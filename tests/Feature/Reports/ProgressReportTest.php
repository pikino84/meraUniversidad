<?php

namespace Tests\Feature\Reports;

use App\Models\CatalogCourse;
use App\Models\CourseGroup;
use App\Models\Market;
use App\Models\ProgressReport;
use App\Models\ProgressReportItem;
use App\Models\ProgressReportSurvey;
use App\Models\User;
use App\Services\Reports\FastSheetReader;
use App\Services\Reports\ProgressSurveyAnalyzer;
use App\Support\TestEnvironment;
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
        // Aislado de la configuración real de Higiene (y de su archivo privado de clasificación previa).
        config(['report_surveys.higiene.course_codes' => []]);

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

        // Resumen por defecto; cada pestaña muestra su sección.
        $response->assertSee('Cursos con menor avance')->assertSee('Localidades con menor avance')->assertSee('Avance mes a mes');
        $cursos = $this->actingAs($this->admin)->get(route('progress.show', ['report' => $report, 'vista' => 'cursos']))
            ->assertOk()->assertSee('Cómo va cada curso')->assertSee('Higiene 2026')->assertDontSee('Higiene 2025');
        $this->assertSame('avance-asc', $cursos->viewData('sort'));
        $this->assertSame(['CMP-XYZ-001-EL-26', 'Corresponsal Cambiario', 'Higiene 2026'], // sin curso en el catálogo: su código
            $this->actingAs($this->admin)->get(route('progress.show', ['report' => $report, 'vista' => 'cursos', 'orden' => 'nombre']))->viewData('courses')->pluck('label')->take(3)->all());
        foreach (['localidades' => 'Ranking de localidades', 'areas' => 'Puestos con menor avance', 'evaluacion' => 'Promedio por curso', 'opiniones' => 'Lo que opinan'] as $view => $text) {
            $this->actingAs($this->admin)->get(route('progress.show', ['report' => $report, 'vista' => $view]))->assertOk()->assertSee($text);
        }

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

    public function test_comparisons_use_only_general_courses_unless_asked(): void
    {
        CatalogCourse::where('code', 'NEG-OPE-007-EL-26-MX')->update(['scope' => CatalogCourse::SCOPE_SPECIFIC, 'modality' => 'BL']);
        config(['mera.progress.dashboard.ranking_min_people' => 1]);
        $report = $this->createCut();
        $ana = $report->people()->firstWhere('employee_number', '1');

        // Las tarjetas de avance cuentan todo; cursos por persona y el ranking, solo los generales.
        $default = $this->actingAs($this->admin)->get(route('progress.show', $report))->assertOk()->assertSee('solo cursos generales');
        $this->assertSame(8, $default->viewData('totals')['assigned']);
        $this->assertSame(6, $default->viewData('comparable')['assigned']);
        $ranking = $this->actingAs($this->admin)->get(route('progress.show', ['report' => $report, 'vista' => 'localidades']));
        $monterrey = $ranking->viewData('localities')->firstWhere('k', 'Monterrey');
        $this->assertSame(3, $monterrey['assigned'] ?? null); // sin Corresponsal Cambiario
        $cursos = $this->actingAs($this->admin)->get(route('progress.show', ['report' => $report, 'vista' => 'cursos']))
            ->assertSee('Cursos específicos y presenciales')->assertSee('BL · presencial');
        $specific = $cursos->viewData('specific');
        $this->assertSame(['NEG-OPE-007-EL-26-MX'], $specific->pluck('k')->all());
        $this->assertSame(['Cocina' => 1, 'Piso / Restaurantes' => 1], $specific[0]['areas']);

        $all = $this->actingAs($this->admin)->get(route('progress.show', ['report' => $report, 'especificos' => 1]))->assertDontSee('solo cursos generales');
        $this->assertSame(8, $all->viewData('comparable')['assigned']);

        // Si se elige un curso específico, el ranking es de ese curso.
        $one = $this->actingAs($this->admin)->get(route('progress.show', ['report' => $report, 'curso' => 'NEG-OPE-007-EL-26-MX']));
        $this->assertSame(2, $one->viewData('comparable')['assigned']);

        // Ficha: generales y específicos por separado.
        $this->actingAs($this->admin)->get(route('progress.person', [$report, $ana]))
            ->assertSeeInOrder(['Cursos generales', 'Higiene 2026', 'Específicos de su puesto o área', 'Corresponsal Cambiario']);
    }

    private function survey(string $title, array $answers): UploadedFile
    {
        $headers = ['Cuestionario', 'Fecha', 'No. Empleado', 'Colaborador', 'Estatus colaborador', 'Departamento', 'Puesto', 'Centro de trabajo', 'Entidad legal', 'Resultado',
            '¡Hola! Gracias', '¿Los temas tratados son útiles?', '¿Tuviste algún problema técnico durante el curso? ', '¿Recomendarías este curso? ',
            'En una escala del 0 al 10, ¿qué tan probable es que recomiendes este curso?', '¿Qué fue lo que más te gustó del curso?', '¡Gracias!'];
        $rows = [$headers];
        foreach ($answers as [$num, $name, $status, $useful, $tech, $recommend, $score, $liked]) {
            $rows[] = [$title, new DateTime('2026-10-01'), $num, $name, $status, 'Cocina', 'Cocinero', 'Centro', 'MERA', 100, '', $useful.'  ', $tech.' ', $recommend.' ', $score, $liked, ''];
        }
        $path = $this->makeXlsx($rows, 'encuesta.xlsx');

        return new UploadedFile($path, '2026-10-19 Encuesta de satisfacción responses.xlsx', null, null, true);
    }

    public function test_course_surveys_show_common_indicators_without_personal_data(): void
    {
        $report = $this->createCut();
        $file = $this->survey('Encuesta de Satisfacción - Higiene 2026', [
            ['1', 'Ana Pérez', 'active', 'Muy de acuerdo', 'No', 'Sí', 10, 'Los videos'],
            ['2', 'Beto Pérez', 'active', 'De acuerdo', 'Sí', 'Sí', 9, 'Los ejemplos de cocina'],
            ['3', 'Caro Pérez', 'deleted', 'Indeciso', 'No', 'No', 4, ''],
            ['9', 'Zoe Sin Curso', 'active', 'Muy de acuerdo', 'No', 'Sí', 8, 'Todo'],
        ]);

        // Sin elegir curso: se reconoce por el título.
        $this->actingAs($this->admin)->post(route('progress.surveys.store', $report), ['encuesta' => $file])
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'Higiene 2026') && str_contains($m, '4 respuestas'));

        $survey = $report->surveys()->sole();
        $this->assertSame(['NRM-HIG-002-EL-26', 4, 3, 1], [$survey->course_code, $survey->responses, $survey->active, $survey->not_in_cut]); // Zoe no tiene el curso
        $this->assertEquals(75, $survey->indicator('recommend'));
        $this->assertSame(25, $survey->indicator('nps'));      // 2 promotores y 1 detractor, de 4
        $this->assertEquals(25, $survey->indicator('tech_problems'));
        $this->assertNull($survey->indicator('satisfaction')); // sin configuración para este código en las pruebas
        $this->assertSame(['Muy de acuerdo' => 2, 'De acuerdo' => 1, 'Indeciso' => 1], $survey->data['closed'][0]['answers']); // sin espacios al final
        $this->assertSame(['Los videos', 'Los ejemplos de cocina', 'Todo'], $survey->data['open'][0]['answers']);

        $stored = json_encode($survey->getAttributes());
        $this->assertStringNotContainsString('Ana Pérez', $stored);
        $this->assertStringNotContainsString('Zoe', $stored);

        $this->actingAs($this->admin)->get(route('progress.show', ['report' => $report, 'vista' => 'opiniones', 'curso' => 'NRM-HIG-002-EL-26']))
            ->assertOk()->assertSee('Lo que opinan')->assertSee('+25')->assertSee('respuestas por pregunta')->assertSee('Los ejemplos de cocina');

        // Otra encuesta del mismo curso la reemplaza; un título que no se reconoce pide elegir el curso.
        $this->actingAs($this->admin)->post(route('progress.surveys.store', $report), ['encuesta' => $this->survey('Encuesta de Satisfacción - Higiene 2026', [['1', 'Ana', 'active', 'Muy de acuerdo', 'No', 'Sí', 10, 'Bien']])]);
        $this->assertSame(1, $report->surveys()->sole()->responses);

        $this->actingAs($this->admin)->post(route('progress.surveys.store', $report), ['encuesta' => $this->survey('Encuesta de Satisfacción - Algo que no existe', [['1', 'Ana', 'active', 'Sí', 'No', 'Sí', 10, '']])])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Elige el curso'));
        $this->actingAs($this->admin)->post(route('progress.surveys.store', $report), [
            'encuesta' => $this->survey('Encuesta de Satisfacción - Algo que no existe', [['1', 'Ana', 'active', 'Sí', 'No', 'Sí', 10, '']]),
            'course_code' => 'NEG-CUL-004-EL-26',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['NEG-CUL-004-EL-26', 'NRM-HIG-002-EL-26'], $report->surveys()->orderBy('course_code')->pluck('course_code')->all());

        $this->actingAs($this->admin)->delete(route('progress.surveys.destroy', [$report, $report->surveys()->firstWhere('course_code', 'NEG-CUL-004-EL-26')]));
        $this->assertSame(1, $report->surveys()->count());
    }

    public function test_configured_surveys_use_the_same_satisfaction_rule_as_the_course_report(): void
    {
        config(['report_surveys' => ['prueba' => [
            'name' => 'Prueba',
            'course_codes' => ['NRM-HIG-002-EL-26'],
            'favorable' => [['f_util', 'temas tratados son útiles', ['Muy de acuerdo', 'De acuerdo']], ['f_rec', 'Recomendarías', ['Sí']]],
        ]]]);
        $report = $this->createCut();

        $this->actingAs($this->admin)->post(route('progress.surveys.store', $report), ['encuesta' => $this->survey('Encuesta de Satisfacción - Higiene 2026', [
            ['1', 'A', 'active', 'Muy de acuerdo', 'No', 'Sí', 10, ''],
            ['2', 'B', 'active', 'De acuerdo', 'No', 'No', 9, ''],
            ['3', 'C', 'active', 'Indeciso', 'No', 'Sí', 9, ''],
            ['4', 'D', 'active', 'De acuerdo', 'No', 'Sí', 9, ''],
        ])]);

        // Favorable en TODAS las configuradas: A y D → 50 %.
        $this->assertEquals(50, $report->surveys()->sole()->indicator('satisfaction'));
    }

    public function test_course_versions_and_the_tracking_report_are_connected_by_catalog_code(): void
    {
        $report = $this->createCut();
        $group = CourseGroup::create(['name' => 'Higiene']);
        $market = Market::where('code', 'MX')->value('id');
        $version = $group->versions()->create(['year' => 2026, 'market_id' => $market]);

        // Sin código del catálogo: invita a agregarlo.
        $this->actingAs($this->admin)->get(route('course-groups.versions.show', [$group, $version, 'seccion' => 'reportes']))
            ->assertOk()->assertSee('En el seguimiento anual')->assertSee('no tiene')->assertSee('código del catálogo');

        // Con código: cifras del curso en el último corte y link al tablero filtrado.
        $version->update(['catalog_code' => 'NRM-HIG-002-EL-26']);
        $this->actingAs($this->admin)->get(route('course-groups.versions.show', [$group, $version, 'seccion' => 'reportes']))
            ->assertOk()->assertSee('Higiene 2026 · corte al 12/10/2026')->assertSee('33.3 %') // 1 aprobado de 3
            ->assertSee(route('progress.show', ['report' => $report, 'curso' => 'NRM-HIG-002-EL-26', 'anio' => 2026]));

        // Y al revés: el tablero filtrado por el curso enlaza a su carpeta.
        $this->actingAs($this->admin)->get(route('progress.show', ['report' => $report, 'curso' => 'NRM-HIG-002-EL-26']))
            ->assertSee('Este curso en las carpetas')->assertSee(route('course-groups.versions.show', [$group, $version, 'seccion' => 'reportes']), false);

        // Un curso que no cuenta para el seguimiento lo dice.
        $version->update(['catalog_code' => 'NRM-HIG-001-EL-25']);
        $this->actingAs($this->admin)->get(route('course-groups.versions.show', [$group, $version, 'seccion' => 'reportes']))
            ->assertSee('no cuenta para el seguimiento anual');
    }

    public function test_cut_url_uses_its_date_and_title_and_date_can_be_edited(): void
    {
        $report = $this->createCut('2026-10-12');
        $this->assertSame('2026-10-12', $report->slug);
        $this->assertStringEndsWith('/seguimiento/2026-10-12', route('progress.show', $report));
        // El link anterior (número) lleva al de la fecha, con sus filtros.
        $this->actingAs($this->admin)->get('/seguimiento/'.$report->id.'?vista=cursos')->assertStatus(301)->assertRedirect('/seguimiento/2026-10-12?vista=cursos');

        $same = $this->createCut('2026-10-12');
        $this->assertSame('2026-10-12-2', $same->slug);

        $this->actingAs($this->admin)->put(route('progress.update', $report), ['title' => 'Capacitación 2026 · octubre', 'cut_date' => '2026-10-11'])
            ->assertRedirect('/seguimiento/2026-10-11');
        $this->assertSame(['Capacitación 2026 · octubre', '2026-10-11'], [$report->fresh()->title, $report->fresh()->slug]);

        $this->actingAs($this->admin)->put(route('progress.update', $report->fresh()), ['title' => '', 'cut_date' => '2099-01-01'])
            ->assertSessionHasErrors(['title', 'cut_date']);
        $this->actingAs($this->admin)->get(route('progress.edit', $report->fresh()))->assertOk()->assertSee('/seguimiento/2026-10-11');
    }

    public function test_shared_links_show_one_locality_without_names_and_can_be_disabled(): void
    {
        $report = $this->createCut();
        $this->actingAs($this->admin)->post(route('progress.surveys.store', $report), ['encuesta' => $this->survey('Encuesta de Satisfacción - Higiene 2026', [
            ['1', 'Ana Pérez', 'active', 'Muy de acuerdo', 'No', 'Sí', 10, 'Me gustó, atte. Ana Pérez'],
        ])]);

        auth()->logout();
        $this->get(route('progress.shares', $report))->assertRedirect(route('login'));
        $this->actingAs($this->admin)->post(route('progress.shares.store', $report), ['locality' => 'Monterrey'])->assertRedirect(route('progress.shares', $report));
        $this->actingAs($this->admin)->post(route('progress.shares.store', $report), ['locality' => 'Monterrey']); // no duplica
        $this->actingAs($this->admin)->post(route('progress.shares.store', $report), ['locality' => 'No existe'])->assertSessionHasErrors('locality');
        $share = $report->shares()->sole();
        $this->actingAs($this->admin)->get(route('progress.shares', $report))->assertOk()->assertSee($share->url());

        auth()->logout();

        // Sin sesión, fijo en Monterrey, sin nombres, sin colaboradores, sin pestaña de localidades, noindex.
        $response = $this->get($share->url())->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('Capacitación 2026 · Monterrey')->assertDontSee('Colaboradores')->assertDontSee('Localidades</a>', false)
            ->assertDontSee('Ana Pérez')->assertDontSee('Localidades con menor avance');
        $this->assertSame([5, 2], [$response->viewData('totals')['assigned'], $response->viewData('totals')['people']]);

        // Aunque se pida otra localidad en la URL, se queda en la suya; las opiniones no muestran respuestas abiertas.
        $this->assertSame(5, $this->get($share->url().'?localidad=Tijuana')->viewData('totals')['assigned']);
        $this->get($share->url().'?vista=opiniones&curso=NRM-HIG-002-EL-26')->assertOk()->assertSee('respuestas por pregunta')
            ->assertDontSee('atte. Ana')->assertDontSee('Agregar encuesta');
        $this->get($share->url().'?vista=localidades')->assertOk()->assertDontSee('Ranking de localidades');

        // Toda la empresa: sí compara localidades.
        $company = $report->shares()->create(['locality' => null]);
        $this->get($company->url().'?vista=localidades')->assertOk()->assertSee('Ranking de localidades')->assertDontSee('Ana Pérez');

        // Desactivado: 404. "Apagar todo lo compartido" también los apaga.
        $this->actingAs($this->admin)->delete(route('progress.shares.destroy', [$report, $share]));
        auth()->logout();
        $this->get($share->url())->assertNotFound();
        $this->assertSame(1, TestEnvironment::sharedCounts()['reports']);
        TestEnvironment::unshareAll();
        $this->get($company->url())->assertNotFound();
    }

    public function test_the_dashboard_downloads_as_a_single_html_without_names(): void
    {
        config(['mera.progress.dashboard.ranking_min_people' => 1]);
        $report = $this->createCut();
        $this->actingAs($this->admin)->post(route('progress.surveys.store', $report), ['encuesta' => $this->survey('Encuesta de Satisfacción - Higiene 2026', [
            ['1', 'Ana Pérez', 'active', 'Muy de acuerdo', 'No', 'Sí', 10, 'Me gustó, atte. Ana Pérez'],
        ])]);

        $html = $this->actingAs($this->admin)->get(route('progress.download', $report))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="seguimiento-2026-10-12.html"')
            ->getContent();

        $this->assertStringContainsString('<style>', $html);
        $this->assertStringNotContainsString('<link rel="stylesheet" href="http', str_replace('fonts.googleapis', '', $html));
        foreach (['Realizado', '62.5 %', 'Cómo va cada curso', 'Ranking de localidades', 'Avance por área', 'Calificaciones', 'Lo que opinan', 'Higiene 2026'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['Ana Pérez', 'Beto', 'atte.', 'No. empleado', '/seguimiento/'] as $text) {
            $this->assertStringNotContainsString($text, $html); // sin nombres, sin respuestas abiertas, sin links al panel
        }
        $this->assertSame(1, Activity::where('description', 'like', 'Descargó el tablero%')->count());

        // Con una localidad: su nombre en el archivo y sin comparar localidades.
        $one = $this->actingAs($this->admin)->get(route('progress.download', ['report' => $report, 'localidad' => 'Monterrey']))
            ->assertHeader('Content-Disposition', 'attachment; filename="seguimiento-2026-10-12-monterrey.html"')->getContent();
        $this->assertStringContainsString('Capacitación 2026 · Monterrey', $one);
        $this->assertStringNotContainsString('Ranking de localidades', $one);

        auth()->logout();
        $this->get(route('progress.download', $report))->assertRedirect(route('login'));
    }

    public function test_the_dashboard_compares_with_the_previous_cut(): void
    {
        config(['mera.progress.dashboard.ranking_min_people' => 1]);
        $old = $this->createCut('2026-09-01');
        // En el corte anterior nadie había empezado.
        ProgressReportItem::where('progress_report_id', $old->id)->update(['status' => ProgressReportItem::STATUS_NOT_STARTED, 'grade' => null]);
        $new = $this->createCut('2026-10-12');

        // Por defecto, contra el corte inmediato anterior y con los mismos filtros.
        $response = $this->actingAs($this->admin)->get(route('progress.show', $new))->assertOk()->assertSee('Cambios contra el corte del')->assertSee('01/09/2026');
        $this->assertSame($old->id, $response->viewData('compare')->id);
        $this->assertSame(['pct_done' => 62.5, 'pct_passed' => 37.5, 'pct_not_started' => -87.5, 'avg_grade' => null], $response->viewData('deltas')['kpi']);
        $response->assertSee('▲', false)->assertSee('62.5 pts');

        $monterrey = $this->actingAs($this->admin)->get(route('progress.show', ['report' => $new, 'vista' => 'localidades']))->viewData('deltas')['localities'];
        $this->assertSame(80.0, $monterrey['Monterrey']); // 4 de 5 realizados en Monterrey; antes, 0

        $courses = $this->actingAs($this->admin)->get(route('progress.show', ['report' => $new, 'vista' => 'cursos']))->viewData('deltas')['courses'];
        $this->assertEqualsWithDelta(66.7, $courses['NRM-HIG-002-EL-26'], 0.05); // Higiene: 2 de 3 realizados

        // Sin comparación, o contra un corte posterior elegido.
        $this->assertNull($this->actingAs($this->admin)->get(route('progress.show', ['report' => $new, 'comparar' => 'no']))->viewData('compare'));
        $this->assertSame($new->id, $this->actingAs($this->admin)->get(route('progress.show', ['report' => $old, 'comparar' => $new->slug]))->viewData('compare')->id);

        // El corte más antiguo no tiene anterior: lo dice.
        $this->actingAs($this->admin)->get(route('progress.show', $old))->assertSee('Sin comparación');

        // La descarga lleva los mismos cambios.
        $html = $this->actingAs($this->admin)->get(route('progress.download', $new))->getContent();
        $this->assertStringContainsString('Cambios contra el corte del', $html);
        $this->assertStringContainsString('62.5 pts', $html);
    }

    public function test_open_answers_get_topics_that_can_be_corrected(): void
    {
        $report = $this->createCut();
        $this->actingAs($this->admin)->post(route('progress.surveys.store', $report), ['encuesta' => $this->survey('Encuesta de Satisfacción - Higiene 2026', [
            ['1', 'Ana', 'active', 'Muy de acuerdo', 'No', 'Sí', 10, 'Los videos y los ejemplos'],
            ['2', 'Beto', 'active', 'De acuerdo', 'No', 'Sí', 9, 'Todo'],
            ['3', 'Caro', 'active', 'De acuerdo', 'No', 'Sí', 9, 'La maestra Lupita'],
            ['4', 'Dani', 'active', 'De acuerdo', 'No', 'Sí', 9, 'nada'],
        ])]);
        $survey = $report->surveys()->sole();

        // Catálogo base (en las pruebas Higiene no usa su configuración): propuesta por palabras clave.
        $summary = $survey->topicSummary();
        $this->assertSame(3, $summary['liked_n']); // "nada" no cuenta
        $this->assertSame(['v02', 'v03', 'v_todo'], collect($summary['valora'])->pluck('code')->sort()->values()->all());
        $this->assertSame([1, 3], [$summary['unclassified'], $summary['unreviewed']]);

        $this->actingAs($this->admin)->get(route('progress.show', ['report' => $report, 'vista' => 'opiniones', 'curso' => 'NRM-HIG-002-EL-26']))
            ->assertSee('Lo que más valoran')->assertSee('Videos, animaciones e imágenes')->assertSee('Clasificar comentarios');

        $page = $this->actingAs($this->admin)->get(route('progress.surveys.topics', [$report, $survey]))->assertOk()
            ->assertSee('«La maestra Lupita»', false)->assertSee('3 revisados');

        // Corrección: "La maestra Lupita" → explicaciones claras; los 3 quedan revisados.
        $this->actingAs($this->admin)->patch(route('progress.surveys.classify', [$report, $survey]), [
            'shown' => [0, 1, 2],
            'topics' => [0 => ['v02', 'v03'], 1 => ['v_todo'], 2 => ['v01', 'no-existe']],
        ])->assertSessionHas('success', '3 comentarios revisados.');

        $summary = $survey->fresh()->topicSummary();
        $this->assertSame([0, 0], [$summary['unclassified'], $summary['unreviewed']]);
        $this->assertSame(['v01'], $survey->fresh()->data['topics']['answers'][2]['c']);

        // Compartido: los temas como totales, sin los comentarios.
        $share = $report->shares()->create(['locality' => null]);
        auth()->logout();
        $this->get($share->url().'?vista=opiniones&curso=NRM-HIG-002-EL-26')->assertOk()
            ->assertSee('Lo que más valoran')->assertDontSee('Lupita')->assertDontSee('Clasificar comentarios');
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

    /**
     * En el MySQL del servidor las llaves llegan como texto ("5") y el id del corte como entero: con
     * `===` la ficha, clasificar, borrar encuesta y desactivar un link daban 404 (QA, 2026-10-20).
     */
    public function test_routes_work_when_the_database_returns_ids_as_text(): void
    {
        $report = $this->createCut();
        $this->actingAs($this->admin)->post(route('progress.surveys.store', $report), ['encuesta' => $this->survey('Encuesta de Satisfacción - Higiene 2026', [
            ['1', 'Ana', 'active', 'Muy de acuerdo', 'No', 'Sí', 10, 'Los videos y los ejemplos'],
            ['2', 'Beto', 'active', 'De acuerdo', 'No', 'Sí', 9, 'Todo'],
        ])]);
        $share = $report->shares()->create(['locality' => null]);
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, true);

        try {
            $this->assertSame('string', gettype(DB::table('progress_report_shares')->value('progress_report_id')));
            $survey = $report->surveys()->sole();
            $ana = $report->people()->firstWhere('employee_number', '1');

            $this->actingAs($this->admin)->get(route('progress.person', [$report, $ana]))->assertOk()->assertSee('Ana Pérez López');
            $this->actingAs($this->admin)->get(route('progress.surveys.topics', [$report, $survey]))->assertOk();
            $this->actingAs($this->admin)->patch(route('progress.surveys.classify', [$report, $survey]), ['shown' => [0], 'topics' => [0 => ['v02']]])
                ->assertSessionHas('success');
            $this->actingAs($this->admin)->delete(route('progress.shares.destroy', [$report, $share]))->assertRedirect();
            $this->actingAs($this->admin)->delete(route('progress.surveys.destroy', [$report, $survey]))->assertRedirect();
        } finally {
            $pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, false);
        }

        $this->assertModelMissing($share);
        $this->assertModelMissing($survey);
        auth()->logout();
        $this->get($share->url())->assertNotFound();
    }

    /**
     * Respeto que Construye (2026-10-22): «Recomendarías» escrita a mano y calificación general 0–10 en
     * lugar del NPS. El % recomienda deja fuera lo que no es claro; la calificación no se llama NPS.
     */
    public function test_open_recommendation_and_course_rating_without_nps(): void
    {
        $rows = [['Cuestionario', 'No. Empleado', 'Estatus colaborador', '¿Te sientes capaz de aplicar lo aprendido en tu trabajo?', '¿Cómo calificarías el curso en general?', '¿Qué te gustó más del curso?', '¿Recomendarías este curso a otros colaboradores?']];
        foreach ([['Si', 10, 'Todo', 'Claro que si'], ['Si', 9, 'Los ejemplos', 'sii'], ['Parcialmente', 8, 'Nada', 'No'], ['Si', 7, 'El tema', '.']] as $i => [$capaz, $score, $liked, $recommend]) {
            $rows[] = ['Encuesta de satisfacción - Respeto que Construye 2026', (string) ($i + 1), 'active', $capaz, $score, $liked, $recommend];
        }
        $path = $this->makeXlsx($rows, 'respeto.xlsx');

        $result = app(ProgressSurveyAnalyzer::class)->analyze($path, 'NRM-SEG-004-EL-26');
        $indicators = $result['data']['indicators'];

        $this->assertSame(66.7, $indicators['recommend']); // 2 de 3 claras; "." fuera
        $this->assertNull($indicators['nps']);
        $this->assertSame(8.5, $indicators['rating_avg']);
        $this->assertSame(66.7, $indicators['satisfaction']); // capaz + recomienda, sin la "."
        $recommend = collect($result['data']['closed'])->firstWhere('question', '¿Recomendarías este curso a otros colaboradores?');
        $this->assertSame(['Sí' => 2, 'No' => 1, 'Sin respuesta clara' => 1], $recommend['answers']);
        $this->assertNotContains('¿Recomendarías este curso a otros colaboradores?', array_column($result['data']['open'], 'question'));
        $this->assertSame(['Todo', 'Los ejemplos', 'El tema'], array_column($result['data']['topics']['answers'], 'l'));

        $html = view('progress._nps', ['s' => new ProgressReportSurvey(['data' => $result['data']])])->render();
        $this->assertStringContainsString('8.5 / 10', $html);
        $this->assertStringContainsString('calificación del curso', $html);
    }
}
