<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::all()->map(function ($course) {
            return [
                'name' => $course->name,
                'description' => $course->description,
                'cover_image' => asset('storage/' . $course->cover_image),
                'url' => asset('storage/' . $course->path . '/index.html'),
            ];
        });

        return response()->json($courses);
    }
}

