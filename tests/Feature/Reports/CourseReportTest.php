<?php

namespace Tests\Feature\Reports;

use App\Models\CourseGroup;
use App\Models\CourseReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
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
        return array_merge(['title' => 'Curso: Higiene', 'cut_date' => '2026-10-13', 'survey' => 'higiene'], $this->workbooks(), $overrides);
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
        $this->assertSame(['cubo', 'inicio_mes', 'encuesta', 'catalogo'], array_keys($report->data));
        $this->assertSame($this->admin->id, $report->created_by);
        $this->assertTrue($this->tempIsEmpty());

        // Solo agregados: ni nombres ni números de empleado en lo guardado.
        $this->assertStringNotContainsString('"A"', json_encode($report->data));

        $this->get(route('course-groups.reports.show', [$this->group, $report]))->assertOk()->assertSee('Curso: Higiene');
        $this->get(route('course-groups.show', $this->group))->assertOk()->assertSee('13/10/2026');
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
        $this->assertSame(1, $report->pendingCount());

        $this->actingAs($this->admin)
            ->get(route('course-groups.reports.pending', [$this->group, $report]))
            ->assertOk()
            ->assertSee('Los videos');

        $code = config('report_surveys.higiene.catalog')[0][0];
        $this->actingAs($this->admin)
            ->patch(route('course-groups.reports.classify', [$this->group, $report]), ['codes' => [0 => [$code, 'v_respondio', 'inventado']], 'reviewed' => [0]])
            ->assertSessionHas('success');

        $report->refresh();
        $columns = $report->data['encuesta']['columns'];
        $row = array_combine($columns, $report->data['encuesta']['rows'][0]);

        $this->assertSame(0, $report->pendingCount());
        $this->assertNull($report->pending);
        $this->assertSame(1, $row[$code]);
        $this->assertSame(1, $row['v_respondio']); // no se toca a mano
        $this->assertSame(1, array_sum(array_map(fn ($c) => preg_match('/^(m\d+|v\d+|v_todo)$/', $c) ? $row[$c] : 0, $columns)));
    }

    public function test_deleting_a_cut_keeps_the_others_and_the_folder(): void
    {
        $report = $this->makeReport();
        $other = $this->makeReport();

        $this->actingAs($this->admin)
            ->delete(route('course-groups.reports.destroy', [$this->group, $report]))
            ->assertRedirect(route('course-groups.show', $this->group));

        $this->assertModelMissing($report);
        $this->assertModelExists($other);
        $this->assertModelExists($this->group);
    }
}
