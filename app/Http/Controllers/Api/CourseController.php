<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Listado público de cursos para consumo externo (WordPress: meracorporation.com/cursos-university).
 *
 * Dos modos, compatibles entre sí:
 *  - GET /api/courses                → arreglo plano (formato original, lo usa el WordPress actual).
 *  - GET /api/courses?page=1         → paginado: { data: [...], meta: {...} }
 *        Filtros opcionales: per_page (1-48, def. 12), search, category_id (incluye subcategorías).
 *
 * Campos:
 *  - cover_image / url: rutas RELATIVAS sin "/" inicial ("storage/cursos/…"). Así el WordPress actual,
 *    que antepone "https://cursos.meracorporation.com/", ya no genera el doble slash.
 *  - cover_image_url / course_url: URLs ABSOLUTAS (usar estas en integraciones nuevas).
 *
 * Todo sale de una lista cacheada (se invalida al crear/editar/borrar cursos o categorías).
 */
class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $courses = collect($this->allCourses());

        if (! $request->has('page')) {
            return response()->json($courses->values());
        }

        // Modo paginado: más recientes primero.
        $courses = $courses->sortByDesc('id');

        $validated = $request->validate([
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:48'],
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
        ]);

        if ($search = trim((string) ($validated['search'] ?? ''))) {
            $needle = Str::lower(Str::ascii($search));
            $courses = $courses->filter(fn ($c) => str_contains(Str::lower(Str::ascii($c['name'].' '.$c['description'])), $needle));
        }

        if (! empty($validated['category_id'])) {
            $ids = Category::descendantIdsOf((int) $validated['category_id'])->all();
            $courses = $courses->filter(fn ($c) => in_array($c['category_id'], $ids, true));
        }

        $perPage = (int) ($validated['per_page'] ?? 12);
        $page = (int) ($validated['page'] ?? 1);
        $total = $courses->count();

        return response()->json([
            'data' => $courses->slice(($page - 1) * $perPage, $perPage)->values(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function allCourses(): array
    {
        $minutes = config('mera.api.courses_cache_minutes');

        return Cache::remember(Course::API_CACHE_KEY, now()->addMinutes($minutes), function () {
            $paths = Category::pathMap();

            return Course::query()
                ->orderBy('id') // mismo orden que el Course::all() original
                ->get(['id', 'name', 'slug', 'description', 'cover_image', 'path', 'category_id', 'created_at', 'updated_at'])
                ->map(fn (Course $course) => [
                    'id' => $course->id,
                    'name' => $course->name,
                    'slug' => $course->slug,
                    'description' => $course->description,
                    'category_id' => $course->category_id,
                    'category' => $course->category_id ? ($paths[$course->category_id] ?? null) : null,
                    // Formato original (relativo, sin "/" inicial)
                    'cover_image' => $course->cover_image ? Course::publicPath($course->cover_image) : null,
                    'url' => $course->path ? Course::publicPath(rtrim($course->path, '/').'/'.config('mera.courses.entry_file')) : null,
                    // URLs absolutas
                    'cover_image_url' => $course->cover_url,
                    'course_url' => $course->content_url,
                    'updated_at' => $course->updated_at?->toIso8601String(),
                ])
                ->all();
        });
    }
}
