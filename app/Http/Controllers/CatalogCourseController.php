<?php

namespace App\Http\Controllers;

use App\Models\CatalogCourse;
use App\Services\Reports\CourseCatalogImporter;
use App\Services\Reports\ReportFormatException;
use App\Support\Like;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Pantalla "Catálogo de cursos" (super admin): lo carga el Excel de Capacitación y aquí se ajusta lo
 * que el Excel no trae: calificación mínima, si tiene evaluación, si cuenta para el seguimiento y
 * los códigos con los que aparece en la plataforma (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md §6).
 */
class CatalogCourseController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'anio' => $request->integer('anio') ?: null,
            'ruta' => in_array($request->query('ruta'), array_keys(CatalogCourse::ROUTES), true) ? $request->query('ruta') : null,
            'q' => trim((string) $request->query('q')),
        ];

        $courses = CatalogCourse::query()
            ->when($filters['anio'], fn ($q, $year) => $q->where('year', $year))
            ->when($filters['ruta'], fn ($q, $route) => $q->where('route', $route))
            ->tap(fn ($q) => Like::contains($q, ['code', 'short_title', 'title', 'aliases'], $filters['q']))
            ->ordered()
            ->get();

        return view('course-catalog.index', [
            'courses' => $courses,
            'filters' => $filters,
            'years' => CatalogCourse::query()->distinct()->orderByDesc('year')->pluck('year'),
            'total' => CatalogCourse::count(),
        ]);
    }

    public function import(Request $request, CourseCatalogImporter $importer): RedirectResponse
    {
        $request->validate([
            'catalog' => ['required', 'file', 'mimes:xlsx', 'max:'.(int) config('mera.progress.catalog_max_upload_kb', 5120)],
        ], [
            'catalog.required' => 'Elige el Excel del catálogo.',
            'catalog.mimes' => 'El catálogo debe ser un archivo .xlsx.',
        ]);

        try {
            $result = $importer->import($request->file('catalog')->getRealPath());
        } catch (ReportFormatException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Error al cargar el catálogo de cursos', ['exception' => $e]);

            return back()->with('error', 'No se pudo leer el catálogo. Revisa el archivo e inténtalo de nuevo.');
        }

        activity('catalogo')->causedBy($request->user())
            ->withProperties(['nuevos' => $result['created'], 'actualizados' => $result['updated'], 'sin_leer' => $result['skipped']])
            ->log('Catálogo de cursos cargado desde Excel');

        $message = sprintf('Catálogo cargado: %d nuevos, %d actualizados y %d sin cambios.', count($result['created']), count($result['updated']), $result['unchanged']);

        if ($result['skipped']) {
            $message .= ' No se pudieron leer '.count($result['skipped']).' filas: '.implode('; ', array_slice($result['skipped'], 0, 5)).'.';
        }
        if ($result['missing']) {
            $message .= ' '.count($result['missing']).' cursos ya no vienen en el Excel (se conservan): '.implode(', ', array_slice($result['missing'], 0, 5)).'.';
        }

        return redirect()->route('course-catalog.index')->with('success', $message);
    }

    public function edit(CatalogCourse $course): View
    {
        return view('course-catalog.edit', ['course' => $course]);
    }

    public function update(Request $request, CatalogCourse $course): RedirectResponse
    {
        // Uno por línea (o separados por coma); se ignora el " | título" si lo pegan completo.
        $aliases = collect(preg_split('/[\r\n,;]+/', strtoupper((string) $request->input('aliases'))))
            ->map(fn ($a) => trim(explode('|', $a)[0]))->filter()->unique()->reject(fn ($a) => $a === $course->code)->values();
        $request->merge(['aliases' => $aliases->all()]);

        $data = $request->validate([
            'short_title' => ['required', 'string', 'max:150'],
            'passing_grade' => ['required', 'integer', 'between:1,100'],
            'has_evaluation' => ['boolean'],
            'in_tracking' => ['boolean'],
            'aliases' => ['array', 'max:20'],
            'aliases.*' => ['regex:'.CatalogCourse::CODE_PATTERN, function ($attribute, $value, $fail) use ($course) {
                $owner = CatalogCourse::resolve($value);
                if ($owner && $owner->id !== $course->id) {
                    $fail("El código {$value} ya es de «{$owner->short_title}» ({$owner->code}).");
                }
            }],
        ], [
            'aliases.*.regex' => 'El código :input no tiene la forma del catálogo (p. ej. NEG-LID-003-BL-26).',
        ], [
            'short_title' => 'nombre corto',
            'passing_grade' => 'calificación mínima',
        ]);

        $course->update([
            'short_title' => trim($data['short_title']),
            'passing_grade' => (int) $data['passing_grade'],
            'has_evaluation' => $request->boolean('has_evaluation'),
            'in_tracking' => $request->boolean('in_tracking'),
            'aliases' => $data['aliases'] ?: null,
        ]);

        return redirect()->route('course-catalog.index', ['anio' => $course->year])->with('success', "«{$course->short_title}» actualizado.");
    }
}
