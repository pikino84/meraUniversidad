<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Solo crea el registro (sin archivos). Para pruebas con archivos usar CourseManager.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        $folder = 'curso-'.Str::random(10);

        return [
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'description' => fake()->sentence(),
            'cover_image' => "cursos/{$folder}-cover-abc123.png",
            'path' => "cursos/{$folder}",
            'category_id' => null,
        ];
    }
}
