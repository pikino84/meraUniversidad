<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseGroup;

/**
 * Programas públicos multi-idioma definidos en config('mera.programs').
 * Resuelve URL → (programa, idioma), arma los módulos de cada idioma y los enlaces de
 * evaluación/encuesta. Un idioma sin módulos no se publica.
 */
final class ProgramCatalog
{
    /**
     * Todos los slugs públicos posibles (para la ruta). Se filtran los que no tienen módulos
     * dentro de resolve(), porque los módulos de carpeta dependen de la BD.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        $slugs = [];

        foreach (config('mera.programs', []) as $program) {
            foreach ($program['languages'] ?? [] as $language) {
                if (! empty($language['slug'])) {
                    $slugs[] = $language['slug'];
                }
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * @return array{key:string,language:string,program:array,page:array,modules:list<array>,actions:list<array>,alternates:array<string,string>}|null
     */
    public static function resolve(string $slug): ?array
    {
        foreach (config('mera.programs', []) as $key => $program) {
            foreach ($program['languages'] ?? [] as $code => $page) {
                if (($page['slug'] ?? null) !== $slug) {
                    continue;
                }

                $modules = self::modules($page, $code);

                if ($modules === []) {
                    return null; // idioma preparado pero sin módulos: no se publica
                }

                return [
                    'key' => $key,
                    'language' => $code,
                    'program' => $program,
                    'page' => $page,
                    'modules' => $modules,
                    'actions' => self::actions($program, $code),
                    'alternates' => self::alternates($program),
                ];
            }
        }

        return null;
    }

    /**
     * URL pública de cada idioma publicado del programa (para el selector y hreflang).
     *
     * @return array<string,string>
     */
    public static function alternates(array $program): array
    {
        $urls = [];

        foreach ($program['languages'] ?? [] as $code => $page) {
            if (! empty($page['slug']) && self::modules($page, $code) !== []) {
                $urls[$code] = url($page['slug']);
            }
        }

        return $urls;
    }

    /**
     * Módulos de un idioma. Si el idioma tiene carpeta de curso ('course_group') y la carpeta
     * tiene módulos en ese idioma, se usan esos (en su orden). Si no, la lista fija 'modules'
     * de la config, que queda como respaldo: así la URL publicada nunca se queda vacía.
     *
     * De la lista fija se reutilizan, por slug del curso, el título corto y el ancla de inicio
     * ('start', p. ej. #/slide/…) también cuando los módulos vienen de la carpeta.
     *
     * @return list<array{number:int,key:string,title:string,url:?string}>
     */
    public static function modules(array $page, string $language): array
    {
        $fromGroup = self::groupModules($page, $language);

        if ($fromGroup !== []) {
            return $fromGroup;
        }

        if (empty($page['modules'])) {
            return [];
        }

        $courses = Course::query()
            ->whereIn('slug', array_column($page['modules'], 'course_slug'))
            ->get(['id', 'name', 'slug', 'path'])
            ->keyBy('slug');

        $list = [];

        foreach (array_values($page['modules']) as $i => $module) {
            $course = $courses->get($module['course_slug'] ?? '');
            $base = $course?->content_url
                ?? (! empty($module['fallback_path'])
                    ? Course::publicUrl(rtrim($module['fallback_path'], '/').'/'.config('mera.courses.entry_file'))
                    : null);

            $list[] = [
                'number' => $i + 1,
                'key' => $module['course_slug'] ?? 'modulo-'.($i + 1),
                'title' => $module['title'] ?? $course?->name ?? '',
                'url' => $base ? $base.($module['start'] ?? '') : null,
            ];
        }

        return $list;
    }

    /**
     * @return list<array{number:int,key:string,title:string,url:?string}>
     */
    private static function groupModules(array $page, string $language): array
    {
        $group = self::group($page);

        if (! $group) {
            return [];
        }

        $overrides = collect($page['modules'] ?? [])->keyBy('course_slug');

        return $group->modules()
            ->where('language', $language)
            ->ordered()
            ->get(['id', 'name', 'slug', 'path', 'position'])
            ->values()
            ->map(fn (Course $course, int $i) => [
                'number' => $i + 1,
                'key' => $course->slug, // misma clave que la lista fija: se conserva el avance guardado
                'title' => $overrides[$course->slug]['title'] ?? $course->name,
                'url' => $course->content_url ? $course->content_url.($overrides[$course->slug]['start'] ?? '') : null,
            ])
            ->all();
    }

    private static function group(array $page): ?CourseGroup
    {
        return empty($page['course_group']) ? null : CourseGroup::where('slug', $page['course_group'])->first();
    }

    /**
     * Programa ligado a una carpeta de curso: idioma => URL pública del programa (solo los
     * idiomas publicados). Vacío si la carpeta no alimenta ningún programa.
     *
     * @return array<string,string>
     */
    public static function linkedTo(CourseGroup $group): array
    {
        $program = self::programFor($group);

        return $program ? self::alternates($program) : [];
    }

    public static function isLinked(CourseGroup $group): bool
    {
        return self::programFor($group) !== null;
    }

    private static function programFor(CourseGroup $group): ?array
    {
        foreach (config('mera.programs', []) as $program) {
            foreach ($program['languages'] ?? [] as $page) {
                if (($page['course_group'] ?? null) === $group->slug) {
                    return $program;
                }
            }
        }

        return null;
    }

    /**
     * Evaluación y encuesta con la URL del idioma; si no tiene, la del idioma por defecto.
     * Si el programa tiene carpeta de curso con links capturados en "Editar carpeta", esos
     * mandan; los de la config quedan como respaldo.
     *
     * @return list<array{type:string,url:string}>
     */
    public static function actions(array $program, string $language): array
    {
        $default = $program['default_language'] ?? 'es';
        $group = self::group($program['languages'][$language] ?? []) ?? self::group($program['languages'][$default] ?? []);
        $actions = [];

        foreach ($program['actions'] ?? [] as $action) {
            $url = $action['url'] ?? null;
            $resolved = $group?->actionUrl($action['type'], $language)
                ?: (is_array($url) ? ((($url[$language] ?? null) ?: ($url[$default] ?? null))) : $url);

            if ($resolved) {
                $actions[] = ['type' => $action['type'], 'url' => $resolved];
            }
        }

        return $actions;
    }
}
