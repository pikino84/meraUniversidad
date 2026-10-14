<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCourseStorage;
use Tests\TestCase;

/**
 * Carpetas de curso (CLAUDE.md §10): Curso → Idioma → Módulos en orden.
 */
class CourseGroupTest extends TestCase
{
    use InteractsWithCourseStorage, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->userWithRole(User::ROLE_ADMIN);
    }

    public function test_admin_creates_a_folder_and_lists_it(): void
    {
        $this->actingAs($this->admin)
            ->post(route('course-groups.store'), ['name' => 'Manipulación de alimentos'])
            ->assertRedirect();

        $group = CourseGroup::sole();
        $this->assertSame('manipulacion-de-alimentos', $group->slug);

        $this->actingAs($this->admin)->get(route('course-groups.index'))
            ->assertOk()->assertSee('Manipulación de alimentos');

        $this->actingAs($this->admin)->post(route('course-groups.store'), ['name' => 'Manipulación de alimentos'])
            ->assertSessionHasErrors('name');
    }

    public function test_bulk_move_assigns_folder_language_and_order_without_touching_urls(): void
    {
        $group = CourseGroup::factory()->create();
        $a = Course::factory()->create(['name' => 'Módulo A']);
        $b = Course::factory()->create(['name' => 'Módulo B']);
        $paths = [$a->path, $b->path];
        $urls = [$a->content_url, $b->content_url];

        $this->actingAs($this->admin)->post(route('courses.bulk-move'), [
            'course_ids' => [$b->id, $a->id],
            'course_group_id' => $group->id,
            'language' => 'en',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, '2 módulos movidos'));

        $a->refresh();
        $b->refresh();
        $this->assertSame([$group->id, 'en', 2], [$a->course_group_id, $a->language, $a->position]);
        $this->assertSame([$group->id, 'en', 1], [$b->course_group_id, $b->language, $b->position]);
        $this->assertSame($paths, [$a->path, $b->path], 'Mover de carpeta nunca cambia la ruta del módulo');
        $this->assertSame($urls, [$a->content_url, $b->content_url]);
    }

    public function test_bulk_move_requires_language_when_a_folder_is_chosen(): void
    {
        $group = CourseGroup::factory()->create();
        $course = Course::factory()->create();

        $this->actingAs($this->admin)->post(route('courses.bulk-move'), [
            'course_ids' => [$course->id],
            'course_group_id' => $group->id,
            'language' => '',
        ])->assertSessionHasErrors('language');
    }

    public function test_modules_can_be_reordered_and_positions_stay_compact(): void
    {
        $group = CourseGroup::factory()->create();
        $m = collect(['Uno', 'Dos', 'Tres'])->map(fn ($n, $i) => Course::factory()->create([
            'name' => $n, 'course_group_id' => $group->id, 'language' => 'es', 'position' => $i + 1,
        ]));

        $this->actingAs($this->admin)->patch(route('courses.move', $m[2]), ['direction' => 'up']);

        $this->assertSame(['Uno', 'Tres', 'Dos'], $group->modules()->where('language', 'es')->ordered()->pluck('name')->all());

        // Quitar uno de la carpeta: los demás se renumeran sin huecos.
        $this->actingAs($this->admin)->delete(route('courses.ungroup', $m[0]))->assertSessionHas('success');

        $this->assertNull($m[0]->fresh()->course_group_id);
        $this->assertSame([1, 2], $group->modules()->where('language', 'es')->ordered()->pluck('position')->all());
    }

    public function test_modules_can_be_reordered_by_drag_and_drop(): void
    {
        $group = CourseGroup::factory()->create();
        $m = collect(['Uno', 'Dos', 'Tres'])->map(fn ($n, $i) => Course::factory()->create([
            'name' => $n, 'course_group_id' => $group->id, 'language' => 'es', 'position' => $i + 1,
        ]));
        $other = Course::factory()->create(['course_group_id' => $group->id, 'language' => 'en', 'position' => 1]);

        $this->actingAs($this->admin)->get(route('course-groups.show', $group))
            ->assertOk()
            ->assertSee(route('course-groups.reorder', $group), false)
            ->assertSee('fa-unlink', false)
            ->assertDontSee('fa-sign-out-alt', false);

        $this->actingAs($this->admin)->patchJson(route('course-groups.reorder', $group), [
            'language' => 'es', 'order' => [$m[2]->id, $m[0]->id, $m[1]->id],
        ])->assertOk();

        $this->assertSame(['Tres', 'Uno', 'Dos'], $group->modules()->where('language', 'es')->ordered()->pluck('name')->all());
        $this->assertSame([1, 2, 3], $group->modules()->where('language', 'es')->ordered()->pluck('position')->all());

        // Una lista que no corresponde (falta un módulo o trae uno de otro idioma) no cambia nada.
        $this->patchJson(route('course-groups.reorder', $group), [
            'language' => 'es', 'order' => [$m[0]->id, $other->id, $m[1]->id],
        ])->assertStatus(409);

        $this->assertSame(['Tres', 'Uno', 'Dos'], $group->modules()->where('language', 'es')->ordered()->pluck('name')->all());
        $this->assertSame(1, $other->fresh()->position);
    }

    public function test_bulk_move_orders_by_module_number_in_title_and_warns_on_mismatch(): void
    {
        $group = CourseGroup::factory()->create();
        $m2 = Course::factory()->create(['name' => 'Service Standards: MODULE 2']);
        $m1 = Course::factory()->create(['name' => 'Service Standards: MODULE 1']);

        // Llegan en el orden de la lista (más recientes primero): 2, 1.
        $this->actingAs($this->admin)->post(route('courses.bulk-move'), [
            'course_ids' => [$m2->id, $m1->id], 'course_group_id' => $group->id, 'language' => 'en',
        ])->assertSessionHasNoErrors();

        $this->assertSame([1, 2], [$m1->fresh()->position, $m2->fresh()->position]);

        // Si alguien los invierte, la carpeta avisa que el título no coincide.
        $this->patchJson(route('course-groups.reorder', $group), ['language' => 'en', 'order' => [$m2->id, $m1->id]])->assertOk();

        $this->get(route('course-groups.show', [$group, 'idioma' => 'en']))
            ->assertOk()
            ->assertSee('El título dice Módulo 2');
    }

    public function test_deleting_a_folder_never_deletes_modules(): void
    {
        $group = CourseGroup::factory()->create(['name' => 'Temporal']);
        $course = Course::factory()->create(['course_group_id' => $group->id, 'language' => 'es', 'position' => 1]);

        $this->actingAs($this->admin)->delete(route('course-groups.destroy', $group))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 módulo regresó'));

        $this->assertModelMissing($group);
        $this->assertModelExists($course);
        $this->assertSame([null, null, null], [$course->fresh()->course_group_id, $course->fresh()->language, $course->fresh()->position]);
    }

    public function test_folder_page_shows_spanish_and_english_tabs(): void
    {
        $group = CourseGroup::factory()->create();
        Course::factory()->create(['name' => 'Módulo en español', 'course_group_id' => $group->id, 'language' => 'es', 'position' => 1]);
        Course::factory()->create(['name' => 'Module in English', 'course_group_id' => $group->id, 'language' => 'en', 'position' => 1]);

        $this->actingAs($this->admin)->get(route('course-groups.show', $group))
            ->assertOk()->assertSee('Módulo en español')->assertDontSee('Module in English');

        $this->actingAs($this->admin)->get(route('course-groups.show', [$group, 'idioma' => 'en']))
            ->assertOk()->assertSee('Module in English')->assertDontSee('Módulo en español');
    }

    public function test_course_form_places_module_in_folder_and_position(): void
    {
        $this->setUpCourseStorage();
        $group = CourseGroup::factory()->create();
        Course::factory()->create(['name' => 'Existente', 'course_group_id' => $group->id, 'language' => 'es', 'position' => 1]);

        $this->actingAs($this->admin)->post(route('courses.store'), [
            'name' => 'Nuevo primero',
            'description' => 'x',
            'cover_image' => $this->cover(),
            'zip_file' => $this->validZip(),
            'course_group_id' => $group->id,
            'language' => 'es',
            'position' => 1,
        ])->assertSessionHas('success');

        $this->assertSame(['Nuevo primero', 'Existente'], $group->modules()->where('language', 'es')->ordered()->pluck('name')->all());
    }

    public function test_api_adds_folder_fields_without_removing_existing_ones(): void
    {
        $group = CourseGroup::factory()->create(['name' => 'Seguridad']);
        Course::factory()->create(['course_group_id' => $group->id, 'language' => 'en', 'position' => 3]);

        $this->getJson('/api/courses')
            ->assertOk()
            ->assertJsonStructure([['id', 'name', 'description', 'cover_image', 'url', 'category_id', 'category', 'course_url', 'cover_image_url', 'course_group_id', 'course_group', 'language', 'position']])
            ->assertJsonPath('0.course_group', 'Seguridad')
            ->assertJsonPath('0.language', 'en')
            ->assertJsonPath('0.position', 3);
    }

    public function test_courses_index_filters_by_folder(): void
    {
        $group = CourseGroup::factory()->create();
        Course::factory()->create(['name' => 'En carpeta', 'course_group_id' => $group->id, 'language' => 'es']);
        Course::factory()->create(['name' => 'Suelto']);

        $this->actingAs($this->admin)->get(route('courses.index', ['carpeta' => 'sin']))
            ->assertOk()->assertSee('Suelto')->assertDontSee('>En carpeta<', false);

        $this->actingAs($this->admin)->get(route('courses.index', ['carpeta' => $group->id]))
            ->assertOk()->assertSee('En carpeta')->assertDontSee('Suelto');
    }
}
