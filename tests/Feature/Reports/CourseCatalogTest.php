<?php

namespace Tests\Feature\Reports;

use App\Models\CatalogCourse;
use App\Models\User;
use App\Services\Reports\CourseCatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsSpreadsheets;
use Tests\TestCase;

/**
 * Catálogo de cursos (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md §6), con un Excel SINTÉTICO que
 * imita el de Capacitación: título arriba, encabezados en la fila 3, filas de sección y la columna
 * "Nomenclatura" como fórmula.
 */
class CourseCatalogTest extends TestCase
{
    use BuildsSpreadsheets, RefreshDatabase;

    private const HEADERS = ['Nomenclatura', 'Ruta', 'Sub', '#', 'Mod', 'Año', 'País', 'Título Completo del Curso', 'NOM / Referencia', 'Competencia Asociada', 'Nivel', 'Dur (h)', 'Instructor', 'Título Corto'];

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate(User::ROLE_SUPER_ADMIN, 'web');
        Role::findOrCreate(User::ROLE_ADMIN, 'web');
        $this->superAdmin = tap(User::factory()->create())->assignRole(User::ROLE_SUPER_ADMIN);
    }

    private function catalog(array $extra = [], string $higieneTitle = 'Higiene AyB 2026'): string
    {
        return $this->makeXlsx([
            ['CATÁLOGO DE CURSOS — MERA University'],
            ['Nomenclatura: Ruta · Sub · # · Modalidad · Año · País'],
            self::HEADERS,
            ['  ▶  NORMATIVO'],
            ['      ▸  HIG — Higiene'],
            ['=IFERROR(…)', 'Normativo', 'HIG', 2, 'EL', 2026, 'GLOBAL', 'Curso: Introducción a la Higiene', 'NOM-251', 'Autogestión', 'Cocina', 2, 'Lilia', $higieneTitle],
            ['=IFERROR(…)', 'Normativo', 'SEG', 5, 'EL', 2026, 'MX', 'Factores de riesgo psicosocial', 'NOM-035', '', 'Todo MERA', 1, '', 'Factores Psicosociales'],
            ['=IFERROR(…)', 'Negocio', 'OPE', 7, 'EL', 2026, 'MX', 'Corresponsal Cambiario', 'Ingresos', '', 'Operativo', 1, '', 'Corresponsal Cambiario'],
            ['=IFERROR(…)', 'Negocio', 'CUL', 4, 'EL', 2026, 'GLOBAL', 'El Éxito a la MERA', '', '', 'Todo MERA', '', '', 'Inducción a la Empresa'],
            ['=IFERROR(…)', 'Competencias', 'LID', 6, 'BL', 2026, 'GLOBAL', 'Masterclass: Liderazgo Positivo', '', '', 'Gerencia+', 1, '', 'Liderazgo Positivo 2026'],
            ['=IFERROR(…)', 'Competencias', 'LID', '', 'BL', 2026, 'GLOBAL', 'Fila sin número', '', '', '', '', '', ''],
            ['=IFERROR(…)', 'Normativo', 'HIG', 1, 'EL', 2025, 'GLOBAL', 'Curso: Introducción a la Higiene', 'NOM-251', '', 'Cocina', 2, '', 'Higiene AyB 2025'],
            ['  ▶  DESARROLLO PERSONAL'],
            ['=IFERROR(…)'],
            ...$extra,
        ], 'Catalogo_MERA_v12.xlsx');
    }

    public function test_import_builds_codes_skips_sections_and_applies_capacitacion_defaults(): void
    {
        $result = app(CourseCatalogImporter::class)->import($this->catalog());

        $this->assertSame(['NRM-HIG-002-EL-26', 'NRM-SEG-005-EL-26-MX', 'NEG-OPE-007-EL-26-MX', 'NEG-CUL-004-EL-26', 'CMP-LID-006-BL-26', 'NRM-HIG-001-EL-25'], $result['created']);
        $this->assertCount(1, $result['skipped']); // la fila sin número

        $higiene = CatalogCourse::firstWhere('code', 'NRM-HIG-002-EL-26');
        $this->assertSame(['Normativo', 'HIG', 2026, null, 2.0, 'Higiene AyB 2026', 80, true, true], [
            $higiene->route, $higiene->sub, $higiene->year, $higiene->country, $higiene->hours, $higiene->short_title,
            $higiene->passing_grade, $higiene->has_evaluation, $higiene->in_tracking,
        ]);
        $this->assertSame('MX', CatalogCourse::firstWhere('code', 'NRM-SEG-005-EL-26-MX')->country);

        // Solo cuenta 2026 en adelante: lo de 2025 queda en el catálogo, fuera del seguimiento.
        $this->assertFalse(CatalogCourse::firstWhere('code', 'NRM-HIG-001-EL-25')->in_tracking);

        // Respuestas de Capacitación: Corresponsal Cambiario se aprueba con 90; Inducción no tiene evaluación.
        $this->assertSame(90, CatalogCourse::firstWhere('code', 'NEG-OPE-007-EL-26-MX')->passing_grade);
        $this->assertFalse(CatalogCourse::firstWhere('code', 'NEG-CUL-004-EL-26')->has_evaluation);
    }

    public function test_reimporting_updates_excel_data_but_keeps_panel_settings_and_never_deletes(): void
    {
        $importer = app(CourseCatalogImporter::class);
        $importer->import($this->catalog());

        CatalogCourse::firstWhere('code', 'NRM-HIG-002-EL-26')->update(['passing_grade' => 85, 'in_tracking' => false, 'aliases' => ['NRM-HIG-009-EL-26']]);

        $result = $importer->import($this->catalog([], 'Higiene 2026 (nuevo nombre)'));
        $this->assertSame(['NRM-HIG-002-EL-26'], $result['updated']);
        $this->assertSame(5, $result['unchanged']);

        $higiene = CatalogCourse::firstWhere('code', 'NRM-HIG-002-EL-26');
        $this->assertSame(['Higiene 2026 (nuevo nombre)', 85, false, ['NRM-HIG-009-EL-26']], [$higiene->short_title, $higiene->passing_grade, $higiene->in_tracking, $higiene->aliases]);

        // Un curso que ya no viene en el Excel se conserva y se avisa.
        CatalogCourse::create(['code' => 'NRM-QUI-001-BL-25', 'route' => 'Normativo', 'sub' => 'QUI', 'number' => 1, 'modality' => 'BL', 'year' => 2025, 'title' => 'Químicos', 'short_title' => 'Químicos']);
        $this->assertSame(['NRM-QUI-001-BL-25'], $importer->import($this->catalog())['missing']);
        $this->assertSame(7, CatalogCourse::count());

        // --dry-run no escribe.
        $importer->import($this->catalog([['', 'Negocio', 'CUL', 8, 'EL', 2026, 'GLOBAL', 'Código de Ética', '', '', '', 2, '', 'Código de Ética']]), dryRun: true);
        $this->assertNull(CatalogCourse::firstWhere('code', 'NEG-CUL-008-EL-26'));
    }

    public function test_platform_codes_resolve_by_alias_and_the_pass_rules(): void
    {
        $importer = app(CourseCatalogImporter::class);
        $importer->import($this->catalog());

        $csv = sys_get_temp_dir().DIRECTORY_SEPARATOR.'equiv-'.Str::random(6).'.csv';
        File::put($csv, "codigo_plataforma,titulo_en_plataforma,codigo_catalogo_v12,titulo_corto_catalogo,confianza,nota\n"
            ."NEG-LID-003-BL-26,Liderazgo Positivo,CMP-LID-006-BL-26,Liderazgo Positivo 2026,alta,\n"
            ."NEG-LID-004-BL-25,Coaching Ejecutivo,,,sin equivalente,\n");
        $this->assertSame(['added' => 1, 'unmatched' => ['NEG-LID-004-BL-25 (sin código del catálogo)']], $importer->importAliases($csv));
        File::delete($csv);

        $this->assertSame('CMP-LID-006-BL-26', CatalogCourse::resolve('NEG-LID-003-BL-26')?->code);
        $this->assertSame('CMP-LID-006-BL-26', CatalogCourse::resolve(CatalogCourse::codeFromName('NEG-LID-003-BL-26 | Liderazgo Positivo'))?->code);
        $this->assertNull(CatalogCourse::resolve('NEG-XXX-001-EL-26'));

        // Realizado = 100 % + evaluación presentada; aprobado = además ≥ mínima (Capacitación, 2026-10-19).
        $higiene = CatalogCourse::firstWhere('code', 'NRM-HIG-002-EL-26');
        $this->assertTrue($higiene->done(1, 70));
        $this->assertFalse($higiene->passes(1, 79.9));
        $this->assertTrue($higiene->passes(1, 80));
        $this->assertFalse($higiene->done(0.9, 95));
        $this->assertFalse($higiene->done(1, null));

        $cambiario = CatalogCourse::firstWhere('code', 'NEG-OPE-007-EL-26-MX');
        $this->assertFalse($cambiario->passes(1, 85));
        $this->assertTrue($cambiario->passes(1, 90));

        $induccion = CatalogCourse::firstWhere('code', 'NEG-CUL-004-EL-26');
        $this->assertTrue($induccion->passes(1, null));
        $this->assertFalse($induccion->passes(0.5, null));
    }

    public function test_only_super_admins_manage_the_catalog_from_the_panel(): void
    {
        $admin = tap(User::factory()->create())->assignRole(User::ROLE_ADMIN);
        $this->actingAs($admin)->get(route('course-catalog.index'))->assertForbidden();

        $upload = new UploadedFile($this->catalog(), 'Catalogo_MERA_v12.xlsx', null, null, true);
        $this->actingAs($this->superAdmin)->post(route('course-catalog.import'), ['catalog' => $upload])
            ->assertRedirect(route('course-catalog.index'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '6 nuevos') && str_contains($m, 'No se pudieron leer 1'));

        $this->actingAs($this->superAdmin)->get(route('course-catalog.index', ['anio' => 2026, 'q' => 'cambiario']))
            ->assertOk()->assertSee('Corresponsal Cambiario')->assertSee('100 % y 90 o más')->assertDontSee('Higiene AyB 2026');

        $this->actingAs($this->superAdmin)->post(route('course-catalog.import'), ['catalog' => UploadedFile::fake()->create('x.pdf', 10)])
            ->assertSessionHasErrors('catalog');
    }

    public function test_panel_settings_and_alias_validation(): void
    {
        app(CourseCatalogImporter::class)->import($this->catalog());
        $higiene = CatalogCourse::firstWhere('code', 'NRM-HIG-002-EL-26');
        $liderazgo = CatalogCourse::firstWhere('code', 'CMP-LID-006-BL-26');
        $liderazgo->update(['aliases' => ['NEG-LID-003-BL-26']]);

        $this->actingAs($this->superAdmin)->put(route('course-catalog.update', $higiene), [
            'short_title' => 'Higiene', 'passing_grade' => 80, 'aliases' => "NEG-LID-003-BL-26\nno-es-codigo",
        ])->assertSessionHasErrors(['aliases.0', 'aliases.1']);

        $this->actingAs($this->superAdmin)->put(route('course-catalog.update', $higiene), [
            'short_title' => 'Higiene 2026', 'passing_grade' => 85, 'has_evaluation' => 1, 'in_tracking' => 0,
            'aliases' => "nrm-hig-020-el-26 | Higiene vieja\nNRM-HIG-002-EL-26",
        ])->assertRedirect(route('course-catalog.index', ['anio' => 2026]));

        $higiene->refresh();
        $this->assertSame(['Higiene 2026', 85, true, false, ['NRM-HIG-020-EL-26']], [
            $higiene->short_title, $higiene->passing_grade, $higiene->has_evaluation, $higiene->in_tracking, $higiene->aliases,
        ]);
        $this->assertSame($higiene->id, CatalogCourse::resolve('NRM-HIG-020-EL-26')?->id);
    }
}
