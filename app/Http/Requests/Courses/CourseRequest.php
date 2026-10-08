<?php

namespace App\Http\Requests\Courses;

use Illuminate\Foundation\Http\FormRequest;

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
            'name' => 'nombre del curso',
            'description' => 'descripción',
            'category_id' => 'categoría',
            'cover_image' => 'imagen de portada',
            'zip_file' => 'archivo ZIP',
        ];
    }

    /**
     * @return array{name:string,description:string,category_id:?int}
     */
    public function courseData(): array
    {
        return [
            'name' => trim($this->input('name')),
            'description' => trim($this->input('description')),
            'category_id' => $this->filled('category_id') ? (int) $this->input('category_id') : null,
        ];
    }
}
