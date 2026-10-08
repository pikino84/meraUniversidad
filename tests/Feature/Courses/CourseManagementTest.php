<?php

namespace Tests\Feature\Courses;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\InteractsWithCourseStorage;
use Tests\TestCase;

class CourseManagementTest extends TestCase
{
    use InteractsWithCourseStorage, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCourseStorage();
        $this->admin = $this->userWithRole(User::ROLE_ADMIN);
    }

    private function store(array $overrides = [])
    {
        return $this->actingAs($this->admin)->post(route('courses.store'), array_merge([
            'name' => 'Seguridad en el trabajo',
            'description' => 'NOM-019',
            'cover_image' => $this->cover(),
            'zip_file' => $this->validZip(),
        ], $overrides));
    }

    public function test_admin_can_create_a_course_with_a_valid_package(): void
    {
        $this->store()->assertRedirect(route('courses.index'))->assertSessionHas('success');

        $course = Course::sole();

        $this->assertSame('seguridad-en-el-trabajo', $course->slug);
        $this->assertMatchesRegularExpression('#^cursos/curso-[A-Za-z0-9]{10}$#', $course->path);
        $this->assertFileExists($this->coursesRoot().'/'.basename($course->path).'/index.html');
        $this->assertFileExists($this->coursesRoot().'/'.basename($course->cover_image));
        $this->assertStringEndsWith('.png', $course->cover_image);
        $this->assertFileExists($this->sandbox.'/public/.htaccess', 'Debe endurecer storage al subir');
    }

    public function test_package_is_flattened_when_zipped_inside_a_root_folder(): void
    {
        $this->store(['zip_file' => $this->makeZip([
            'mi-curso/' => '',
            'mi-curso/index.html' => '<html></html>',
            'mi-curso/css/a.css' => 'body{}',
        ])])->assertSessionHas('success');

        $this->assertFileExists($this->coursesRoot().'/'.basename(Course::sole()->path).'/index.html');
    }

    /** @dataProvider maliciousPackages */
    public function test_malicious_or_invalid_packages_are_rejected(array $files, string $expectedMessage): void
    {
        $this->store(['zip_file' => $this->makeZip($files)])
            ->assertSessionHas('error', fn ($message) => str_contains($message, $expectedMessage));

        $this->assertSame(0, Course::count());
        $this->assertSame([], File::directories($this->coursesRoot()), 'No deben quedar carpetas huérfanas');
    }

    public static function maliciousPackages(): array
    {
        return [
            'web shell php' => [['index.html' => 'x', 'shell.php' => '<?php system($_GET[1]);'], 'no permitidos'],
            'phtml oculto' => [['index.html' => 'x', 'img/foto.phtml' => '<?php'], 'no permitidos'],
            'htaccess' => [['index.html' => 'x', '.htaccess' => 'AddType application/x-httpd-php .png'], 'no permitidos'],
            'zip slip' => [['index.html' => 'x', '../../evil.html' => 'x'], 'rutas no válidas'],
            'sin index' => [['otro.html' => 'x'], 'index.html'],
        ];
    }

    public function test_svg_or_php_disguised_covers_are_rejected(): void
    {
        $svg = \Illuminate\Http\UploadedFile::fake()->createWithContent('portada.svg', '<svg onload="alert(1)"></svg>');

        $this->store(['cover_image' => $svg])->assertSessionHasErrors('cover_image');
        $this->assertSame(0, Course::count());
    }

    public function test_a_name_without_slug_characters_never_wipes_the_courses_folder(): void
    {
        $this->store(['name' => 'Primero'])->assertSessionHas('success');
        $first = Course::sole();

        // Antes: Str::slug('¡¿?!') === '' → se borraba storage/app/public/cursos completo.
        $this->store(['name' => '¡¿?!'])->assertSessionHas('success');

        $this->assertFileExists($this->coursesRoot().'/'.basename($first->path).'/index.html');
        $this->assertSame('curso', Course::where('name', '¡¿?!')->value('slug'));
    }

    public function test_duplicate_names_get_unique_slugs(): void
    {
        $this->store()->assertSessionHas('success');
        $this->store()->assertSessionHas('success');

        $this->assertEqualsCanonicalizing(
            ['seguridad-en-el-trabajo', 'seguridad-en-el-trabajo-2'],
            Course::pluck('slug')->all()
        );
    }

    public function test_renaming_a_course_does_not_move_its_files(): void
    {
        $this->store();
        $course = Course::sole();
        $path = $course->path;

        $this->actingAs($this->admin)->put(route('courses.update', $course), [
            'name' => 'Nombre nuevo',
            'description' => 'x',
        ])->assertSessionHas('success');

        $course->refresh();
        $this->assertSame('nombre-nuevo', $course->slug);
        $this->assertSame($path, $course->path);
        $this->assertFileExists($this->coursesRoot().'/'.basename($path).'/index.html');
    }

    public function test_replacing_the_package_keeps_old_content_until_success_and_trashes_it(): void
    {
        $this->store();
        $course = Course::sole();
        $oldFolder = basename($course->path);

        // Paquete inválido: el curso debe quedar intacto.
        $this->actingAs($this->admin)->put(route('courses.update', $course), [
            'name' => $course->name, 'description' => 'x',
            'zip_file' => $this->makeZip(['index.html' => 'x', 'x.php' => '<?php']),
        ])->assertSessionHas('error');

        $this->assertSame($course->path, $course->fresh()->path);
        $this->assertFileExists($this->coursesRoot()."/{$oldFolder}/index.html");

        // Paquete válido: carpeta nueva, la vieja va a la papelera.
        $this->actingAs($this->admin)->put(route('courses.update', $course), [
            'name' => $course->name, 'description' => 'x',
            'zip_file' => $this->validZip(),
        ])->assertSessionHas('success');

        $newFolder = basename($course->fresh()->path);
        $this->assertNotSame($oldFolder, $newFolder);
        $this->assertFileExists($this->coursesRoot()."/{$newFolder}/index.html");
        $this->assertDirectoryDoesNotExist($this->coursesRoot()."/{$oldFolder}");
        $this->assertCount(1, File::directories($this->sandbox.'/trash'));
    }

    public function test_legacy_cover_inside_course_folder_is_rescued_when_package_is_replaced(): void
    {
        File::ensureDirectoryExists($this->coursesRoot().'/legacy-course');
        File::put($this->coursesRoot().'/legacy-course/index.html', 'x');
        File::put($this->coursesRoot().'/legacy-course/cover.png', 'img');

        $course = Course::factory()->create([
            'path' => 'cursos/legacy-course',
            'cover_image' => 'cursos/legacy-course/cover.png',
        ]);

        $this->actingAs($this->admin)->put(route('courses.update', $course), [
            'name' => $course->name, 'description' => 'x', 'zip_file' => $this->validZip(),
        ])->assertSessionHas('success');

        $course->refresh();
        $this->assertStringNotContainsString('legacy-course/', $course->cover_image);
        $this->assertFileExists($this->coursesRoot().'/'.basename($course->cover_image));
    }

    public function test_deleting_a_course_moves_files_to_trash(): void
    {
        $this->store();
        $course = Course::sole();

        $this->actingAs($this->admin)->delete(route('courses.destroy', $course))->assertSessionHas('success');

        $this->assertModelMissing($course);
        $this->assertDirectoryDoesNotExist($this->coursesRoot().'/'.basename($course->path));
        $this->assertCount(2, array_merge(File::directories($this->sandbox.'/trash'), File::files($this->sandbox.'/trash')));
    }

    public function test_upload_via_xhr_returns_json(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('courses.store'), [
                'name' => 'Curso XHR', 'description' => 'x',
                'cover_image' => $this->cover(), 'zip_file' => $this->makeZip(['index.html' => 'x', 'a.exe' => 'MZ']),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'no permitidos'));

        $this->actingAs($this->admin)
            ->postJson(route('courses.store'), [
                'name' => 'Curso XHR', 'description' => 'x',
                'cover_image' => $this->cover(), 'zip_file' => $this->validZip(),
            ])
            ->assertOk()
            ->assertJson(['redirect' => route('courses.index')]);
    }

    public function test_index_filters_by_category_including_subcategories_and_escapes_like(): void
    {
        $parent = Category::factory()->create(['name' => 'Seguridad']);
        $child = Category::factory()->create(['name' => 'Sismos', 'parent_id' => $parent->id]);
        Course::factory()->create(['name' => 'Evacuación', 'category_id' => $child->id]);
        Course::factory()->create(['name' => 'Excel 100%', 'category_id' => null]);

        $this->actingAs($this->admin)->get(route('courses.index', ['category_id' => $parent->id]))
            ->assertOk()->assertSee('Evacuación')->assertDontSee('Excel 100%')->assertSee('Seguridad &gt; Sismos', false);

        $this->actingAs($this->admin)->get(route('courses.index', ['search' => '%']))
            ->assertOk()->assertSee('Excel 100%')->assertDontSee('Evacuación');
    }

    public function test_guests_and_users_without_panel_role_cannot_manage_courses(): void
    {
        $this->get(route('courses.index'))->assertRedirect(route('login'));

        $this->actingAs($this->userWithRole('user'))->get(route('courses.index'))->assertForbidden();
    }
}
