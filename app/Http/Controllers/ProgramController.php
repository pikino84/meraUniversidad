<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\View\View;

/**
 * Páginas públicas de programas (recorridos de varios cursos), definidos en config('mera.programs').
 * Acceso público, igual que el catálogo /cursos.
 */
class ProgramController extends Controller
{
    public function show(string $program): View
    {
        $config = config("mera.programs.{$program}");

        abort_unless(is_array($config), 404);

        // Una sola consulta para todos los módulos.
        $courses = Course::query()
            ->whereIn('slug', array_column($config['modules'], 'course_slug'))
            ->get(['id', 'name', 'slug', 'path'])
            ->keyBy('slug');

        $modules = array_map(function (array $module, int $index) use ($courses) {
            $course = $courses->get($module['course_slug'] ?? '');
            $baseUrl = $course?->content_url
                ?? (! empty($module['fallback_path'])
                    ? Course::publicUrl(rtrim($module['fallback_path'], '/').'/'.config('mera.courses.entry_file'))
                    : null);

            return [
                'number' => $index + 1,
                'key' => $module['course_slug'] ?? 'modulo-'.($index + 1),
                'title' => $module['title'],
                'url' => $baseUrl ? $baseUrl.($module['start'] ?? '') : null,
            ];
        }, $config['modules'], array_keys($config['modules']));

        return view('programs.show', [
            'slug' => $program,
            'program' => $config,
            'modules' => $modules,
        ]);
    }
}
