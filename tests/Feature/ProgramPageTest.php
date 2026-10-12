<?php

namespace Tests\Feature;

use App\Models\Course;
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
        config(['mera.programs.proceso-de-manipulacion-de-alimentos.modules.4.fallback_path' => null]);

        $this->get(self::URL)->assertOk()->assertSee('Disponible próximamente');
    }

    public function test_only_configured_programs_are_routed(): void
    {
        $this->get('/no-existe-este-programa')->assertNotFound();
        $this->get('/cursos')->assertOk(); // la ruta comodín no tapa a las demás
    }
}
