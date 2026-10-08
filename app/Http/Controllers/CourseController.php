<?php

namespace App\Http\Controllers;

use App\Http\Requests\Courses\CourseRequest;
use App\Models\Category;
use App\Models\Course;
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
    public function __construct(private readonly CourseManager $courses) {}

    public function index(Request $request): View
    {
        $courses = Course::query()
            ->with('category:id,name,parent_id')
            ->search($request->input('search'))
            ->inCategoryTree($request->input('category_id'))
            ->latest()
            ->paginate(config('mera.courses.per_page'))
            ->withQueryString();

        return view('courses.index', [
            'courses' => $courses,
            'categories' => Category::tree(),
            'categoryPaths' => Category::pathMap(),
            'filtering' => $request->filled('search') || $request->filled('category_id'),
        ]);
    }

    public function create(CourseStorage $storage): View
    {
        return view('courses.create', [
            'categories' => Category::tree(),
            'maxUploadBytes' => $storage->effectiveMaxUploadBytes(),
        ]);
    }

    public function store(CourseRequest $request): RedirectResponse|JsonResponse
    {
        return $this->handle($request, fn () => $this->courses->create(
            $request->courseData(),
            $request->file('cover_image'),
            $request->file('zip_file'),
        ), 'Curso creado exitosamente.');
    }

    public function edit(Course $course, CourseStorage $storage): View
    {
        return view('courses.edit', [
            'course' => $course,
            'categories' => Category::tree(),
            'maxUploadBytes' => $storage->effectiveMaxUploadBytes(),
        ]);
    }

    public function update(CourseRequest $request, Course $course): RedirectResponse|JsonResponse
    {
        return $this->handle($request, fn () => $this->courses->update(
            $course,
            $request->courseData(),
            $request->file('cover_image'),
            $request->file('zip_file'),
        ), 'Curso actualizado exitosamente.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        try {
            $this->courses->delete($course);
        } catch (Throwable $e) {
            Log::error('Error al eliminar curso', ['course_id' => $course->id, 'exception' => $e]);

            return back()->with('error', 'No se pudo eliminar el curso. Intenta de nuevo.');
        }

        return redirect()->route('courses.index')->with('success', "Curso «{$course->name}» eliminado.");
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

            return $this->fail($request, 'Ocurrió un error inesperado al procesar el curso. Intenta de nuevo o contacta al administrador.', 500);
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
