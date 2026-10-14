<?php

namespace App\Http\Controllers;

use App\Http\Requests\Courses\CourseRequest;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseGroup;
use App\Services\Courses\CourseGroupOrganizer;
use App\Services\Courses\CourseManager;
use App\Services\Courses\CoursePackageException;
use App\Services\Courses\CourseStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class CourseController extends Controller
{
    public function __construct(
        private readonly CourseManager $courses,
        private readonly CourseGroupOrganizer $organizer,
    ) {}

    public function index(Request $request, CourseStorage $storage): View
    {
        $courses = Course::query()
            ->with(['category:id,name,parent_id', 'group:id,name,slug'])
            ->search($request->input('search'))
            ->inCategoryTree($request->input('category_id'))
            ->when($request->input('carpeta') === 'sin', fn ($q) => $q->whereNull('course_group_id'))
            ->when(ctype_digit((string) $request->input('carpeta')), fn ($q) => $q->where('course_group_id', (int) $request->input('carpeta')))
            // U23: orden elegible (recientes por defecto).
            ->when($request->input('orden'), fn ($q, $orden) => match ($orden) {
                'nombre' => $q->orderBy('name'),
                'actualizados' => $q->latest('updated_at'),
                default => $q->latest(),
            }, fn ($q) => $q->latest())
            ->orderByDesc('id')
            ->paginate(config('mera.courses.per_page'))
            ->withQueryString();

        return view('courses.index', [
            'courses' => $courses,
            'categories' => Category::tree(),
            'categoryPaths' => Category::pathMap(),
            'filtering' => $request->filled('search') || $request->filled('category_id') || $request->filled('carpeta'),
            'groups' => CourseGroup::orderBy('position')->orderBy('name')->get(['id', 'name']),
            // F1: archivos faltantes (solo lectura) de los cursos de esta página.
            'missing' => $courses->getCollection()->mapWithKeys(fn (Course $c) => [$c->id => $storage->missingFiles($c->path, $c->cover_image)])->all(),
        ]);
    }

    public function create(CourseStorage $storage): View
    {
        return view('courses.create', [
            'categories' => Category::tree(),
            'groups' => CourseGroup::orderBy('position')->orderBy('name')->get(['id', 'name']),
            'maxUploadBytes' => $storage->effectiveMaxUploadBytes(),
        ]);
    }

    public function store(CourseRequest $request): RedirectResponse|JsonResponse
    {
        return $this->handle($request, fn () => $this->place($request, $this->courses->create(
            $request->courseData(),
            $request->file('cover_image'),
            $request->file('zip_file'),
        )), 'Módulo creado.');
    }

    public function edit(Course $course, CourseStorage $storage): View
    {
        return view('courses.edit', [
            'course' => $course,
            'missing' => $storage->missingFiles($course->path, $course->cover_image),
            'categories' => Category::tree(),
            'groups' => CourseGroup::orderBy('position')->orderBy('name')->get(['id', 'name']),
            'maxUploadBytes' => $storage->effectiveMaxUploadBytes(),
        ]);
    }

    public function update(CourseRequest $request, Course $course): RedirectResponse|JsonResponse
    {
        return $this->handle($request, fn () => $this->place($request, $this->courses->update(
            $course,
            $request->courseData(),
            $request->file('cover_image'),
            $request->file('zip_file'),
        )), 'Módulo actualizado.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        try {
            $this->courses->delete($course);
            $this->organizer->normalize($course->course_group_id, $course->language); // sin huecos en su carpeta
        } catch (Throwable $e) {
            Log::error('Error al eliminar curso', ['course_id' => $course->id, 'exception' => $e]);

            return back()->with('error', 'No se pudo eliminar el módulo. Intenta de nuevo.');
        }

        return redirect()->route('courses.index')->with('success', "Módulo «{$course->name}» enviado a la papelera.");
    }

    /**
     * Ubica el módulo en su carpeta de curso, idioma y posición (CLAUDE.md §10).
     */
    private function place(CourseRequest $request, Course $course): Course
    {
        $p = $request->placement();
        $this->organizer->assign($course, $p['group'], $p['language'], $p['position']);

        return $course;
    }

    /**
     * Ejecuta el caso de uso y responde en HTML (form normal) o JSON (subida con progreso).
     * Al usuario solo se le muestran mensajes de negocio, nunca detalles internos.
     */
    private function handle(Request $request, callable $action, string $successMessage): RedirectResponse|JsonResponse
    {
        try {
            $action();
        } catch (CoursePackageException $e) {
            return $this->fail($request, $e->getMessage(), 422);
        } catch (Throwable $e) {
            Log::error('Error al procesar curso', ['exception' => $e]);

            return $this->fail($request, 'Ocurrió un error inesperado al procesar el módulo. Intenta de nuevo o contacta al administrador.', 500);
        }

        session()->flash('success', $successMessage);
        $redirect = route('courses.index');

        return $request->expectsJson()
            ? response()->json(['redirect' => $redirect])
            : redirect($redirect);
    }

    private function fail(Request $request, string $message, int $status): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], $status)
            : back()->withInput()->with('error', $message);
    }
}
