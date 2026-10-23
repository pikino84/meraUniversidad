<?php

namespace Tests\Feature\Reports;

use App\Models\CourseGroup;
use App\Models\CourseGroupVersion;
use App\Models\CourseReport;
use App\Models\Market;
use App\Models\User;
use App\Services\Reports\CourseReportManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsSpreadsheets;
use Tests\TestCase;

/**
 * Cortes de reporte por carpeta (INSTRUCTIVO_REPORTES.md §8), siempre con Excel SINTÉTICOS.
 */
class CourseReportTest extends TestCase
{
    use BuildsSpreadsheets, RefreshDatabase;

    private const ALL_FAVORABLE = ['De acuerdo', 'Sí', 'Muy claros y aplicables', 'Sí, muchas', 'Sí', 'Fácil', 'Útiles', 'Si'];

    private User $admin;

    private CourseGroup $group;

    private string $tempRoot;

    private CourseGroupVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mera-report-uploads-'.Str::random(8);
        config([
            'mera.reports.temp_root' => $this->tempRoot,
            'report_surveys.higiene.precoded_file' => null,
        ]);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->tempRoot));

        $this->admin = $this->userWithRole(User::ROLE_ADMIN);
        $this->group = CourseGroup::create(['name' => 'Higiene en cocina']);
        $this->version = $this->group->versions()->create(['year' => 2026, 'market_id' => Market::where('code', 'MX')->value('id')]);
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        return tap(User::factory()->create())->assignRole($role);
    }

    /** @return array<string, UploadedFile> */
    private function workbooks(int $completed = 1, ?array $personnelHeader = null): array
    {
        $personnel = $this->makeXlsx([
            $personnelHeader ?? ['No. de empleado', 'Nombre', 'Unidad de negocio', 'Departamento*', 'Puesto'],
            [1, 'A', 'Bajio', 'Cocina', 'chef'],
            [2, 'B', 'Bajio', 'Cocina', 'chef'],
        ], 'personal.xlsx');

        $enrollments = $this->makeXlsx([
            ['No. Empleado', 'Nombre', 'Estatus', 'Progreso %', 'Calificación', 'Iniciado'],
            [1, 'A', $completed >= 1 ? 'completed' : 'in_progress', 100, 90, null],
            [2, 'B', $completed >= 2 ? 'completed' : 'in_progress', 0, $completed >= 2 ? 70 : null, null],
        ], 'inscripciones_2026-10-13.xlsx');

        $header = ['No. Empleado', 'Estatus colaborador'];
        foreach (config('report_surveys.higiene.favorable') as [, $fragment]) {
            $header[] = "¿Pregunta: {$fragment}?";
        }
        $header[] = '¿Qué fue lo que más te gustó del curso?';
        $header[] = '¿Qué mejorarías del curso?';
        $header[] = 'En una escala del 0 al 10, ¿qué tan probable es que recomiendes este curso?';
        $survey = $this->makeXlsx([
            $header,
            array_merge([1, 'active'], self::ALL_FAVORABLE, ['Los videos', 'Nada']),
        ], 'encuesta.xlsx');

        return [
            'personal' => new UploadedFile($personnel, 'personal.xlsx', null, null, true),
            'inscripciones' => new UploadedFile($enrollments, 'inscripciones_2026-10-13.xlsx', null, null, true),
            'encuesta' => new UploadedFile($survey, 'encuesta.xlsx', null, null, true),
        ];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['title' => 'Curso: Higiene', 'cut_date' => '2026-10-13', 'survey' => 'higiene', 'course_group_version_id' => $this->version->id], $this->workbooks(), $overrides);
    }

    private function tempIsEmpty(): bool
    {
        return ! is_dir($this->tempRoot) || File::isEmptyDirectory($this->tempRoot);
    }

    private function makeReport(): CourseReport
    {
        $this->actingAs($this->admin)->post(route('course-groups.reports.store', $this->group), $this->payload());

        return CourseReport::latest('id')->firstOrFail();
    }

    public function test_admin_creates_a_cut_and_the_excel_files_are_deleted(): void
    {
        $response = $this->actingAs($this->admin)->post(route('course-groups.reports.store', $this->group), $this->payload());

        $report = CourseReport::firstOrFail();
        $response->assertRedirect(route('course-groups.reports.show', [$this->group, $report]));
        $this->assertSame('2026-10-13', $report->cut_date->toDateString());
        $this->assertSame(2, $report->summary['asignados']);
        $this->assertSame(1, $report->summary['completados']);
        $this->assertSame(['cubo', 'inicio_mes', 'encuesta', 'catalogo', 'preguntas'], array_keys($report->data));
        $this->assertSame($this->admin->id, $report->created_by);
        $this->assertSame($this->version->id, $report->course_group_version_id);
        $this->assertTrue($this->tempIsEmpty());

        // Solo agregados: ni nombres ni números de empleado en lo guardado.
        $this->assertStringNotContainsString('"A"', json_encode($report->data));

        $this->get(route('course-groups.reports.show', [$this->group, $report]))->assertOk()->assertSee('Curso: Higiene');
        $this->get(route('course-groups.versions.show', [$this->group, $this->version, 'seccion' => 'reportes']))->assertOk()->assertSee('13/10/2026')->assertSee('Cortes del curso');
    }

    /**
     * 2026-10-21 (producción): en Fundamentos de Seguridad el corte se detenía con «Falta la columna
     * «riesgos invisibles»». "Automática" elige la encuesta por el código de la versión.
     */
    public function test_automatic_survey_uses_the_course_of_the_version(): void
    {
        $this->version->update(['catalog_code' => 'NRM-SEG-002-EL-26']);
        $files = $this->workbooks();
        $header = ['No. Empleado', 'Estatus colaborador'];
        foreach (config('report_surveys.seguridad.favorable') as [, $fragment]) {
            $header[] = "¿Pregunta: {$fragment}?";
        }
        array_push($header, 'En una escala del 0 al 10, ¿qué tan probable es…?', '¿Qué fue lo que más te gustó del curso?', '¿Qué mejorarías del curso?');
        $survey = $this->makeXlsx([$header, [1, 'active', 'De acuerdo', 'Sí', 'Muy claros y aplicables', 'Sí, muchas', 'Sí', 'Fácil', 'Útiles', 'Si', 10, 'Todo', 'Nada']], 'encuesta.xlsx');
        $files['encuesta'] = new UploadedFile($survey, 'encuesta.xlsx', null, null, true);

        $this->actingAs($this->admin)->get(route('course-groups.reports.create', $this->group))->assertOk()->assertSee('Automática (según el curso)');
        $this->actingAs($this->admin)->post(route('course-groups.reports.store', $this->group), $this->payload(['survey' => 'auto'] + $files))
            ->assertSessionHas('success', 'Corte creado con la encuesta «Fundamentos de Seguridad». Revisa los avisos.');

        $report = CourseReport::firstOrFail();
        $this->assertSame('seguridad', $report->survey);
        $this->assertSame(100.0, (float) $report->summary['satisfaccion']);
        $this->assertContains(['f_fundamentos', 'Entienden los fundamentos'], $report->data['preguntas']['rows']);
        $this->assertTrue($this->tempIsEmpty());
    }

    public function test_a_wrong_file_shows_a_clear_error_and_still_deletes_the_upload(): void
    {
        $files = $this->workbooks(1, ['Nombre', 'Unidad de negocio', 'Departamento*', 'Puesto']); // sin número de empleado

        $this->actingAs($this->admin)
            ->from(route('course-groups.reports.create', $this->group))
            ->post(route('course-groups.reports.store', $this->group), $this->payload($files))
            ->assertRedirect(route('course-groups.reports.create', $this->group))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Falta la columna'));

        $this->assertSame(0, CourseReport::count());
        $this->assertTrue($this->tempIsEmpty());
    }

    /** Pedido 2026-10-22: el error dice qué archivo falla (con su nombre) y por qué. */
    public function test_a_file_in_the_wrong_place_says_which_one_and_why(): void
    {
        $files = $this->workbooks();
        // Una copia del de inscripciones en el lugar del de personal.
        $copy = $files['inscripciones']->getPathname().'-copia';
        copy($files['inscripciones']->getPathname(), $copy);
        $files['personal'] = new UploadedFile($copy, 'inscripciones_2026-10-13.xlsx', null, null, true);

        $this->actingAs($this->admin)->post(route('course-groups.reports.store', $this->group), $this->payload($files))
            ->assertSessionHas('error', 'Falta la columna «No. de empleado» en el archivo «reporte de personal». Parece el reporte de inscripciones: '
                .'¿se subió en el lugar del reporte de personal? Archivo subido: «inscripciones_2026-10-13.xlsx».');

        $this->assertSame(0, CourseReport::count());
        $this->assertTrue($this->tempIsEmpty());
    }

    public function test_an_unexpected_error_shows_a_code_to_find_it_in_the_log(): void
    {
        $this->mock(CourseReportManager::class)->shouldReceive('create')->andThrow(new \RuntimeException('boom'));
        Log::spy();

        $this->actingAs($this->admin)->post(route('course-groups.reports.store', $this->group), $this->payload())
            ->assertSessionHas('error', fn ($m) => (bool) preg_match('/error inesperado .* comparte este código con soporte: [A-Z0-9]{6}\./', $m));

        Log::shouldHaveReceived('error')->withArgs(fn ($what, $context) => strlen($context['codigo'] ?? '') === 6)->once();
    }

    public function test_a_new_cut_needs_a_version_of_its_own_folder(): void
    {
        $other = CourseGroup::create(['name' => 'Otra'])->versions()->create(['year' => 2026, 'market_id' => Market::where('code', 'MX')->value('id')]);

        $this->actingAs($this->admin)->post(route('course-groups.reports.store', $this->group), $this->payload(['course_group_version_id' => '']))
            ->assertSessionHasErrors('course_group_version_id');
        $this->actingAs($this->admin)->post(route('course-groups.reports.store', $this->group), $this->payload(['course_group_version_id' => $other->id]))
            ->assertSessionHasErrors('course_group_version_id');

        $this->assertSame(0, CourseReport::count());
    }

    public function test_validation_requires_the_three_xlsx_files(): void
    {
        $payload = $this->payload(['encuesta' => UploadedFile::fake()->create('encuesta.csv', 5)]);
        unset($payload['personal']);

        $this->actingAs($this->admin)
            ->post(route('course-groups.reports.store', $this->group), $payload)
            ->assertSessionHasErrors(['personal', 'encuesta']);

        $this->actingAs($this->admin)
            ->post(route('course-groups.reports.store', $this->group), $this->payload(['cut_date' => now()->addDays(3)->toDateString()]))
            ->assertSessionHasErrors('cut_date');
    }

    public function test_users_without_a_panel_role_and_guests_cannot_use_reports(): void
    {
        $report = $this->makeReport();
        $user = $this->userWithRole('user');

        $this->actingAs($user)->get(route('course-groups.reports.show', [$this->group, $report]))->assertForbidden();
        $this->actingAs($user)->post(route('course-groups.reports.store', $this->group), $this->payload())->assertForbidden();
        $this->assertSame(1, CourseReport::count());

        auth()->logout();
        $this->get(route('course-groups.reports.show', [$this->group, $report]))->assertRedirect(route('login'));
    }

    public function test_a_report_from_another_folder_is_not_found(): void
    {
        $report = $this->makeReport();
        $other = CourseGroup::create(['name' => 'Otra carpeta']);

        $this->actingAs($this->admin)->get(route('course-groups.reports.show', [$other, $report]))->assertNotFound();
    }

    public function test_update_title_and_date_without_files_keeps_the_data(): void
    {
        $report = $this->makeReport();
        $data = $report->data;

        $this->actingAs($this->admin)
            ->put(route('course-groups.reports.update', [$this->group, $report]), ['title' => 'Corte octubre', 'cut_date' => '2026-10-10'])
            ->assertRedirect(route('course-groups.reports.show', [$this->group, $report]));

        $report->refresh();
        $this->assertSame(['Corte octubre', '2026-10-10'], [$report->title, $report->cut_date->toDateString()]);
        $this->assertSame($data, $report->data);
    }

    public function test_update_with_the_three_files_recalculates_the_same_cut(): void
    {
        $report = $this->makeReport();

        $this->actingAs($this->admin)
            ->put(route('course-groups.reports.update', [$this->group, $report]), ['title' => 'Corte', 'cut_date' => '2026-10-13'] + $this->workbooks(2))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, CourseReport::count());
        $this->assertSame(2, $report->refresh()->summary['completados']);
        $this->assertTrue($this->tempIsEmpty());

        // Un solo archivo no basta: se piden los tres.
        $one = $this->workbooks(2);
        $this->actingAs($this->admin)
            ->put(route('course-groups.reports.update', [$this->group, $report]), ['title' => 'Corte', 'cut_date' => '2026-10-13', 'personal' => $one['personal']])
            ->assertSessionHasErrors(['inscripciones', 'encuesta']);
    }

    public function test_share_link_enable_regenerate_and_disable(): void
    {
        $report = $this->makeReport();
        $share = fn (string $action) => $this->actingAs($this->admin)
            ->patch(route('course-groups.reports.share', [$this->group, $report]), ['action' => $action]);

        $share('enable')->assertSessionHas('success');
        $first = $report->refresh()->shareUrl();
        $this->assertNotNull($first);

        auth()->logout();
        $this->get($first)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('noindex', false)
            ->assertDontSee('Eliminar corte')
            ->assertDontSee('Actualizar corte');

        $share('regenerate');
        $second = $report->refresh()->shareUrl();
        $this->assertNotSame($first, $second);
        auth()->logout();
        $this->get($first)->assertNotFound();
        $this->get($second)->assertOk();

        $share('disable');
        $this->assertNull($report->refresh()->share_token);
        auth()->logout();
        $this->get($second)->assertNotFound();
    }

    public function test_pending_comments_can_be_classified_by_hand(): void
    {
        $report = $this->makeReport();
        // "Los videos" ya trae tema: no cuenta como "sin tema", pero se puede corregir en "Con tema".
        $this->assertSame(0, $report->pendingCount());

        $this->actingAs($this->admin)
            ->get(route('course-groups.reports.pending', [$this->group, $report, 'ver' => 'con-tema']))
            ->assertOk()
            ->assertSee('Los videos')->assertSee('Guardar esta página')->assertDontSee('Revisado');

        $code = config('report_surveys.higiene.catalog')[0][0];
        $this->actingAs($this->admin)
            ->patch(route('course-groups.reports.classify', [$this->group, $report]), ['codes' => [0 => [$code, 'v_respondio', 'inventado']], 'shown' => [0]])
            ->assertSessionHas('success', 'Listo: 1 comentario guardado. Ya se ven en «Opiniones» del reporte.');

        $report->refresh();
        $columns = $report->data['encuesta']['columns'];
        $row = array_combine($columns, $report->data['encuesta']['rows'][0]);

        $this->assertNull($report->pending);
        $this->assertSame(1, $row[$code]);
        $this->assertSame(1, $row['v_respondio']); // no se toca a mano
        $this->assertSame(1, array_sum(array_map(fn ($c) => preg_match('/^(m\d+|v\d+|v_todo)$/', $c) ? $row[$c] : 0, $columns)));
    }

    /**
     * Rediseño 2026-10-23 (en producción nadie entendía "Revisado" y las casillas de temas no salían
     * para las encuestas sin catálogo propio): vacíos fuera, pestañas Sin tema / Con tema, temas del corte.
     */
    public function test_review_shows_only_comments_with_text_and_the_topics_of_the_cut(): void
    {
        config(['report_surveys.higiene.course_codes' => []]);
        $files = $this->workbooks();
        $header = ['No. Empleado', 'Estatus colaborador', '¿Recomendarías este curso?', '¿Qué fue lo que más te gustó del curso?', '¿Qué mejorarías del curso?'];
        $survey = $this->makeXlsx([$header,
            [1, 'active', 'Sí', 'Los videos', 'Nada'],          // con tema
            [2, 'active', 'Sí', 'Saber a quién recurrir', ''],  // sin tema
            [2, 'active', 'Sí', 'N/A', '.'],                    // vacío: no aparece
        ], 'encuesta.xlsx');
        $files['encuesta'] = new UploadedFile($survey, 'encuesta.xlsx', null, null, true);
        $this->actingAs($this->admin)->post(route('course-groups.reports.store', $this->group), $this->payload(['survey' => 'general'] + $files));
        $report = CourseReport::firstOrFail();

        $this->assertSame(1, $report->pendingCount());
        $this->assertSame(2, count($report->pending));
        $this->assertStringContainsString('2 comentarios con texto', implode(' ', $report->warnings));

        // Abre en "Sin tema", con las casillas del catálogo base (antes no salía ninguna).
        $this->actingAs($this->admin)->get(route('course-groups.reports.pending', [$this->group, $report]))
            ->assertOk()->assertSee('Saber a quién recurrir')->assertDontSee('Los videos')->assertDontSee('mera-pending-a">N/A', false)
            ->assertSee(config('mera.progress.survey_topics')[0][2])->assertSeeInOrder(['Sin tema', '1', 'Con tema', '1']);
        $this->actingAs($this->admin)->get(route('course-groups.reports.show', [$this->group, $report]))->assertSee('1 sin tema');

        $index = array_key_first($report->reviewQueue()['sin-tema']);
        $this->actingAs($this->admin)->patch(route('course-groups.reports.classify', [$this->group, $report]), ['codes' => [$index => ['v08']], 'shown' => [$index], 'ver' => 'sin-tema'])
            ->assertRedirect(route('course-groups.reports.pending', [$this->group, $report, 'ver' => 'sin-tema']));

        $report->refresh();
        $this->assertSame(0, $report->pendingCount());
        $this->assertSame(1, count($report->pending)); // queda el que ya tenía tema

        // Corte anterior (como los de producción): sin "sin_tema" y con vacíos en la lista. Al abrirlo se corrige.
        $summary = $report->summary;
        unset($summary['sin_tema']);
        $report->forceFill(['summary' => $summary, 'pending' => array_merge($report->pending, [['row' => 0, 'liked' => '.', 'improve' => 'N/A']])])->save();
        $this->actingAs($this->admin)->get(route('course-groups.reports.show', [$this->group, $report]))->assertOk();
        $report->refresh();
        $this->assertSame([1, 0, 1], [count($report->pending), $report->summary['sin_tema'], $report->summary['pendientes']]);
    }

    public function test_the_report_page_mounts_the_dashboard_with_its_data(): void
    {
        $report = $this->makeReport();

        $this->actingAs($this->admin)->get(route('course-groups.reports.show', [$this->group, $report]))
            ->assertOk()
            ->assertSee('data-course-report', false)
            ->assertSee('id="report-data"', false)
            ->assertSee('Ranking de localidades')
            ->assertSee('Descargar HTML');
    }

    /**
     * 2026-10-23 ("demasiada información en una sola vista"): pestañas, acciones secundarias en
     * "Más acciones" y avisos de rutina plegados (los que piden atención se abren solos).
     */
    public function test_the_report_page_is_organized_in_tabs_and_folded_panels(): void
    {
        $report = $this->makeReport();
        $page = fn () => $this->actingAs($this->admin)->get(route('course-groups.reports.show', [$this->group, $report]))->assertOk();

        $page()->assertSeeInOrder(['Más acciones', 'Actualizar corte', 'Descargar HTML', 'Compartir sin sesión', 'Eliminar corte'])
            ->assertSeeInOrder(['data-tab="resumen"', 'data-tab="avance"', 'data-tab="evaluacion"', 'data-tab="opiniones"'], false)
            ->assertSeeInOrder(['data-panel="resumen"', 'Ranking de localidades', 'data-panel="avance"', 'Avance por mes', 'data-panel="evaluacion"', 'Calificaciones', 'data-panel="opiniones"', 'Lo que más valoran'], false);

        $report->forceFill(['warnings' => ['3 inscritos de unidades excluidas o sin unidad (se omiten).']])->save();
        $page()->assertSee('Informativos: nada que corregir.')->assertDontSee('mera-report-panel is-warning" open', false);

        $report->forceFill(['warnings' => ['La encuesta no trae la pregunta «riesgos invisibles»: la satisfacción se calcula con las otras 7.']])->save();
        $page()->assertSee('mera-report-panel is-warning" open', false)->assertDontSee('Informativos: nada que corregir.');
    }

    public function test_download_html_is_a_standalone_file_with_only_aggregates(): void
    {
        $report = $this->makeReport();

        $response = $this->actingAs($this->admin)->get(route('course-groups.reports.download', [$this->group, $report]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="reporte-higiene-en-cocina-2026-10-13.html"');
        $html = $response->getContent();
        $this->assertStringContainsString('<!doctype html>', $html);
        $this->assertStringContainsString('id="report-data"', $html);
        $this->assertStringContainsString('Corte al 13/10/2026', $html);
        $this->assertStringNotContainsString('/build/assets/', $html); // nada por fuera: todo incrustado
        $this->assertStringNotContainsString('"A"', $html);            // ni nombres de personas

        $this->actingAs($this->userWithRole('user'))
            ->get(route('course-groups.reports.download', [$this->group, $report]))
            ->assertForbidden();
    }

    public function test_an_empty_cut_date_uses_today_in_mexico_city(): void
    {
        $this->travelTo(now('America/Mexico_City')->setTime(23, 30)); // ya es "mañana" en UTC

        $this->actingAs($this->admin)
            ->post(route('course-groups.reports.store', $this->group), $this->payload(['cut_date' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            now(config('mera.display_timezone'))->toDateString(),
            CourseReport::firstOrFail()->cut_date->toDateString(),
        );
    }

    public function test_abandoned_uploads_are_purged_but_recent_ones_are_kept(): void
    {
        $old = $this->tempRoot.DIRECTORY_SEPARATOR.'abandonada';
        $recent = $this->tempRoot.DIRECTORY_SEPARATOR.'en-curso';
        File::ensureDirectoryExists($old);
        File::ensureDirectoryExists($recent);
        File::put($old.DIRECTORY_SEPARATOR.'personal.xlsx', 'x');
        touch($old, now()->subHours(2)->getTimestamp());

        $this->artisan('mera:purge-report-uploads')->assertSuccessful();

        $this->assertDirectoryDoesNotExist($old);
        $this->assertDirectoryExists($recent);
    }

    public function test_deleting_a_cut_keeps_the_others_and_the_folder(): void
    {
        $report = $this->makeReport();
        $other = $this->makeReport();

        $this->actingAs($this->admin)
            ->delete(route('course-groups.reports.destroy', [$this->group, $report]))
            ->assertRedirect(route('course-groups.versions.show', [$this->group, $this->version, 'seccion' => 'reportes']));

        $this->assertModelMissing($report);
        $this->assertModelExists($other);
        $this->assertModelExists($this->group);
    }
}
