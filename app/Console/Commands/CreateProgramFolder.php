<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CourseGroup;
use App\Services\Courses\CourseGroupOrganizer;
use Illuminate\Console\Command;

/**
 * Crea la carpeta de curso que alimenta un programa de config('mera.programs') y le acomoda,
 * en español, los módulos de su lista fija (por slug). Idempotente: si la carpeta ya existe
 * no la duplica, y no mueve módulos que ya estén en OTRA carpeta.
 *
 * No cambia ninguna URL: la página del programa sigue en su mismo link, y si algo falta en la
 * carpeta usa la lista de la config como respaldo.
 *
 *   php artisan mera:program-folder proceso-de-manipulacion-de-alimentos [--dry-run]
 */
class CreateProgramFolder extends Command
{
    protected $signature = 'mera:program-folder {program : Clave del programa en config/mera.php} {--dry-run : Solo mostrar lo que haría}';

    protected $description = 'Crea la carpeta de curso de un programa y le acomoda sus módulos en español';

    public function handle(CourseGroupOrganizer $organizer): int
    {
        $key = (string) $this->argument('program');
        $program = config("mera.programs.{$key}");

        if (! $program) {
            $this->error("No existe el programa «{$key}» en config/mera.php.");

            return self::FAILURE;
        }

        $default = $program['default_language'] ?? 'es';
        $page = $program['languages'][$default] ?? [];
        $slug = $page['course_group'] ?? null;

        if (! $slug) {
            $this->error("El programa «{$key}» no tiene 'course_group' en su idioma por defecto.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $group = CourseGroup::where('slug', $slug)->first();

        if ($group) {
            $this->line("La carpeta «{$group->name}» ({$slug}) ya existe.");
        } elseif ($dryRun) {
            $this->line("[dry-run] Se crearía la carpeta «{$page['title']}» ({$slug}), apagada.");
        } else {
            $group = $this->createGroup($program, $page, $slug);
            $this->info("Carpeta creada: «{$group->name}» ({$slug}). Queda apagada: la página pública sigue siendo la del programa.");
        }

        $slugs = array_column($page['modules'] ?? [], 'course_slug');
        $courses = Course::whereIn('slug', $slugs)->get()->keyBy('slug');
        $toMove = [];

        foreach ($slugs as $courseSlug) {
            $course = $courses->get($courseSlug);

            if (! $course) {
                $this->warn("  · {$courseSlug}: no existe en la BD (la página usa su ruta de respaldo).");
            } elseif ($group && $course->course_group_id === $group->id) {
                $this->line("  · {$courseSlug}: ya está en la carpeta.");
            } elseif ($course->course_group_id) {
                $this->warn("  · {$courseSlug}: está en otra carpeta; no se mueve.");
            } else {
                $toMove[] = $course->id;
                $this->line(($dryRun ? '  · [dry-run] se movería ' : '  · se mueve ').$courseSlug);
            }
        }

        if (! $dryRun && $group && $toMove !== []) {
            $organizer->moveModules($toMove, $group->id, $default);
        }

        $this->info($dryRun ? 'Fin (sin cambios).' : count($toMove).' módulo(s) acomodados en español.');

        return self::SUCCESS;
    }

    private function createGroup(array $program, array $page, string $slug): CourseGroup
    {
        $default = $program['default_language'] ?? 'es';
        $urls = collect($program['actions'] ?? [])->mapWithKeys(fn ($action) => [$action['type'] => $action['url'] ?? null]);
        $url = fn (string $type, string $language) => is_array($urls[$type] ?? null) ? ($urls[$type][$language] ?? null) : ($urls[$type] ?? null);

        $group = new CourseGroup([
            'name' => $page['title'],
            'name_en' => $program['languages']['en']['title'] ?? null,
            'description_es' => $program['languages']['es']['lead'] ?? null,
            'description_en' => $program['languages']['en']['lead'] ?? null,
            'evaluation_url_es' => $url('evaluation', 'es'),
            'evaluation_url_en' => $url('evaluation', 'en'),
            'survey_url_es' => $url('survey', 'es'),
            'survey_url_en' => $url('survey', 'en'),
        ]);
        $group->slug = $slug;
        $group->position = (int) CourseGroup::max('position') + 1;
        $group->save();

        return $group;
    }
}
