<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramPageTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/proceso-de-manipulacion-de-alimentos';

    public function test_food_handling_program_is_public_and_lists_modules_and_actions(): void
    {
        $this->get(self::URL)
            ->assertOk()
            ->assertSee('Proceso de manipulación de alimentos')
            ->assertSee('Recepción y almacenamiento operativo')
            ->assertSee('Exclusión de plagas y manejo integral de residuos')
            ->assertSee('https://forms.cloud.microsoft/r/3e12BETYuE?origin=lprLink', false)
            ->assertSee('https://forms.cloud.microsoft/r/6qpNWit2U3?origin=lprLink', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertDontSee('data:image/png;base64', false);
    }

    public function test_modules_resolve_by_course_slug_so_reuploads_do_not_break_links(): void
    {
        // El ZIP se volvió a subir: la carpeta cambió, el slug no.
        Course::factory()->create([
            'name' => 'Módulo 1 - Manejo Higiénico LATAM',
            'slug' => 'modulo-1-manejo-higienico-latam',
            'path' => 'cursos/curso-NuevaCarpeta',
        ]);

        $html = $this->get(self::URL)->assertOk()->getContent();

        $this->assertStringContainsString('/storage/cursos/curso-NuevaCarpeta/index.html#/slide/saz3GISUgN', $html);
        // Los módulos que no están en la BD usan la ruta de respaldo.
        $this->assertStringContainsString('/storage/cursos/modulo-2-manejo-higienico-latam/index.html#/slide/WL8kWAa7Y', $html);
    }

    public function test_module_without_course_nor_fallback_is_shown_as_coming_soon(): void
    {
        config(['mera.programs.proceso-de-manipulacion-de-alimentos.languages.es.modules.4.fallback_path' => null]);

        $this->get(self::URL)->assertOk()->assertSee('Disponible próximamente');
    }

    public function test_english_version_is_not_published_while_it_has_no_modules(): void
    {
        $this->get('/food-handling-process')->assertNotFound();

        // Sin otro idioma publicado no se muestra el selector.
        $this->get(self::URL)->assertOk()
            ->assertSee('<html lang="es">', false)
            ->assertDontSee('hreflang="en"', false)
            ->assertSee('data-program-progress="proceso-de-manipulacion-de-alimentos"', false); // mismo avance guardado que antes
    }

    public function test_english_version_uses_its_own_url_texts_and_modules_from_the_folder(): void
    {
        $group = CourseGroup::factory()->create(['name' => 'Manipulación de alimentos']);
        Course::factory()->create(['name' => 'Module 2: Kitchen', 'slug' => 'kitchen-en', 'path' => 'cursos/kitchen-en', 'course_group_id' => $group->id, 'language' => 'en', 'position' => 2]);
        Course::factory()->create(['name' => 'Module 1: Receiving', 'slug' => 'receiving-en', 'path' => 'cursos/receiving-en', 'course_group_id' => $group->id, 'language' => 'en', 'position' => 1]);
        Course::factory()->create(['name' => 'Módulo en español', 'course_group_id' => $group->id, 'language' => 'es', 'position' => 1]);

        config(['mera.programs.proceso-de-manipulacion-de-alimentos.languages.en.course_group' => $group->slug]);

        $html = $this->get('/food-handling-process')->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Food handling process')
            ->assertSee('Open module')
            ->assertSee('Step 1 · Your 2 modules')
            ->assertSee('Take the assessment')
            ->assertDontSee('Módulo en español')
            // Por ahora el formulario en inglés es el mismo que en español.
            ->assertSee('https://forms.cloud.microsoft/r/3e12BETYuE?origin=lprLink', false)
            ->assertSee('hreflang="es"', false)
            ->getContent();

        // Orden por posición y enlaces al módulo EN de cada botón.
        $this->assertLessThan(strpos($html, 'Module 2: Kitchen'), strpos($html, 'Module 1: Receiving'));
        $this->assertStringContainsString('/storage/cursos/receiving-en/index.html', $html);

        // La versión en español ahora muestra el selector hacia la de inglés.
        $this->get(self::URL)->assertOk()->assertSee('hreflang="en"', false)->assertSee('/food-handling-process', false);
    }

    public function test_program_folder_command_keeps_the_url_progress_and_start_slides(): void
    {
        // Dos de los cinco módulos del programa existen en la BD; uno ya está en otra carpeta.
        $m1 = Course::factory()->create(['name' => 'Módulo 1 - Manejo Higiénico LATAM', 'path' => 'cursos/m1']);
        $m2 = Course::factory()->create(['name' => 'Módulo 2 - Manejo Higiénico LATAM', 'path' => 'cursos/m2']);
        $other = CourseGroup::factory()->create(['name' => 'Otra carpeta']);
        $m3 = Course::factory()->create(['name' => 'Módulo 3 - Manejo Higiénico LATAM', 'course_group_id' => $other->id, 'language' => 'es', 'position' => 1]);

        $this->artisan('mera:program-folder', ['program' => 'proceso-de-manipulacion-de-alimentos'])->assertSuccessful();
        $this->artisan('mera:program-folder', ['program' => 'proceso-de-manipulacion-de-alimentos'])->assertSuccessful(); // idempotente

        $group = CourseGroup::where('slug', 'manipulacion-de-alimentos')->sole();
        $this->assertFalse($group->is_published);
        $this->assertSame('https://forms.cloud.microsoft/r/3e12BETYuE?origin=lprLink', $group->evaluation_url_es);
        $this->assertSame([1, 2], [$m1->fresh()->position, $m2->fresh()->position]);
        $this->assertSame('es', $m1->fresh()->language);
        $this->assertSame($other->id, $m3->fresh()->course_group_id);

        // Misma URL, misma clave de avance, mismos títulos cortos y anclas de inicio.
        $html = $this->get(self::URL)->assertOk()
            ->assertSee('data-program-progress="proceso-de-manipulacion-de-alimentos"', false)
            ->assertSee('data-module="modulo-1-manejo-higienico-latam"', false)
            ->assertSee('Recepción y almacenamiento operativo')
            ->getContent();
        $this->assertStringContainsString('/storage/cursos/m1/index.html#/slide/saz3GISUgN', $html);

        // La carpeta ya manda: solo sus módulos, en su orden.
        $this->assertStringNotContainsString('modulo-4-manejo-higienico-latam', $html);

        // Su página propia lleva al link del programa (el que ya se compartió).
        $this->get('/manipulacion-de-alimentos')->assertRedirect(url(self::URL));
    }

    public function test_program_without_folder_modules_falls_back_to_the_configured_list(): void
    {
        CourseGroup::factory()->create(['name' => 'Manipulación de alimentos', 'slug' => 'manipulacion-de-alimentos']);

        $this->get(self::URL)->assertOk()
            ->assertSee('Exclusión de plagas y manejo integral de residuos')
            ->assertSee('/storage/cursos/modulo-5-manejo-higienico-latam/index.html#/slide/g5SbzQZUF', false);
    }

    public function test_folder_forms_override_the_configured_ones(): void
    {
        CourseGroup::factory()->create([
            'name' => 'Manipulación de alimentos', 'slug' => 'manipulacion-de-alimentos',
            'evaluation_url_es' => 'https://forms.cloud.microsoft/r/NUEVA-EVAL',
        ]);

        $this->get(self::URL)->assertOk()
            ->assertSee('https://forms.cloud.microsoft/r/NUEVA-EVAL', false)
            ->assertDontSee('https://forms.cloud.microsoft/r/3e12BETYuE', false)
            ->assertSee('https://forms.cloud.microsoft/r/6qpNWit2U3?origin=lprLink', false); // encuesta: respaldo de la config
    }

    public function test_english_forms_can_differ_from_spanish(): void
    {
        config([
            'mera.programs.proceso-de-manipulacion-de-alimentos.languages.en.modules' => [
                ['title' => 'Receiving', 'course_slug' => 'x', 'fallback_path' => 'cursos/x'],
            ],
            'mera.programs.proceso-de-manipulacion-de-alimentos.actions.0.url.en' => 'https://forms.cloud.microsoft/r/ENGLISH',
        ]);

        $this->get('/food-handling-process')->assertOk()
            ->assertSee('https://forms.cloud.microsoft/r/ENGLISH', false)
            ->assertDontSee('https://forms.cloud.microsoft/r/3e12BETYuE', false);
    }

    public function test_only_configured_programs_are_routed(): void
    {
        $this->get('/no-existe-este-programa')->assertNotFound();
        $this->get('/cursos')->assertOk(); // la ruta comodín no tapa a las demás
    }
}
