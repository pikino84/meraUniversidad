<?php

namespace App\Http\Requests\Categories;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Category|null $category */
        $category = $this->route('category');
        $parentId = $this->filled('parent_id') ? (int) $this->input('parent_id') : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // Sin hermanos con el mismo nombre (sí se permite "Básico" bajo padres distintos).
                Rule::unique('categories', 'name')
                    ->where(fn ($q) => $parentId ? $q->where('parent_id', $parentId) : $q->whereNull('parent_id'))
                    ->ignore($category?->id),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($category) {
                    if ($category && $value && $category->getDescendantIds()->contains((int) $value)) {
                        $fail('No puedes asignar como padre a esta misma categoría o a una de sus subcategorías.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una categoría con ese nombre en el mismo nivel.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'parent_id' => 'categoría padre'];
    }

    /**
     * @return array{name:string,parent_id:?int}
     */
    public function categoryData(): array
    {
        return [
            'name' => trim($this->input('name')),
            'parent_id' => $this->filled('parent_id') ? (int) $this->input('parent_id') : null,
        ];
    }
}
