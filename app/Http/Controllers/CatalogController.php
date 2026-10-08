<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catálogo público de cursos (/cursos). Reemplaza al antiguo publicIndex, que reutilizaba
 * la vista del panel y fallaba con error 500 para visitantes.
 */
class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $courses = Course::query()
            ->search($request->input('search'))
            ->inCategoryTree($request->input('category_id'))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return view('catalog.index', [
            'courses' => $courses,
            'categories' => Category::tree(),
            'categoryPaths' => Category::pathMap(),
        ]);
    }
}
