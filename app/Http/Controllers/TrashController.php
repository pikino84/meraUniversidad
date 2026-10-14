<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\Courses\CourseGroupOrganizer;
use App\Services\Courses\CourseManager;
use App\Services\Courses\CoursePackageException;
use App\Services\Courses\CourseStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Papelera de módulos (CLAUDE.md §12, F2). Solo Super Admin. Lo borrado se puede restaurar
 * mientras sus archivos sigan en la papelera (se purgan a los N días).
 */
class TrashController extends Controller
{
    public function index(CourseStorage $storage): View
    {
        $days = (int) config('mera.courses.trash_retention_days');

        $courses = Course::onlyTrashed()
            ->with('group:id,name')
            ->latest('deleted_at')
            ->paginate(20);

        $restorable = $courses->getCollection()->mapWithKeys(fn (Course $c) => [
            $c->id => collect($c->trash_items ?? [])->every(fn ($item) => $storage->inTrash($item['name'])),
        ])->all();

        return view('trash.index', compact('courses', 'restorable', 'days'));
    }

    public function restore(Course $course, CourseManager $manager, CourseGroupOrganizer $organizer): RedirectResponse
    {
        abort_unless($course->trashed(), 404);

        try {
            $manager->restore($course);
        } catch (CoursePackageException $e) {
            return back()->with('error', "No se pudo restaurar «{$course->name}». {$e->getMessage()}");
        }

        // Si estaba en una carpeta, vuelve al final de su idioma (su lugar pudo ocuparse).
        $organizer->placeModule($course, null);

        return redirect()->route('trash.index')
            ->with('success', "«{$course->name}» se restauró y su link vuelve a funcionar.");
    }
}
