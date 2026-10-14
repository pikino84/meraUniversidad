<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithCourseStorage;
use Tests\TestCase;

/**
 * Correcciones de la auditoría en navegador (CLAUDE.md §12): F1, U12 y U13.
 */
class AuditFixesTest extends TestCase
{
    use InteractsWithCourseStorage, RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCourseStorage();
        $this->superAdmin = $this->userWithRole(User::ROLE_SUPER_ADMIN);
        $this->admin = $this->userWithRole(User::ROLE_ADMIN);
    }

    private function courseWithFiles(bool $content, bool $cover, string $name = 'Curso'): Course
    {
        $folder = 'curso-'.substr(md5($name), 0, 10);
        $course = Course::factory()->create([
            'name' => $name,
            'path' => "cursos/{$folder}",
            'cover_image' => "cursos/{$folder}-cover-abc123.png",
        ]);

        if ($content) {
            File::ensureDirectoryExists($this->coursesRoot()."/{$folder}");
            File::put($this->coursesRoot()."/{$folder}/index.html", '<!doctype html>');
        }

        if ($cover) {
            File::ensureDirectoryExists($this->coursesRoot());
            File::put($this->coursesRoot()."/{$folder}-cover-abc123.png", 'png');
        }

        return $course;
    }

    // F1 ---------------------------------------------------------------

    public function test_courses_list_flags_missing_files_and_disables_preview(): void
    {
        $this->courseWithFiles(true, true, 'Curso completo');
        $this->courseWithFiles(false, true, 'Curso sin contenido');

        $this->actingAs($this->admin)->get(route('courses.index'))
            ->assertOk()
            ->assertSee('Contenido no encontrado')
            ->assertSee('mera-btn-disabled', false)
            ->assertDontSee('Archivos no encontrados');
    }

    public function test_check_courses_command_reports_missing_files_without_changing_anything(): void
    {
        $this->courseWithFiles(true, true, 'Curso completo');
        $this->courseWithFiles(true, false, 'Curso sin portada');

        $this->artisan('mera:check-courses', ['--only-missing' => true])
            ->expectsOutputToContain('1 de 2 cursos tienen archivos faltantes')
            ->assertSuccessful();

        $this->assertSame(2, Course::count());
    }

    // U14 --------------------------------------------------------------

    public function test_dashboard_highlights_course_folders(): void
    {
        Course::factory()->count(2)->create(['course_group_id' => null]);

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Carpetas de curso')
            ->assertSee('Nueva carpeta')
            ->assertSee('módulos sin carpeta de curso', false)
            ->assertSee(route('courses.index', ['carpeta' => 'sin']), false)
            ->assertDontSee('Nueva categoría');
    }

    // U27 / P10 --------------------------------------------------------

    public function test_catalog_cards_have_one_link_named_after_the_course(): void
    {
        $this->courseWithFiles(true, true, 'Seguridad en cocina');

        $html = $this->get(route('catalog.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<a href="[^"]+/index\.html"[^>]*class="mera-catalog-link">\s*Seguridad en cocina#', $html);
        $this->assertSame(1, substr_count($html, 'class="mera-catalog-link"'));
        $this->assertMatchesRegularExpression('#width="640"\s+height="360"#', $html); // espacio reservado para la portada
    }

    public function test_panel_no_longer_loads_unused_template_assets(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('jquery-ui.min.js', false)
            ->assertDontSee('waves.min', false)
            ->assertDontSee('widget.css', false)
            ->assertDontSee('script.min.js', false)
            ->assertDontSee('onclick="toggleFullScreen()"', false)
            ->assertDontSee('data-fullscreen', false)
            ->assertSee('Ir al inicio')
            ->assertSee('jquery.slimscroll.js', false); // lo necesita pcoded (menú lateral)
    }

    // Auditoría UI 2026-10-14 -------------------------------------------

    public function test_sidebar_is_a_flat_menu_and_confirmations_keep_long_data_out_of_the_title(): void
    {
        $other = $this->userWithRole(User::ROLE_ADMIN);

        $html = $this->actingAs($this->superAdmin)->get(route('users.index'))->assertOk()->getContent();

        // Menú plano por secciones (antes, un acordeón "Administración" escondía todo).
        $this->assertStringNotContainsString('pcoded-hasmenu', $html);
        $this->assertStringContainsString('mera-nav-label', $html);
        $this->assertMatchesRegularExpression('#<li class="active">\s*<a href="[^"]+/users"\s+aria-current="page"#', $html);

        // "Enviar link": título corto, el correo en el cuerpo y tono informativo.
        $this->assertStringContainsString('data-confirm="¿Enviar link para cambiar la contraseña?" data-confirm-strong="'.$other->email.'" data-confirm-tone="info"', $html);

        // Eliminar: tono de peligro y el nombre fuera del título.
        $this->assertStringContainsString('data-confirm="¿Eliminar al usuario?" data-confirm-tone="danger"', $html);
    }

    // Categorías ocultas (decisión del usuario 2026-10-14) ----------------

    public function test_categories_are_hidden_from_panel_and_catalog_by_default(): void
    {
        $category = \App\Models\Category::factory()->create(['name' => 'Higiene']);
        $course = $this->courseWithFiles(true, true, 'Módulo con categoría');
        $course->update(['category_id' => $category->id]);

        $dashboard = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
        $dashboard->assertDontSee(route('categories.index'), false);

        $this->get(route('courses.index'))->assertOk()
            ->assertDontSee('course-filter-category', false)
            ->assertDontSee('>Categoría</th>', false);
        $this->get(route('courses.edit', $course))->assertOk()->assertDontSee('name="category_id"', false);
        $this->get(route('categories.index'))->assertNotFound();

        // Catálogo: sin filtro ni etiqueta de categoría y 12 módulos por página.
        \App\Models\Course::factory()->count(14)->create();
        auth()->logout();
        $html = $this->get(route('catalog.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('catalog-category', $html);
        $this->assertStringNotContainsString('>Higiene<', $html);
        $this->assertSame(12, substr_count($html, 'class="mera-catalog-card"'));

        // La API no cambia (el WordPress usa el nombre de la categoría).
        $this->getJson('/api/courses')->assertJsonFragment(['category' => 'Higiene']);
    }

    public function test_editing_a_module_keeps_its_category_while_they_are_hidden(): void
    {
        $category = \App\Models\Category::factory()->create();
        $course = $this->courseWithFiles(true, true, 'Conserva categoría');
        $course->update(['category_id' => $category->id]);

        $this->actingAs($this->admin)->put(route('courses.update', $course), [
            'name' => 'Conserva categoría (editado)', 'description' => 'Nueva descripción',
        ])->assertSessionHasNoErrors();

        $this->assertSame($category->id, $course->fresh()->category_id);
    }

    public function test_detach_categories_command_frees_modules_and_keeps_a_backup(): void
    {
        $category = \App\Models\Category::factory()->create(['name' => 'Sismos']);
        $with = $this->courseWithFiles(true, true, 'Con categoría');
        $with->update(['category_id' => $category->id]);
        $without = $this->courseWithFiles(true, true, 'Sin categoría');

        $this->artisan('mera:detach-categories', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame($category->id, $with->fresh()->category_id);

        $before = glob(storage_path('backups/categorias_de_modulos_*.json')) ?: [];
        $this->artisan('mera:detach-categories')->assertSuccessful();
        $after = array_values(array_diff(glob(storage_path('backups/categorias_de_modulos_*.json')) ?: [], $before));

        $this->assertNull($with->fresh()->category_id);
        $this->assertNull($without->fresh()->category_id);
        $this->assertModelExists($category); // la categoría no se borra
        $this->assertCount(1, $after);
        $this->assertStringContainsString('"category": "Sismos"', file_get_contents($after[0]));

        @unlink($after[0]); // no dejar respaldos de prueba en storage/backups
        $this->artisan('mera:detach-categories')->expectsOutputToContain('No hay nada que hacer')->assertSuccessful();
    }

    // U12 --------------------------------------------------------------

    public function test_roles_are_shown_with_readable_names(): void
    {
        $this->actingAs($this->superAdmin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Super administrador')
            ->assertSee('Administrador');

        $this->actingAs($this->superAdmin)->get(route('roles.index'))
            ->assertOk()
            ->assertSee('¿Cómo funciona el acceso?');
    }

    public function test_only_panel_roles_can_be_assigned(): void
    {
        Role::findOrCreate('user', 'web');

        $this->actingAs($this->superAdmin)->get(route('users.create'))
            ->assertOk()
            ->assertDontSee('value="user"', false);

        $this->actingAs($this->superAdmin)->post(route('users.store'), [
            'name' => 'Sin panel', 'email' => 'sinpanel@example.com', 'password' => 'secreto123', 'role' => 'user',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'sinpanel@example.com']);
    }

    public function test_a_user_keeps_a_non_panel_role_when_edited(): void
    {
        $legacy = $this->userWithRole('user');

        $this->actingAs($this->superAdmin)->get(route('users.edit', $legacy))
            ->assertOk()
            ->assertSee('value="user"', false);

        $this->actingAs($this->superAdmin)->put(route('users.update', $legacy), [
            'name' => 'Nombre nuevo', 'email' => $legacy->email, 'role' => 'user',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Nombre nuevo', $legacy->fresh()->name);
    }

    // U13 --------------------------------------------------------------

    public function test_activity_log_is_human_readable_and_filterable(): void
    {
        $this->actingAs($this->admin);
        $course = Course::factory()->create(['name' => 'Inocuidad alimentaria']);
        $course->update(['name' => 'Inocuidad alimentaria II']);

        $this->actingAs($this->superAdmin)->get(route('activity.logs.index'))
            ->assertOk()
            ->assertSee('Módulo «Inocuidad alimentaria II»', false)
            ->assertSee('Nombre:')
            ->assertDontSee('App\\Models\\Course');

        $this->get(route('activity.logs.index', ['log' => 'usuarios']))
            ->assertOk()
            ->assertDontSee('Inocuidad alimentaria II');

        $this->get(route('activity.logs.index', ['log' => 'inventado']))
            ->assertSessionHasErrors('log');
    }
}
