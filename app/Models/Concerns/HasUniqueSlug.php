<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Genera un slug único a partir de "name", nunca vacío ("¡¿?!" → "curso", "curso-2"…).
 * El modelo puede definir:
 *  - slugFallback(): slug cuando el nombre no produce ninguno.
 *  - slugFollowsName(): false si el slug NO debe cambiar al renombrar (p. ej. es un link público).
 *  - slugIsReserved($slug): true si el slug no se puede usar aunque esté libre en la tabla.
 */
trait HasUniqueSlug
{
    public static function bootHasUniqueSlug(): void
    {
        static::saving(function ($model) {
            if (! $model->slug || ($model->isDirty('name') && $model->slugFollowsName())) {
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

        // Con borrado suave, los registros en la papelera conservan su slug (índice único en BD).
        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive(static::class), true);

        while ($this->slugIsReserved($slug) || ($usesSoftDeletes ? static::withTrashed() : static::query())
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

    protected function slugFollowsName(): bool
    {
        return true;
    }

    public function slugIsReserved(string $slug): bool
    {
        return false;
    }
}
