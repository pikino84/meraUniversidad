<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard', [
            'usersCount'      => User::count(),
            'coursesCount'    => Course::count(),
            'categoriesCount' => Category::count(),
            'recentCourses' => Course::latest()->take(5)->get(),
            // 'recentActivity' => ActivityLog::latest()->take(10)->get(),
            'rolesCount'      => Role::count(),
        ]);
    }
}
