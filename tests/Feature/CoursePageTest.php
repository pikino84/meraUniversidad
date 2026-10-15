<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCourseStorage;
use Tests\TestCase;

/**
 * Página pública de cada carpeta: /<slug> (es) y /en/<slug>, apagada hasta publicarla.
 */
class CoursePageTest extends TestCase
{
    use InteractsWithCourseStorage, RefreshDatabase;

    private User $admin;

    private CourseGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->userWithRole(User::ROLE_ADMIN);

        $this->group = CourseGroup::create([
            'name' => 'Higiene en cocina',
            'name_en' => 'Kitchen hygiene',
            'evaluation_url_es' => 'https://forms.cloud.microsoft/r/EVAL-ES',
            'survey_url_es' => 'https://forms.cloud.microsoft/r/SURVEY-ES',
        ]);

        Course::factory()->create(['name' => 'Módulo 2 · Cocina', 'path' => 'cursos/cocina-es', 'course_group_id' => $this->group->id, 'language' => 'es', 'position' => 2]);
        Course::factory()->create(['name' => 'Módulo 1 · Recepción', 'path' => 'cursos/recepcion-es', 'course_group_id' => $this->group->id, 'language' => 'es', 'position' => 1]);
    }

    public function test_new_pages_are_born_unpublished_and_hidden_from_the_public(): void
    {
        $this->assertFalse($this->group->is_published);

        $this->get('/higiene-en-cocina')->assertNotFound();
    }

    public function test_admins_see_a_preview_while_unpublished(): void
    {
        $this->actingAs($this->admin)->get('/higiene-en-cocina')
            ->assertOk()
            ->assertSee('Vista previa.')
            ->assertSee('noindex, nofollow', false)
            ->assertSee('Higiene en cocina');
    }

    public function test_published_page_shows_ordered_modules_and_forms(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('course-groups.publish', $this->group), ['published' => 1])
            ->assertSessionHas('success');

        auth()->logout();

        $html = $this->get('/higiene-en-cocina')
            ->assertOk()
            ->assertDontSee('Vista previa.')
            ->assertSee('<html lang="es">', false)
            ->assertSee('Paso 1 · Tus 2 módulos')
            ->assertSee('https://forms.cloud.microsoft/r/EVAL-ES', false)
            ->assertSee('https://forms.cloud.microsoft/r/SURVEY-ES', false)
            ->assertDontSee('hreflang="en"', false) // sin módulos en inglés no hay selector
            ->getContent();

        $this->assertLessThan(strpos($html, 'Módulo 2 · Cocina'), strpos($html, 'Módulo 1 · Recepción'));
        $this->assertStringContainsString('/storage/cursos/recepcion-es/index.html', $html);
        $this->assertNotNull($this->group->fresh()->published_at);
    }

    public function test_english_uses_en_prefix_its_own_modules_and_falls_back_to_spanish_forms(): void
    {
        $this->group->forceFill(['is_published' => true])->save();

        $this->get('/en/higiene-en-cocina')->assertNotFound(); // sin módulos en inglés

        Course::factory()->create(['name' => 'Module 1 · Receiving', 'path' => 'cursos/receiving-en', 'course_group_id' => $this->group->id, 'language' => 'en', 'position' => 1]);

        $this->get('/en/higiene-en-cocina')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Kitchen hygiene')
            ->assertSee('Open module')
            ->assertSee('Module 1 · Receiving')
            ->assertDontSee('Módulo 1 · Recepción')
            ->assertSee('/storage/cursos/receiving-en/index.html', false)
            ->assertSee('https://forms.cloud.microsoft/r/EVAL-ES', false) // respaldo: sin link propio en inglés
            ->assertSee('hreflang="es"', false);

        $this->group->update(['name' => 'Higiene en cocina', 'evaluation_url_en' => 'https://forms.cloud.microsoft/r/EVAL-EN']);

        $this->get('/en/higiene-en-cocina')->assertSee('https://forms.cloud.microsoft/r/EVAL-EN', false);
        $this->get('/higiene-en-cocina')->assertSee('hreflang="en"', false);
    }

    public function test_old_english_links_redirect_to_the_new_format(): void
    {
        $this->group->update(['is_published' => true]);

        $this->get('/higiene-en-cocina/en')->assertStatus(301)->assertRedirect('/en/higiene-en-cocina');
        $this->assertStringEndsWith('/en/higiene-en-cocina', $this->group->publicUrl('en'));
    }

    public function test_cannot_publish_a_folder_without_modules(): void
    {
        $empty = CourseGroup::create(['name' => 'Vacía']);

        $this->actingAs($this->admin)->patch(route('course-groups.publish', $empty), ['published' => 1])
            ->assertSessionHas('error');

        $this->assertFalse($empty->fresh()->is_published);
    }

    public function test_unpublishing_hides_it_again(): void
    {
        $this->group->forceFill(['is_published' => true])->save();
        $this->get('/higiene-en-cocina')->assertOk();

        $this->actingAs($this->admin)->patch(route('course-groups.publish', $this->group), ['published' => 0]);
        auth()->logout();

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
        foreach (['login', 'cursos', 'dashboard', 'storage', 'images', 'proceso-de-manipulacion-de-alimentos', 'Con Espacios', 'acentuación'] as $slug) {
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
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión');
        $this->get('/cursos')->assertOk();
        $this->get('/proceso-de-manipulacion-de-alimentos')->assertOk()->assertSee('Proceso de manipulación de alimentos');
    }

    public function test_form_urls_must_be_https(): void
    {
        $this->actingAs($this->admin)->put(route('course-groups.update', $this->group), [
            'name' => $this->group->name,
            'slug' => $this->group->slug,
            'evaluation_url_es' => 'javascript:alert(1)',
            'survey_url_en' => 'http://inseguro.example.com',
        ])->assertSessionHasErrors(['evaluation_url_es', 'survey_url_en']);
    }
}
