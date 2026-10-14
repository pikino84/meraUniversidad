<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Services\Courses\CourseStorage;
use Illuminate\Console\Command;

/**
 * Revisa (SOLO LECTURA) que cada curso tenga su index.html y su portada en el disco.
 * CLAUDE.md §12, F1. No modifica nada: sirve para detectar cursos rotos (p. ej. D4).
 *
 *   php artisan mera:check-courses
 */
class CheckCourses extends Command
{
    protected $signature = 'mera:check-courses {--only-missing : Mostrar solo los cursos con archivos faltantes}';

    protected $description = 'Revisa que cada curso tenga su contenido (index.html) y su portada (solo lectura)';

    public function handle(CourseStorage $storage): int
    {
        $rows = [];
        $broken = 0;
        $total = 0;

        Course::query()->orderBy('id')->chunk(200, function ($courses) use ($storage, &$rows, &$broken, &$total) {
            foreach ($courses as $course) {
                $total++;
                $missing = $storage->missingFiles($course->path, $course->cover_image);
                $isBroken = $missing['content'] || $missing['cover'];
                $broken += $isBroken ? 1 : 0;

                if ($isBroken || ! $this->option('only-missing')) {
                    $rows[] = [
                        $course->id,
                        mb_strimwidth($course->name, 0, 60, '…'),
                        $missing['content'] ? 'FALTA' : 'ok',
                        $missing['cover'] ? 'FALTA' : 'ok',
                        $course->path,
                    ];
                }
            }
        });

        if ($rows !== []) {
            $this->table(['ID', 'Curso', 'index.html', 'Portada', 'Carpeta'], $rows);
        }

        $broken === 0
            ? $this->info("Los {$total} cursos tienen sus archivos.")
            : $this->warn("{$broken} de {$total} cursos tienen archivos faltantes.");

        return self::SUCCESS;
    }
}
