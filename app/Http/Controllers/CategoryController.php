<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Listado de categorías principales
     */
    // CategoryController
    public function index()
    {
        $categories = Category::tree();
        return view('categories.index', compact('categories'));
    }

    /**
     * Formulario creación
     */
    public function create(Request $request)
    {
        $categories = Category::tree();
        $parent = $request->parent;

        return view('categories.create', compact('categories', 'parent'));
    }


    /**
     * Guardar categoría
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255'
            ],
            'parent_id' => [
                'nullable',
                'exists:categories,id'
            ]
        ]);

        Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'parent_id' => $request->parent_id
        ]);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Categoría creada correctamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Editar categoría
     */
    public function edit(Category $category)
    {
        $categories = Category::tree();
        $excludedIds = $category->getDescendantIds(); // incluye al propio $category

        return view('categories.edit', compact('category', 'categories', 'excludedIds'));
    }

    /**
     * Actualizar categoría
     */
    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($category) {
                    if ($value && $category->getDescendantIds()->contains((int) $value)) {
                        $fail('No puedes asignar como padre a esta misma categoría o a una de sus subcategorías.');
                    }
                },
            ],
        ]);

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'parent_id' => $request->parent_id,
        ]);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Categoría actualizada correctamente');
    }

    /**
     * Eliminar categoría
     */
    public function destroy(Category $category)
    {

        $category->delete();


        return redirect()

            ->route('categories.index')

            ->with(
                'success',
                'Categoría eliminada correctamente'
            );
    }
}
