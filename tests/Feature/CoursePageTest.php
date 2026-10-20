<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\CourseGroupVersion;
use App\Models\Market;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCourseStorage;
use Tests\TestCase;

/**
 * Página pública de cada carpeta, por versión (docs/carpetas/INSTRUCTIVO_VERSIONES.md §5):
 * /<carpeta>/<año>/<versión> (el link que se comparte; sin selector de versión ni de año),
 * /<carpeta> y /<carpeta>/<año> redirigen ahí; apagada hasta publicarla.
 */
class CoursePageTest extends TestCase
{
    use InteractsWithCourseStorage, RefreshDatabase;

    private User $admin;

    private CourseGroup $group;

    private CourseGroupVersion $mx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->userWithRole(User::ROLE_ADMIN);

        $this->group = CourseGroup::create(['name' => 'Higiene en cocina']);
        $this->mx = $this->version(2026, 'MX', [
            'evaluation_url' => 'https://forms.cloud.microsoft/r/EVAL-ES',
            'survey_url' => 'https://forms.cloud.microsoft/r/SURVEY-ES',
        ]);

        $this->module($this->mx, 'Módulo 2 · Cocina', 'cursos/cocina-es', 2);
        $this->module($this->mx, 'Módulo 1 · Recepción', 'cursos/recepcion-es', 1);
    }

    private function version(int $year, string $code, array $attributes = []): CourseGroupVersion
    {
        $version = $this->group->versions()->firstOrCreate(['year' => $year, 'market_id' => Market::where('code', $code)->value('id')]);
        $version->fill($attributes)->save();

        return $version;
    }

    private function module(CourseGroupVersion $version, string $name, string $path, int $position): Course
    {
        return Course::factory()->create([
            'name' => $name, 'path' => $path, 'position' => $position,
            'course_group_id' => $this->group->id, 'course_group_version_id' => $version->id, 'language' => $version->language(),
        ]);
    }

    private function publish(CourseGroupVersion $version): void
    {
        $version->forceFill(['is_published' => true])->save();
    }

    public function test_new_pages_are_born_unpublished_and_hidden_from_the_public(): void
    {
        $this->assertFalse($this->mx->is_published);

        $this->get('/higiene-en-cocina')->assertNotFound();
        $this->get('/higiene-en-cocina/2026')->assertNotFound();
        $this->get('/higiene-en-cocina/2026/mexico')->assertNotFound();
    }

    public function test_admins_see_a_preview_while_unpublished(): void
    {
        $this->actingAs($this->admin)->get('/higiene-en-cocina/2026/mexico')
            ->assertOk()
            ->assertSee('Vista previa.')
            ->assertSee('noindex, nofollow', false)
            ->assertSee('Higiene en cocina')
            ->assertSee(route('course-groups.versions.show', [$this->group, $this->mx, 'seccion' => 'link']), false);
    }

    public function test_published_page_shows_ordered_modules_and_forms(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('course-groups.versions.publish', [$this->group, $this->mx]), ['published' => 1])
            ->assertSessionHas('success');

        auth()->logout();

        $html = $this->get('/higiene-en-cocina/2026/mexico')
            ->assertOk()
            ->assertDontSee('Vista previa.')
            ->assertSee('<html lang="es">', false)
            ->assertSee('Curso en línea · 2026 · México')
            ->assertSee('Paso 1 · Tus 2 módulos')
            ->assertSee('https://forms.cloud.microsoft/r/EVAL-ES', false)
            ->assertSee('https://forms.cloud.microsoft/r/SURVEY-ES', false)
            ->assertSee('data-program-progress="carpeta:higiene-en-cocina:2026:mexico"', false)
            ->assertSee('data-progress-legacy="carpeta:higiene-en-cocina:es"', false) // conserva el avance anterior
            ->getContent();

        $this->assertLessThan(strpos($html, 'Módulo 2 · Cocina'), strpos($html, 'Módulo 1 · Recepción'));
        $this->assertStringContainsString('/storage/cursos/recepcion-es/index.html', $html);
        $this->assertNotNull($this->mx->fresh()->published_at);
        $this->assertStringEndsWith('/higiene-en-cocina/2026/mexico', $this->mx->publicUrl());
    }

    public function test_the_shared_link_shows_only_its_version_and_year(): void
    {
        $this->publish($this->mx);
        $us = $this->version(2026, 'US', ['title' => 'Kitchen hygiene', 'evaluation_url' => 'https://forms.cloud.microsoft/r/EVAL-EN']);
        $this->module($us, 'Module 1 · Receiving', 'cursos/receiving-en', 1);
        $this->publish($us);
        $old = $this->version(2025, 'MX');
        $this->module($old, 'Módulo de 2025', 'cursos/viejo', 1);
        $this->publish($old);

        $this->get('/higiene-en-cocina/2026/eua')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Kitchen hygiene')
            ->assertSee('Open module')
            ->assertSee('Module 1 · Receiving')
            ->assertDontSee('Módulo 1 · Recepción')
            ->assertSee('https://forms.cloud.microsoft/r/EVAL-EN', false);

        // Sin selector ni hreflang: desde el link no se llega a otras versiones ni años.
        $this->get('/higiene-en-cocina/2026/mexico')
            ->assertOk()
            ->assertDontSee('hreflang', false)
            ->assertDontSee('/higiene-en-cocina/2026/eua', false)
            ->assertDontSee('/higiene-en-cocina/2025/mexico', false);

        $this->get('/higiene-en-cocina/2025/mexico')->assertOk()->assertSee('Módulo de 2025')->assertDontSee('Recepción');
        $this->get('/higiene-en-cocina/2024/mexico')->assertNotFound();
        $this->get('/higiene-en-cocina/2026/no-existe')->assertNotFound();
        $this->assertStringEndsWith('/higiene-en-cocina/2025/mexico', $old->publicUrl());
    }

    public function test_short_links_redirect_to_the_latest_year_and_default_version(): void
    {
        $this->publish($this->mx);
        $old = $this->version(2025, 'MX');
        $this->module($old, 'Módulo de 2025', 'cursos/viejo', 1);
        $this->publish($old);

        $this->get('/higiene-en-cocina')->assertStatus(301)->assertRedirect('/higiene-en-cocina/2026/mexico');
        $this->get('/higiene-en-cocina/2025')->assertStatus(301)->assertRedirect('/higiene-en-cocina/2025/mexico');

        // Si el año no tiene México, la primera versión visible.
        $us = $this->version(2027, 'US');
        $this->module($us, 'Module 2027', 'cursos/m2027', 1);
        $this->publish($us);
        $this->get('/higiene-en-cocina/2027')->assertRedirect('/higiene-en-cocina/2027/eua');
    }

    public function test_a_child_version_without_modules_shows_its_parent(): void
    {
        $latam = $this->version(2026, 'LATAM');
        $this->module($latam, 'Módulo Latam', 'cursos/latam', 1);
        $this->publish($latam);
        Market::create(['code' => 'PA', 'name' => 'Panamá', 'slug' => 'panama', 'language' => 'es', 'parent_id' => Market::where('code', 'LATAM')->value('id')]);

        $this->get('/higiene-en-cocina/2026/panama')
            ->assertOk()
            ->assertSee('Módulo Latam')
            ->assertSee('Se muestra la versión Latam.');
    }

    public function test_old_english_links_redirect_to_the_latest_eua_version(): void
    {
        $this->get('/en/higiene-en-cocina')->assertNotFound(); // sin versión EUA visible

        $us = $this->version(2026, 'US');
        $this->module($us, 'Module 1', 'cursos/m1', 1);
        $this->publish($us);

        $this->get('/higiene-en-cocina/en')->assertStatus(301)->assertRedirect('/higiene-en-cocina/2026/eua');
        $this->get('/en/higiene-en-cocina')->assertStatus(301)->assertRedirect('/higiene-en-cocina/2026/eua');
    }

    public function test_cannot_publish_a_version_without_modules(): void
    {
        $empty = $this->version(2026, 'LATAM');

        $this->actingAs($this->admin)->patch(route('course-groups.versions.publish', [$this->group, $empty]), ['published' => 1])
            ->assertSessionHas('error');

        $this->assertFalse($empty->fresh()->is_published);
    }

    public function test_unpublishing_hides_it_again(): void
    {
        $this->publish($this->mx);
        $this->get('/higiene-en-cocina/2026/mexico')->assertOk();

        $this->actingAs($this->admin)->patch(route('course-groups.versions.publish', [$this->group, $this->mx]), ['published' => 0]);
        auth()->logout();

        $this->get('/higiene-en-cocina/2026/mexico')->assertNotFound();
        $this->get('/higiene-en-cocina')->assertNotFound();
    }

    public function test_renaming_keeps_the_link_but_it_can_be_edited_explicitly(): void
    {
        $this->actingAs($this->admin)->put(route('course-groups.update', $this->group), [
            'name' => 'Higiene en cocina 2026',
            'slug' => 'higiene-en-cocina',
        ])->assertRedirect();

        $this->assertSame('higiene-en-cocina', $this->group->fresh()->slug);

        $this->actingAs($this->admin)->put(route('course-groups.update', $this->group->fresh()), [
            'name' => 'Higiene en cocina 2026',
            'slug' => 'alimentos-2026',
        ])->assertRedirect();

        $this->assertSame('alimentos-2026', $this->group->fresh()->slug);
    }

    public function test_reserved_or_invalid_links_are_rejected(): void
    {
        foreach (['login', 'cursos', 'dashboard', 'storage', 'images', 'proceso-de-manipulacion-de-alimentos', 'mexico', 'eua', 'Con Espacios', 'acentuación'] as $slug) {
            $this->actingAs($this->admin)->put(route('course-groups.update', $this->group), [
                'name' => $this->group->name,
                'slug' => $slug,
            ])->assertSessionHasErrors('slug');
        }

        // Al crear sin link, uno reservado se evita solo.
        $this->actingAs($this->admin)->post(route('course-groups.store'), ['name' => 'Login']);
        $this->assertSame('login-2', CourseGroup::where('name', 'Login')->value('slug'));
    }

    public function test_existing_routes_still_win_over_folder_pages(): void
    {
        $this->get('/iniciar-sesion')->assertOk()->assertSee('Iniciar sesión');
        $this->get('/cursos')->assertOk();
        $this->get('/proceso-de-manipulacion-de-alimentos')->assertOk()->assertSee('Proceso de manipulación de alimentos');
    }

    public function test_version_form_urls_must_be_https_and_codes_follow_the_catalog(): void
    {
        $this->actingAs($this->admin)->put(route('course-groups.versions.update', [$this->group, $this->mx]), [
            'evaluation_url' => 'javascript:alert(1)',
            'survey_url' => 'http://inseguro.example.com',
            'catalog_code' => 'HIGIENE-2026',
        ])->assertSessionHasErrors(['evaluation_url', 'survey_url', 'catalog_code']);

        $this->actingAs($this->admin)->put(route('course-groups.versions.update', [$this->group, $this->mx]), [
            'catalog_code' => 'nrm-hig-002-el-26',
            'title' => 'Introducción a la Higiene',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['NRM-HIG-002-EL-26', 'Introducción a la Higiene'], [$this->mx->fresh()->catalog_code, $this->mx->fresh()->title]);
    }
}
