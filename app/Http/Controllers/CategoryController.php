<?php

namespace App\Http\Controllers;

use App\Http\Requests\Categories\CategoryRequest;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * Árbol de categorías
     */
    public function index(): View
    {
        return view('categories.index', ['categories' => Category::tree()]);
    }

    public function create(Request $request): View
    {
        return view('categories.create', [
            'categories' => Category::tree(),
            'parent' => $request->integer('parent') ?: null,
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($request->categoryData());

        return redirect()
            ->route('categories.index')
            ->with('success', 'Categoría creada correctamente.');
    }

    public function edit(Category $category): View
    {
        return view('categories.edit', [
            'category' => $category,
            'categories' => Category::tree(),
            'excludedIds' => $category->getDescendantIds(), // incluye a la propia categoría
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->categoryData());

        return redirect()
            ->route('categories.index')
            ->with('success', 'Categoría actualizada correctamente.');
    }

    /**
     * Elimina la categoría y sus subcategorías (cascade); sus cursos quedan "Sin categoría".
     * La vista advierte del impacto antes de confirmar.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $impact = $category->deletionImpact();
        $ids = $category->getDescendantIds();

        // Explícito (no depende de que el motor de BD tenga el ON DELETE SET NULL / CASCADE).
        DB::transaction(function () use ($category, $ids) {
            Course::whereIn('category_id', $ids)->update(['category_id' => null]);
            Category::whereIn('id', $ids)->where('id', '!=', $category->id)->orderByDesc('id')->get()->each->delete();
            $category->delete();
        });

        Category::flushCache();
        Cache::forget(Course::API_CACHE_KEY);

        $detail = $impact['subcategories'] || $impact['courses']
            ? " Se eliminaron {$impact['subcategories']} subcategorías y {$impact['courses']} cursos quedaron sin categoría."
            : '';

        return redirect()
            ->route('categories.index')
            ->with('success', "Categoría «{$category->name}» eliminada.{$detail}");
    }
}
