<?php

namespace App\Http\Requests\Courses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta (POST) y edición (PUT/PATCH) de cursos. En edición la portada y el ZIP son opcionales.
 */
class CourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware de rol de la ruta
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $config = config('mera.courses');

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            // Carpeta de curso (CLAUDE.md §10): opcional; si hay carpeta, año y versión son obligatorios
            // (docs/carpetas/INSTRUCTIVO_VERSIONES.md). La versión se crea si no existe.
            'course_group_id' => ['nullable', 'integer', 'exists:course_groups,id'],
            'year' => ['nullable', 'required_with:course_group_id', 'integer', 'between:2020,2100'],
            'market_id' => ['nullable', 'required_with:course_group_id', 'integer', Rule::exists('markets', 'id')->where('is_active', true)],
            'position' => ['nullable', 'integer', 'min:1', 'max:999'],
            // "image" acepta SVG (riesgo XSS); por eso se limita a mimes concretos.
            'cover_image' => [
                $creating ? 'required' : 'nullable',
                'file',
                'mimes:'.implode(',', $config['cover_mimes']),
                'max:'.$config['cover_max_kb'],
            ],
            'zip_file' => [
                $creating ? 'required' : 'nullable',
                'file',
                'mimes:zip',
                'max:'.$config['max_zip_kb'],
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre del módulo',
            'description' => 'descripción',
            'category_id' => 'categoría',
            'course_group_id' => 'carpeta de curso',
            'year' => 'año',
            'market_id' => 'versión',
            'position' => 'posición',
            'cover_image' => 'imagen de portada',
            'zip_file' => 'archivo ZIP',
        ];
    }

    /**
     * @return array{name:string,description:string,category_id?:?int}
     */
    public function courseData(): array
    {
        $data = [
            'name' => trim($this->input('name')),
            'description' => trim($this->input('description')),
        ];

        // Con las categorías ocultas el formulario no manda el campo: no se toca la categoría
        // que ya tenga el módulo (si se mandara null, se borraría al editar).
        if (config('mera.categories_enabled')) {
            $data['category_id'] = $this->filled('category_id') ? (int) $this->input('category_id') : null;
        }

        return $data;
    }

    public function messages(): array
    {
        return [
            'year.required_with' => 'Escribe el año del módulo en la carpeta.',
            'market_id.required_with' => 'Elige la versión del módulo (México, Latam, EUA…).',
        ];
    }

    /**
     * Ubicación del módulo: carpeta + año + versión y posición (sin carpeta → "Sin carpeta").
     *
     * @return array{group:?int,year:?int,market:?int,position:?int}
     */
    public function placement(): array
    {
        $group = $this->filled('course_group_id') ? (int) $this->input('course_group_id') : null;

        return [
            'group' => $group,
            'year' => $group ? (int) $this->input('year') : null,
            'market' => $group ? (int) $this->input('market_id') : null,
            'position' => $group && $this->filled('position') ? (int) $this->input('position') : null,
        ];
    }
}
