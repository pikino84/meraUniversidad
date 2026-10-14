<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // U14: la organización principal ahora es por carpetas de curso; las categorías
        // siguen en stand by (CLAUDE.md §10), así que pasan a segundo plano.
        return view('dashboard', [
            'usersCount' => User::count(),
            'coursesCount' => Course::count(),
            'groupsCount' => CourseGroup::count(),
            'publishedGroupsCount' => CourseGroup::where('is_published', true)->count(),
            'ungroupedCount' => Course::whereNull('course_group_id')->count(),
            'uncategorizedCount' => Course::whereNull('category_id')->count(),
            'categoriesCount' => count(Category::flat()),
            'recentCourses' => Course::with('group:id,name')->latest()->take(5)->get(),
            'categoryPaths' => Category::pathMap(),
        ]);
    }
}
