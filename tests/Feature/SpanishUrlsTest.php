<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * URLs en español (pedido del usuario 2026-10-20) y 301 desde las anteriores en inglés
 * (App\Support\LegacyPaths). Excepción: /api/courses (contrato con el WordPress).
 */
class SpanishUrlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_urls_are_in_spanish(): void
    {
        $this->assertSame([
            '/iniciar-sesion', '/panel', '/perfil', '/usuarios/nuevo', '/modulos', '/carpetas',
            '/historial', '/permisos', '/catalogo-cursos', '/recuperar-contrasena',
        ], array_map(fn ($name) => parse_url(route($name), PHP_URL_PATH), [
            'login', 'dashboard', 'profile.edit', 'users.create', 'courses.index', 'course-groups.index',
            'activity.logs.index', 'permissions.index', 'course-catalog.index', 'password.request',
        ]));

        $this->assertStringEndsWith('/modulos/7/editar', route('courses.edit', 7));
        $this->assertStringEndsWith('/restablecer-contrasena/abc', route('password.reset', 'abc'));
    }

    public function test_no_panel_url_keeps_an_english_segment(): void
    {
        $english = '#(^|/)(login|logout|dashboard|profile|users|courses|course-groups|categories|permissions|activity-logs|create|edit|move|invite|password|forgot-password|reset-password)(/|$)#';

        $offenders = collect(Route::getRoutes()->getRoutes())
            ->reject(fn ($r) => str_contains($r->uri(), '{rest?}')) // redirecciones de las URLs anteriores
            ->reject(fn ($r) => str_starts_with($r->uri(), 'api/') || str_starts_with($r->uri(), '_ignition') || str_starts_with($r->uri(), 'sanctum'))
            ->filter(fn ($r) => preg_match($english, $r->uri()))
            ->map(fn ($r) => $r->uri())
            ->values()->all();

        $this->assertSame([], $offenders);
    }

    public function test_old_english_urls_redirect_to_spanish(): void
    {
        $this->get('/login')->assertStatus(301)->assertRedirect('/iniciar-sesion');
        $this->get('/reset-password/token123?email=a%40b.com')->assertStatus(301)->assertRedirect('/restablecer-contrasena/token123?email=a%40b.com');
        $this->get('/courses/5/edit')->assertRedirect('/modulos/5/editar');
        $this->get('/course-groups/higiene')->assertRedirect('/carpetas/higiene');
        $this->get('/dashboard')->assertRedirect('/panel');
        $this->get('/')->assertRedirect('/iniciar-sesion');

        // Las páginas en español sí responden.
        Role::findOrCreate(User::ROLE_ADMIN, 'web');
        $admin = tap(User::factory()->create())->assignRole(User::ROLE_ADMIN);
        $course = Course::factory()->create();
        $this->actingAs($admin)->get('/modulos/'.$course->id.'/editar')->assertOk();
        $this->actingAs($admin)->get('/panel')->assertOk();

        // La API del WordPress no cambia.
        $this->get('/api/courses')->assertOk();
    }
}
