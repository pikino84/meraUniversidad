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
 * Carpetas de curso (CLAUDE.md §10) por año y versión por país
 * (docs/carpetas/INSTRUCTIVO_VERSIONES.md): Carpeta → Año → Versión → Módulos en orden.
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

    private function market(string $code): Market
    {
        return Market::where('code', $code)->firstOrFail();
    }

    private function version(CourseGroup $group, int $year = 2026, string $code = 'MX'): CourseGroupVersion
    {
        return $group->versions()->firstOrCreate(['year' => $year, 'market_id' => $this->market($code)->id]);
    }

    /** Módulo ya acomodado en una versión. */
    private function module(CourseGroupVersion $version, string $name, int $position): Course
    {
        return Course::factory()->create([
            'name' => $name,
            'course_group_id' => $version->course_group_id,
            'course_group_version_id' => $version->id,
            'language' => $version->language(),
            'position' => $position,
        ]);
    }

    private function names(CourseGroupVersion $version): array
    {
        return $version->modules()->ordered()->pluck('name')->all();
    }

    public function test_admin_creates_a_folder_and_lists_it(): void
    {
        $this->actingAs($this->admin)
            ->post(route('course-groups.store'), ['name' => 'Manipulación de alimentos'])
            ->assertRedirect();

        $group = CourseGroup::sole();
        $this->assertSame('manipulacion-de-alimentos', $group->slug);

        $this->actingAs($this->admin)->get(route('course-groups.index'))
            ->assertOk()->assertSee('Manipulación de alimentos')->assertSee('Sin versiones');

        $this->actingAs($this->admin)->post(route('course-groups.store'), ['name' => 'Manipulación de alimentos'])
            ->assertSessionHasErrors('name');
    }

    public function test_initial_markets_exist_and_their_slugs_are_reserved_for_folders(): void
    {
        $this->assertSame(['MX', 'LATAM', 'US'], Market::ordered()->pluck('code')->all());
        $this->assertSame('en', $this->market('US')->language);

        $this->actingAs($this->admin)->post(route('course-groups.store'), ['name' => 'Cualquiera', 'slug' => 'eua'])
            ->assertSessionHasErrors('slug');
    }

    public function test_bulk_move_assigns_folder_year_version_and_order_without_touching_urls(): void
    {
        $group = CourseGroup::factory()->create();
        $a = Course::factory()->create(['name' => 'Módulo A']);
        $b = Course::factory()->create(['name' => 'Módulo B']);
        $paths = [$a->path, $b->path];
        $urls = [$a->content_url, $b->content_url];

        $this->actingAs($this->admin)->post(route('courses.bulk-move'), [
            'course_ids' => [$b->id, $a->id],
            'course_group_id' => $group->id,
            'year' => 2026,
            'market_id' => $this->market('US')->id,
        ])->assertSessionHas('success', fn ($m) => str_contains($m, '2 módulos movidos') && str_contains($m, '2026 · EUA'));

        $version = CourseGroupVersion::sole();
        $this->assertSame([2026, 'US', false], [$version->year, $version->market->code, $version->is_published]);

        [$a, $b] = [$a->fresh(), $b->fresh()];
        $this->assertSame([$group->id, $version->id, 'en', 2], [$a->course_group_id, $a->course_group_version_id, $a->language, $a->position]);
        $this->assertSame([$group->id, $version->id, 'en', 1], [$b->course_group_id, $b->course_group_version_id, $b->language, $b->position]);
        $this->assertSame($paths, [$a->path, $b->path], 'Mover de carpeta nunca cambia la ruta del módulo');
        $this->assertSame($urls, [$a->content_url, $b->content_url]);
    }

    public function test_mexico_and_latam_share_a_language_but_are_separate_lists(): void
    {
        $group = CourseGroup::factory()->create();
        $mx = $this->version($group, 2026, 'MX');
        $latam = $this->version($group, 2026, 'LATAM');
        $this->module($mx, 'MX 1', 1);
        $this->module($latam, 'Latam 1', 1);
        $course = Course::factory()->create(['name' => 'Latam 2']);

        $this->actingAs($this->admin)->post(route('courses.bulk-move'), [
            'course_ids' => [$course->id], 'course_group_id' => $group->id, 'year' => 2026, 'market_id' => $this->market('LATAM')->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(['MX 1'], $this->names($mx));
        $this->assertSame(['Latam 1', 'Latam 2'], $this->names($latam));
        $this->assertSame('es', $course->fresh()->language);
    }

    public function test_bulk_move_requires_year_and_version_when_a_folder_is_chosen(): void
    {
        $group = CourseGroup::factory()->create();
        $course = Course::factory()->create();

        $this->actingAs($this->admin)->post(route('courses.bulk-move'), [
            'course_ids' => [$course->id],
            'course_group_id' => $group->id,
        ])->assertSessionHasErrors(['year', 'market_id']);

        // Una versión inactiva no se ofrece para mover.
        $this->market('LATAM')->update(['is_active' => false]);
        $this->actingAs($this->admin)->post(route('courses.bulk-move'), [
            'course_ids' => [$course->id], 'course_group_id' => $group->id, 'year' => 2026, 'market_id' => $this->market('LATAM')->id,
        ])->assertSessionHasErrors('market_id');
    }

    public function test_bulk_move_can_create_the_folder_in_the_same_step(): void
    {
        [$a, $b] = [Course::factory()->create(), Course::factory()->create()];

        $this->actingAs($this->admin)
            ->from(route('courses.index', ['search' => 'higiene']))
            ->post(route('courses.bulk-move'), [
                'course_ids' => [$a->id, $b->id],
                'course_group_id' => 'new',
                'new_group_name' => '  Higiene en cocina ',
                'year' => 2026,
                'market_id' => $this->market('MX')->id,
            ])
            ->assertRedirect(route('courses.index', ['search' => 'higiene'])) // se queda en la lista filtrada
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Carpeta «Higiene en cocina» creada') && str_contains($m, '2 módulos movidos'));

        $group = CourseGroup::where('name', 'Higiene en cocina')->firstOrFail();
        $version = $group->versions()->sole();
        $this->assertFalse($version->is_published); // nace apagada
        $this->assertSame([$version->id, $version->id], [$a->fresh()->course_group_version_id, $b->fresh()->course_group_version_id]);
    }

    public function test_bulk_move_rejects_an_empty_or_repeated_new_folder_name(): void
    {
        CourseGroup::factory()->create(['name' => 'Higiene']);
        $course = Course::factory()->create();
        $post = fn (string $name) => $this->actingAs($this->admin)->post(route('courses.bulk-move'), [
            'course_ids' => [$course->id], 'course_group_id' => 'new', 'new_group_name' => $name,
            'year' => 2026, 'market_id' => $this->market('MX')->id,
        ]);

        $post('')->assertSessionHasErrors('new_group_name');
        $post('Higiene')->assertSessionHasErrors('new_group_name');

        $this->assertSame(1, CourseGroup::count());
        $this->assertNull($course->fresh()->course_group_id);
    }

    public function test_modules_can_be_reordered_and_positions_stay_compact(): void
    {
        $version = $this->version(CourseGroup::factory()->create());
        $m = collect(['Uno', 'Dos', 'Tres'])->map(fn ($n, $i) => $this->module($version, $n, $i + 1));

        $this->actingAs($this->admin)->patch(route('courses.move', $m[2]), ['direction' => 'up']);

        $this->assertSame(['Uno', 'Tres', 'Dos'], $this->names($version));

        // Quitar uno de la carpeta: los demás se renumeran sin huecos.
        $this->actingAs($this->admin)->delete(route('courses.ungroup', $m[0]))->assertSessionHas('success');

        $this->assertSame([null, null, null], [$m[0]->fresh()->course_group_id, $m[0]->fresh()->course_group_version_id, $m[0]->fresh()->language]);
        $this->assertSame([1, 2], $version->modules()->ordered()->pluck('position')->all());
    }

    public function test_modules_can_be_reordered_by_drag_and_drop(): void
    {
        $group = CourseGroup::factory()->create();
        $version = $this->version($group);
        $m = collect(['Uno', 'Dos', 'Tres'])->map(fn ($n, $i) => $this->module($version, $n, $i + 1));
        $other = $this->module($this->version($group, 2026, 'US'), 'Other', 1);

        $this->actingAs($this->admin)->get(route('course-groups.versions.show', [$group, $version]))
            ->assertOk()
            ->assertSee(route('course-groups.versions.reorder', [$group, $version]), false)
            ->assertSee('fa-unlink', false)
            ->assertDontSee('fa-sign-out-alt', false);

        $this->actingAs($this->admin)->patchJson(route('course-groups.versions.reorder', [$group, $version]), [
            'order' => [$m[2]->id, $m[0]->id, $m[1]->id],
        ])->assertOk();

        $this->assertSame(['Tres', 'Uno', 'Dos'], $this->names($version));
        $this->assertSame([1, 2, 3], $version->modules()->ordered()->pluck('position')->all());

        // Una lista que no corresponde (falta un módulo o trae uno de otra versión) no cambia nada.
        $this->patchJson(route('course-groups.versions.reorder', [$group, $version]), [
            'order' => [$m[0]->id, $other->id, $m[1]->id],
        ])->assertStatus(409);

        $this->assertSame(['Tres', 'Uno', 'Dos'], $this->names($version));
        $this->assertSame(1, $other->fresh()->position);
    }

    public function test_bulk_move_orders_by_module_number_in_title_and_warns_on_mismatch(): void
    {
        $group = CourseGroup::factory()->create();
        $m2 = Course::factory()->create(['name' => 'Service Standards: MODULE 2']);
        $m1 = Course::factory()->create(['name' => 'Service Standards: MODULE 1']);

        // Llegan en el orden de la lista (más recientes primero): 2, 1.
        $this->actingAs($this->admin)->post(route('courses.bulk-move'), [
            'course_ids' => [$m2->id, $m1->id], 'course_group_id' => $group->id, 'year' => 2026, 'market_id' => $this->market('US')->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame([1, 2], [$m1->fresh()->position, $m2->fresh()->position]);

        // Si alguien los invierte, la carpeta avisa que el título no coincide.
        $version = CourseGroupVersion::sole();
        $this->patchJson(route('course-groups.versions.reorder', [$group, $version]), ['order' => [$m2->id, $m1->id]])->assertOk();

        $this->get(route('course-groups.versions.show', [$group, $version]))
            ->assertOk()
            ->assertSee('El título dice Módulo 2');
    }

    public function test_deleting_a_folder_never_deletes_modules(): void
    {
        $group = CourseGroup::factory()->create(['name' => 'Temporal']);
        $version = $this->version($group);
        $course = $this->module($version, 'Uno', 1);

        $this->actingAs($this->admin)->delete(route('course-groups.destroy', $group))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 módulo regresó'));

        $this->assertModelMissing($group);
        $this->assertModelMissing($version);
        $this->assertModelExists($course);
        $course->refresh();
        $this->assertSame([null, null, null, null], [$course->course_group_id, $course->course_group_version_id, $course->language, $course->position]);
    }

    public function test_folder_page_goes_folder_then_year_then_version(): void
    {
        $group = CourseGroup::factory()->create();
        $mx = $this->version($group, 2026, 'MX');
        $us = $this->version($group, 2026, 'US');
        $old = $this->version($group, 2025, 'MX');
        $this->module($mx, 'Módulo en español', 1);
        $this->module($us, 'Module in English', 1);
        $this->module($old, 'Módulo de 2025', 1);

        // Nivel carpeta: años y, del más reciente, sus versiones como tarjetas (sin módulos).
        $this->actingAs($this->admin)->get(route('course-groups.show', $group))
            ->assertOk()
            ->assertSeeInOrder(['2026', '2025', 'Versiones de 2026', 'México', 'EUA'])
            ->assertSee(route('course-groups.versions.show', [$group, $mx]), false)
            ->assertSee(route('course-groups.versions.show', [$group, $us]), false)
            ->assertDontSee(route('course-groups.versions.show', [$group, $old]), false)
            ->assertDontSee('Módulo en español');

        $this->actingAs($this->admin)->get(route('course-groups.show', [$group, 'anio' => 2025]))
            ->assertOk()->assertSee('Versiones de 2025')->assertSee(route('course-groups.versions.show', [$group, $old]), false);

        // Nivel versión: módulos, y link y reportes en sus pestañas.
        $this->actingAs($this->admin)->get(route('course-groups.versions.show', [$group, $us]))
            ->assertOk()->assertSee('Module in English')->assertDontSee('Módulo en español')
            ->assertSee('para compartir')->assertSee('Reportes');

        $this->actingAs($this->admin)->get(route('course-groups.versions.show', [$group, $mx, 'seccion' => 'link']))
            ->assertOk()->assertSee($mx->publicUrl())->assertSee('Publicar página')->assertDontSee('Módulo en español');

        $this->actingAs($this->admin)->get(route('course-groups.versions.show', [$group, $mx, 'seccion' => 'reportes']))
            ->assertOk()->assertSee('Nuevo corte');

        // Links anteriores (?version=) llevan a la versión; una versión de otra carpeta da 404.
        $this->actingAs($this->admin)->get(route('course-groups.show', [$group, 'version' => $old->id]))
            ->assertRedirect(route('course-groups.versions.show', [$group, $old]));
        $this->actingAs($this->admin)->get(route('course-groups.versions.show', [CourseGroup::factory()->create(), $old]))
            ->assertNotFound();

        $this->actingAs($this->admin)->get(route('course-groups.index'))
            ->assertOk()->assertSee('México 1 · EUA 1')->assertSee('2025:');
    }

    public function test_course_form_places_module_in_folder_year_version_and_position(): void
    {
        $this->setUpCourseStorage();
        $group = CourseGroup::factory()->create();
        $version = $this->version($group);
        $this->module($version, 'Existente', 1);

        $this->actingAs($this->admin)->post(route('courses.store'), [
            'name' => 'Nuevo primero',
            'description' => 'x',
            'cover_image' => $this->cover(),
            'zip_file' => $this->validZip(),
            'course_group_id' => $group->id,
            'year' => 2026,
            'market_id' => $this->market('MX')->id,
            'position' => 1,
        ])->assertSessionHas('success');

        $this->assertSame(['Nuevo primero', 'Existente'], $this->names($version));

        // Sin año ni versión: error.
        $this->actingAs($this->admin)->put(route('courses.update', Course::where('name', 'Existente')->first()), [
            'name' => 'Existente', 'description' => 'x', 'course_group_id' => $group->id,
        ])->assertSessionHasErrors(['year', 'market_id']);
    }

    public function test_api_adds_folder_and_version_fields_without_removing_existing_ones(): void
    {
        $group = CourseGroup::factory()->create(['name' => 'Seguridad']);
        $version = $this->version($group, 2026, 'US');
        $version->update(['catalog_code' => 'NRM-SEG-002-EL-26']);
        $this->module($version, 'Safety 3', 3);

        $this->getJson('/api/courses')
            ->assertOk()
            ->assertJsonStructure([['id', 'name', 'description', 'cover_image', 'url', 'category_id', 'category', 'course_url', 'cover_image_url', 'course_group_id', 'course_group', 'language', 'position', 'year', 'market', 'market_name', 'catalog_code']])
            ->assertJsonPath('0.course_group', 'Seguridad')
            ->assertJsonPath('0.language', 'en')
            ->assertJsonPath('0.position', 3)
            ->assertJsonPath('0.year', 2026)
            ->assertJsonPath('0.market', 'US')
            ->assertJsonPath('0.market_name', 'EUA')
            ->assertJsonPath('0.catalog_code', 'NRM-SEG-002-EL-26');
    }

    public function test_courses_index_filters_by_folder(): void
    {
        $group = CourseGroup::factory()->create();
        $this->module($this->version($group), 'En carpeta', 1);
        Course::factory()->create(['name' => 'Suelto']);

        $this->actingAs($this->admin)->get(route('courses.index', ['carpeta' => 'sin']))
            ->assertOk()->assertSee('Suelto')->assertDontSee('>En carpeta<', false);

        $this->actingAs($this->admin)->get(route('courses.index', ['carpeta' => $group->id]))
            ->assertOk()->assertSee('En carpeta')->assertSee('2026 · México')->assertDontSee('Suelto');
    }

    /** Pedido del usuario (2026-10-22): /carpetas paginado de 10 y con buscador. */
    public function test_folders_are_paginated_and_searchable(): void
    {
        foreach (range(1, 12) as $i) {
            CourseGroup::create(['name' => sprintf('Curso %02d', $i), 'position' => $i]);
        }
        $seguridad = CourseGroup::create(['name' => 'Fundamentos Seguridad en el Trabajo', 'position' => 13]);
        $this->version($seguridad)->update(['catalog_code' => 'NRM-SEG-002-EL-26']);

        $this->actingAs($this->admin)->get(route('course-groups.index'))
            ->assertOk()->assertSee('13 carpetas')->assertSee('Curso 10')->assertDontSee('Curso 11')->assertSee('Página');
        $this->actingAs($this->admin)->get(route('course-groups.index', ['page' => 2]))
            ->assertOk()->assertSee('Curso 11')->assertSee('Fundamentos Seguridad')->assertDontSee('Curso 01');

        // Por nombre (sin importar mayúsculas), por código del catálogo de una versión y sin resultados.
        $this->actingAs($this->admin)->get(route('course-groups.index', ['q' => 'seguridad']))
            ->assertOk()->assertSee('1 carpeta de 13')->assertSee('Fundamentos Seguridad')->assertDontSee('Curso 01')
            ->assertDontSee('aria-label="Subir', false);
        $this->actingAs($this->admin)->get(route('course-groups.index', ['q' => 'NRM-SEG-002']))
            ->assertOk()->assertSee('Fundamentos Seguridad')->assertDontSee('Curso 01');
        $this->actingAs($this->admin)->get(route('course-groups.index', ['q' => '100%_']))
            ->assertOk()->assertSee('Ninguna carpeta coincide');
    }
}
