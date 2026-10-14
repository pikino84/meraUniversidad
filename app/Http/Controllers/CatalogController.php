<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catálogo público de cursos (/cursos). Reemplaza al antiguo publicIndex, que reutilizaba
 * la vista del panel y fallaba con error 500 para visitantes.
 *
 * 2026-10-14: 12 módulos por página (antes 24) y sin categorías mientras estén ocultas
 * (config mera.categories_enabled): casi ningún módulo tenía una y el filtro no servía.
 */
class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $withCategories = (bool) config('mera.categories_enabled');

        $courses = Course::query()
            ->select(['id', 'name', 'slug', 'description', 'cover_image', 'path', 'category_id', 'created_at'])
            ->search($request->input('search'))
            ->when($withCategories, fn ($q) => $q->inCategoryTree($request->input('category_id')))
            ->latest()
            ->paginate(max(1, (int) config('mera.catalog_per_page', 12)))
            ->withQueryString();

        return view('catalog.index', [
            'courses' => $courses,
            'withCategories' => $withCategories,
            'categories' => $withCategories ? Category::tree() : [],
            'categoryPaths' => $withCategories ? Category::pathMap() : [],
        ]);
    }
}
