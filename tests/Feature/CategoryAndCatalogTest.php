<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithCourseStorage;
use Tests\TestCase;

class CategoryAndCatalogTest extends TestCase
{
    use InteractsWithCourseStorage, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->userWithRole(User::ROLE_ADMIN);
    }

    public function test_same_subcategory_name_is_allowed_under_different_parents_but_not_among_siblings(): void
    {
        $a = Category::factory()->create(['name' => 'Ventas']);
        $b = Category::factory()->create(['name' => 'Operaciones']);

        $this->actingAs($this->admin)->post(route('categories.store'), ['name' => 'Básico', 'parent_id' => $a->id])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('categories.store'), ['name' => 'Básico', 'parent_id' => $b->id])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('categories.store'), ['name' => 'Básico', 'parent_id' => $a->id])->assertSessionHasErrors('name');

        $this->assertSame(['basico', 'basico-2'], Category::where('name', 'Básico')->orderBy('id')->pluck('slug')->all());
    }

    public function test_category_cannot_be_moved_under_its_own_descendant(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);

        $this->actingAs($this->admin)->put(route('categories.update', $root), [
            'name' => $root->name, 'parent_id' => $child->id,
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_tree_and_paths_are_built_without_n_plus_one_queries(): void
    {
        $root = Category::factory()->create(['name' => 'A']);
        $child = Category::factory()->create(['name' => 'B', 'parent_id' => $root->id]);
        $leaf = Category::factory()->create(['name' => 'C', 'parent_id' => $child->id]);
        Course::factory()->count(15)->create(['category_id' => $leaf->id]);
        Category::flushCache();

        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('courses.index'))->assertOk()->assertSee('A &gt; B &gt; C', false);
        $queries = count(DB::getQueryLog());

        $this->assertLessThan(15, $queries, "El listado hizo {$queries} consultas (N+1)");
    }

    public function test_deleting_a_category_warns_about_and_reports_the_impact(): void
    {
        $root = Category::factory()->create(['name' => 'Raíz']);
        $child = Category::factory()->create(['parent_id' => $root->id]);
        $course = Course::factory()->create(['category_id' => $child->id]);

        $this->actingAs($this->admin)->get(route('categories.index'))
            ->assertSee('1 subcategorías y 1 cursos quedarán sin categoría', false);

        $this->actingAs($this->admin)->delete(route('categories.destroy', $root))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 subcategorías'));

        $this->assertNull($course->fresh()->category_id);
    }

    public function test_public_catalog_works_for_guests(): void
    {
        Course::factory()->create(['name' => 'Curso público']);

        $this->get(route('catalog.index'))->assertOk()->assertSee('Curso público');
    }

    public function test_api_keeps_its_original_shape_and_is_cached(): void
    {
        $course = Course::factory()->create(['name' => 'API curso']);

        $this->getJson('/api/courses')
            ->assertOk()
            ->assertJsonStructure([['name', 'description', 'cover_image', 'url', 'cover_image_url', 'course_url']])
            ->assertJsonPath('0.course_url', $course->content_url);

        Course::factory()->create(['name' => 'Otro']);
        $this->getJson('/api/courses')->assertJsonCount(2); // la caché se invalida al crear
    }

    public function test_api_relative_urls_do_not_produce_a_double_slash_in_wordpress(): void
    {
        $course = Course::factory()->create(['path' => 'cursos/fundamentos', 'cover_image' => 'cursos/fundamentos/cover.png']);

        $item = $this->getJson('/api/courses')->json(0);

        // Lo que hace hoy el WordPress: "https://cursos.meracorporation.com/" + curso.url
        $this->assertSame('https://cursos.meracorporation.com/storage/cursos/fundamentos/index.html', 'https://cursos.meracorporation.com/'.$item['url']);
        $this->assertSame('storage/cursos/fundamentos/cover.png', $item['cover_image']);
        $this->assertStringStartsWith('http', $item['course_url']);
        $this->assertStringNotContainsString('//storage', $item['course_url']);
    }

    public function test_api_paginates_and_filters_when_page_is_requested(): void
    {
        $category = Category::factory()->create(['name' => 'Seguridad']);
        Course::factory()->count(14)->create();
        Course::factory()->create(['name' => 'Protocolo de sismos', 'category_id' => $category->id]);

        $this->getJson('/api/courses?page=2&per_page=12')
            ->assertOk()
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/courses?page=1&search=sismos')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/courses?page=1&category_id='.$category->id)
            ->assertJsonPath('data.0.name', 'Protocolo de sismos');

        $this->getJson('/api/courses?page=1&per_page=500')->assertStatus(422);
    }
}
