<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\CourseGroup;
use App\Models\CourseGroupVersion;
use App\Models\CourseReport;
use App\Models\Market;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Cintillo "Entorno de pruebas" y "Apagar todo lo compartido" (App\Support\TestEnvironment).
 */
class TestEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate(User::ROLE_ADMIN, 'web');
        $this->admin = tap(User::factory()->create())->assignRole(User::ROLE_ADMIN);
    }

    /** @return array{0: CourseGroupVersion, 1: CourseReport} */
    private function sharedContent(): array
    {
        $group = CourseGroup::create(['name' => 'Higiene']);
        $version = $group->versions()->create(['year' => 2026, 'market_id' => Market::where('code', 'MX')->value('id')]);
        $version->forceFill(['is_published' => true, 'published_at' => now()])->save();

        $report = CourseReport::forceCreate([
            'course_group_id' => $group->id,
            'course_group_version_id' => $version->id,
            'title' => 'Corte',
            'cut_date' => '2026-10-13',
            'survey' => 'higiene',
            'data' => [],
            'share_token' => Str::random(48),
            'shared_at' => now(),
        ]);

        return [$version, $report];
    }

    public function test_every_page_shows_the_banner_outside_production(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Entorno de pruebas')->assertSee('cursos.meracorporation.com');
        $this->get(route('catalog.index'))->assertOk()->assertSee('Entorno de pruebas');
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Entorno de pruebas')->assertSee('Apagar todo lo compartido')->assertSee('nada activo');
    }

    public function test_production_has_no_banner_and_no_unshare_route(): void
    {
        $this->app['env'] = 'production';

        $this->get(route('login'))->assertOk()->assertDontSee('Entorno de pruebas');
        // Con env = production el token CSRF ya no se omite en pruebas.
        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->actingAs($this->admin)->post(route('test-environment.unshare'))->assertNotFound();
    }

    public function test_unshare_turns_off_every_page_and_report_link(): void
    {
        [$version, $report] = $this->sharedContent();
        $token = $report->share_token;

        $this->get(route('reports.shared', $token))->assertOk();
        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('(2 activos)');

        $this->actingAs($this->admin)->from(route('dashboard'))->post(route('test-environment.unshare'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 página apagada') && str_contains($m, '1 link de reporte desactivado'));

        $this->assertFalse($version->fresh()->is_published);
        $this->assertNull($report->fresh()->share_token);

        auth()->logout();
        $this->get(route('reports.shared', $token))->assertNotFound();
        $this->get('/'.$version->group->slug)->assertNotFound();
    }

    public function test_only_panel_users_can_unshare(): void
    {
        $this->sharedContent();

        $this->post(route('test-environment.unshare'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post(route('test-environment.unshare'))->assertForbidden();
        $this->assertSame(1, CourseGroupVersion::where('is_published', true)->count());
    }
}
