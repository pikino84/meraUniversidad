<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'usersCount' => User::count(),
            'coursesCount' => Course::count(),
            'uncategorizedCount' => Course::whereNull('category_id')->count(),
            'categoriesCount' => count(Category::flat()),
            'rolesCount' => Role::count(),
            'recentCourses' => Course::latest()->take(5)->get(),
            'categoryPaths' => Category::pathMap(),
        ]);
    }
}
