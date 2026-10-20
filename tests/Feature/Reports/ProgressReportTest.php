<?php

namespace Tests\Feature\Reports;

use App\Models\CatalogCourse;
use App\Models\ProgressReport;
use App\Models\ProgressReportItem;
use App\Models\User;
use App\Services\Reports\FastSheetReader;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsSpreadsheets;
use Tests\TestCase;

/**
 * Seguimiento anual por persona (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md) con Excel SINTÉTICOS.
 * Reglas de Capacitación (2026-10-19): realizado = 100 % + evaluación; aprobado = además ≥ mínima
 * (80; 90 en Corresponsal Cambiario); sin evaluación basta el 100 %; solo cuenta 2026 en adelante.
 */
class ProgressReportTest extends TestCase
{
    use BuildsSpreadsheets, RefreshDatabase;

    private User $admin;

    private const PROGRESS_HEADERS = [
        'No. Empleado', 'No. Nómina', 'RFC/TaxID', 'CURP/GovID', 'Nombre', 'Apellido Paterno', 'Apellido Materno', 'Dirección',
        'Departamento', 'Puesto', 'Centro de Trabajo', 'Unidad de Negocio', 'Razón social', 'Nombre de Curso', 'Proveedor de capacitación',
        'Duración curso (hrs)', 'Progreso global', 'Progreso módulo 1', 'Iniciado', 'Completado', 'Calificación Evaluación', 'Certificado',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate(User::ROLE_ADMIN, 'web');
        $this->admin = tap(User::factory()->create())->assignRole(User::ROLE_ADMIN);
        config(['mera.reports.temp_root' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'mera-progress-'.uniqid()]);

        foreach ([
            ['NRM-HIG-002-EL-26', 'Normativo', 'HIG', 2, 2026, 'Higiene 2026', 80, true, true],
            ['NEG-OPE-007-EL-26-MX', 'Negocio', 'OPE', 7, 2026, 'Corresponsal Cambiario', 90, true, true],
            ['NEG-CUL-004-EL-26', 'Negocio', 'CUL', 4, 2026, 'Inducción a la Empresa', 80, false, true],
            ['NRM-HIG-001-EL-25', 'Normativo', 'HIG', 1, 2025, 'Higiene 2025', 80, true, false],
        ] as [$code, $route, $sub, $number, $year, $title, $grade, $evaluation, $tracking]) {
            CatalogCourse::create([
                'code' => $code, 'route' => $route, 'sub' => $sub, 'number' => $number, 'modality' => 'EL', 'year' => $year,
                'title' => $title, 'short_title' => $title, 'passing_grade' => $grade, 'has_evaluation' => $evaluation, 'in_tracking' => $tracking, 'hours' => 1,
            ]);
        }
    }

    private function row(string $num, string $name, string $unit, string $course, float $progress, ?float $grade, string $department = 'Cocina', ?string $started = '2026-05-10'): array
    {
        return [
            $num, '999', 'RFC-NO-SE-GUARDA', 'CURP-NO-SE-GUARDA', $name, 'Pérez', 'López', 'Dirección privada',
            $department, 'Cocinero A', 'Restaurante Uno', $unit, 'Razón', $course, 'Proveedor',
            1, $progress, $progress, $started ? new DateTime($started) : '', $progress >= 1 ? new DateTime('2026-06-01') : '', $grade ?? '', '',
        ];
    }

    /** @return array{progreso: UploadedFile, personal: UploadedFile} */
    private function files(): array
    {
        $progress = $this->makeXlsx([
            self::PROGRESS_HEADERS,
            $this->row('1', 'Ana', 'Monterrey', 'NRM-HIG-002-EL-26 | Higiene', 1, 95),                 // aprobado
            $this->row('1', 'Ana', 'Monterrey', 'NEG-OPE-007-EL-26-MX | Corresponsal', 1, 85),        // reprobado (pide 90)
            $this->row('1', 'Ana', 'Monterrey', 'NEG-CUL-004-EL-26 | Inducción', 1, null),           // aprobado (sin evaluación)
            $this->row('1', 'Ana', 'Monterrey', 'NRM-HIG-001-EL-25 | Higiene 2025', 1, 100),         // 2025: no cuenta
            $this->row('2', 'Beto', 'Monterrey', 'NRM-HIG-002-EL-26 | Higiene', 1, 70),               // reprobado
            $this->row('2', 'Beto', 'Monterrey', 'NEG-OPE-007-EL-26-MX | Corresponsal', 0.5, 95),     // en curso (no está al 100 %)
            $this->row('3', 'Caro', 'Tijuana', 'NRM-HIG-002-EL-26 | Higiene', 1, null),               // en curso (sin evaluación presentada)
            $this->row('3', 'Caro', 'Tijuana', 'NEG-CUL-004-EL-26 | Inducción', 0, null, 'Cocina', null), // sin iniciar
            $this->row('4', 'Dani', 'Tijuana', 'CMP-XYZ-001-EL-26 | Curso nuevo', 1, 90),              // código fuera del catálogo
            $this->row('5', 'Eva', 'Cancun C4', 'NRM-HIG-002-EL-26 | Higiene', 1, 100),              // unidad excluida
            $this->row('6', 'Fer', 'Monterrey', 'NRM-HIG-002-EL-26 | Higiene', 1, 100),              // baja (no está en personal)
        ], 'Reporte de progreso de capacitaciones 2026-10-12.xlsx');

        $personal = $this->makeXlsx([
            ['No. de empleado', 'Nombre', 'Unidad de negocio', 'Departamento', 'Puesto'],
            ['1', 'Ana', 'Monterrey', 'Cocina', 'COCINERO A'],
            ['2', 'Beto', 'Monterrey', 'Piso', 'MESERO'],
            ['3', 'Caro', 'Tijuana', 'Cocina', 'COCINERO B'],
            ['4', 'Dani', 'Tijuana', 'Bar', 'BARTENDER'],
            ['5', 'Eva', 'Cancun C4', 'Presidencia', 'ASISTENTE'],
        ], 'Reporte de personal 2026-10-14.xlsx');

        return [
            'progreso' => new UploadedFile($progress, basename($progress), null, null, true),
            'personal' => new UploadedFile($personal, basename($personal), null, null, true),
        ];
    }

    private function createCut(string $date = '2026-10-12'): ProgressReport
    {
        $this->actingAs($this->admin)->post(route('progress.store'), ['title' => 'Capacitación 2026', 'cut_date' => $date] + $this->files())
            ->assertSessionHasNoErrors();

        return ProgressReport::latest('id')->firstOrFail();
    }

    public function test_a_cut_applies_capacitacion_rules_and_never_keeps_the_excel_or_private_columns(): void
    {
        $report = $this->createCut();

        $statuses = ProgressReportItem::query()->join('progress_report_people as p', 'p.id', '=', 'progress_report_items.progress_report_person_id')
            ->get(['p.employee_number', 'course_code', 'status'])->map(fn ($r) => $r->employee_number.' '.$r->course_code.' '.$r->status)->sort()->values()->all();

        $this->assertSame([
            '1 NEG-CUL-004-EL-26 1', '1 NEG-OPE-007-EL-26-MX 2', '1 NRM-HIG-001-EL-25 1', '1 NRM-HIG-002-EL-26 1',
            '2 NEG-OPE-007-EL-26-MX 3', '2 NRM-HIG-002-EL-26 2',
            '3 NEG-CUL-004-EL-26 4', '3 NRM-HIG-002-EL-26 3',
            '4 CMP-XYZ-001-EL-26 1',
        ], $statuses);

        $this->assertSame(4, $report->people()->count()); // sin la unidad excluida ni la baja
        $this->assertSame('Pérez', explode(' ', $report->people()->firstWhere('employee_number', '1')->full_name)[1]);
        // Localidad, área y puesto salen del reporte de personal con las reglas del reporte por curso.
        $dani = $report->people()->firstWhere('employee_number', '4');
        $this->assertSame(['Tijuana', 'Bar', 'Bartender'], [$dani->locality, $dani->area, $dani->position]);
        $warnings = implode(' ', $report->warnings);
        $this->assertStringContainsString('1 personas (1 asignaciones) de unidades excluidas', $warnings);
        $this->assertStringContainsString('1 personas (1 asignaciones) ya no están en el reporte de personal', $warnings);
        $this->assertStringContainsString('CMP-XYZ-001-EL-26', $warnings);

        // Nada privado en la base y la carpeta temporal se borró.
        $dump = json_encode(DB::table('progress_report_people')->get()).json_encode(DB::table('progress_report_items')->get());
        $this->assertStringNotContainsString('RFC-NO-SE-GUARDA', $dump);
        $this->assertStringNotContainsString('Dirección privada', $dump);
        $this->assertSame([], File::isDirectory(config('mera.reports.temp_root')) ? File::directories(config('mera.reports.temp_root')) : []);
    }

    public function test_the_dashboard_counts_only_2026_courses_and_filters(): void
    {
        $report = $this->createCut();

        $response = $this->actingAs($this->admin)->get(route('progress.show', $report))->assertOk();
        $totals = $response->viewData('totals');

        // 8 asignaciones de 2026 (sin la de 2025): 3 aprobadas, 2 reprobadas, 2 en curso, 1 sin iniciar.
        $this->assertSame([8, 4, 3, 2, 5, 2, 1], [$totals['assigned'], $totals['people'], $totals['passed'], $totals['failed'], $totals['done'], $totals['in_progress'], $totals['not_started']]);
        $this->assertSame(62.5, $totals['pct_done']);
        $this->assertSame(85.0, $totals['avg_grade']); // 95, 85, 70 y 90 (Inducción no tiene evaluación)

        $response->assertSee('Cómo va cada curso')->assertSee('Higiene 2026')->assertSee('Ranking de localidades')->assertDontSee('Higiene 2025');

        $monterrey = $this->actingAs($this->admin)->get(route('progress.show', ['report' => $report, 'localidad' => 'Monterrey']))->viewData('totals');
        $this->assertSame([5, 2], [$monterrey['assigned'], $monterrey['people']]);

        $higiene = $this->actingAs($this->admin)->get(route('progress.show', ['report' => $report, 'curso' => 'NRM-HIG-002-EL-26']))->viewData('totals');
        $this->assertSame([3, 1, 1], [$higiene['assigned'], $higiene['passed'], $higiene['failed']]);

        // Si un curso deja de contar en el catálogo, sale del tablero.
        CatalogCourse::where('code', 'NEG-CUL-004-EL-26')->update(['in_tracking' => false]);
        $this->assertSame(6, $this->actingAs($this->admin)->get(route('progress.show', $report))->viewData('totals')['assigned']);
    }

    public function test_people_lists_csv_and_person_card(): void
    {
        $report = $this->createCut();

        $this->actingAs($this->admin)->get(route('progress.people', ['report' => $report, 'estado' => 'reprobados']))
            ->assertOk()->assertSee('Ana Pérez López')->assertSee('Beto Pérez López')->assertDontSee('Caro Pérez López');

        $this->actingAs($this->admin)->get(route('progress.people', ['report' => $report, 'estado' => 'sin-iniciar', 'q' => 'caro']))
            ->assertOk()->assertSee('Caro Pérez López')->assertDontSee('Ana Pérez López');

        $csv = $this->actingAs($this->admin)->get(route('progress.export', ['report' => $report, 'estado' => 'reprobados']))->assertOk()->streamedContent();
        $this->assertStringContainsString('No. empleado', $csv);
        $this->assertStringContainsString('Corresponsal Cambiario', $csv);
        $this->assertSame(1, Activity::where('description', 'like', 'Descargó la lista%')->count());

        $ana = $report->people()->firstWhere('employee_number', '1');
        $this->actingAs($this->admin)->get(route('progress.person', [$report, $ana]))
            ->assertOk()->assertSee('Ana Pérez López')->assertSee('Corresponsal Cambiario')->assertSee('aprueba con 90')->assertDontSee('Higiene 2025');

        // Una persona de otro corte da 404.
        $other = $this->createCut('2026-10-13');
        $this->actingAs($this->admin)->get(route('progress.person', [$other, $ana]))->assertNotFound();
    }

    public function test_access_retention_and_delete(): void
    {
        $this->get(route('progress.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('progress.index'))->assertForbidden();

        config(['mera.progress.keep_reports' => 1]);
        $old = $this->createCut('2026-09-01');
        $new = $this->createCut('2026-10-12');
        $this->assertModelMissing($old);
        $this->assertSame(0, ProgressReportItem::where('progress_report_id', $old->id)->count());

        $this->actingAs($this->admin)->delete(route('progress.destroy', $new))->assertRedirect(route('progress.index'));
        $this->assertSame(0, ProgressReport::count() + ProgressReportItem::count());
    }

    public function test_the_fast_reader_reads_only_the_requested_columns(): void
    {
        $path = $this->makeXlsx([
            ['A', 'Nombre', 'B', 'Fecha', 'Número'],
            ['x', 'Ñandú "uno"', 'y', new DateTime('2026-01-15'), 12.5],
            [],
            ['', 'Dos', '', '', 3],
        ]);

        $rows = iterator_to_array((new FastSheetReader)->rows($path, 'prueba', ['n' => ['nombre'], 'f' => ['Fecha'], 'num' => ['Número']]), false);

        $this->assertSame([['n' => 'Ñandú "uno"', 'f' => '46037', 'num' => '12.5'], ['n' => 'Dos', 'f' => null, 'num' => '3']], $rows);
        $this->assertSame(27, FastSheetReader::columnIndex('AB12'));
    }
}
