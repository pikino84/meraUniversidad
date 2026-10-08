<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Genera un slug único a partir de "name", nunca vacío ("¡¿?!" → "curso", "curso-2"…).
 * El modelo puede definir slugFallback() para el caso en que el nombre no produzca slug.
 */
trait HasUniqueSlug
{
    public static function bootHasUniqueSlug(): void
    {
        static::saving(function ($model) {
            if (! $model->slug || $model->isDirty('name')) {
                $model->slug = $model->uniqueSlug($model->name);
            }
        });
    }

    public function uniqueSlug(?string $name): string
    {
        $base = Str::slug((string) $name) ?: $this->slugFallback();
        $base = Str::limit($base, 180, '');
        $slug = $base;
        $i = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()))
            ->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    protected function slugFallback(): string
    {
        return 'item';
    }
}
