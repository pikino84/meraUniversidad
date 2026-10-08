<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Category extends Model
{
    use HasFactory, HasUniqueSlug, LogsActivity;

    /**
     * Toda la jerarquía se arma en memoria desde UNA consulta cacheada; se invalida
     * al guardar/borrar categorías o cursos.
     */
    public const CACHE_KEY = 'categories.flat';

    /** Memo por request para no leer la caché en cada fila de una tabla. */
    private static ?array $flatMemo = null;

    private static ?array $pathMemo = null;

    protected $fillable = [
        'name',
        'slug',
        'parent_id',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => self::flushCache());
        static::deleted(fn () => self::flushCache());
    }

    protected function slugFallback(): string
    {
        return 'categoria';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('categorias')
            ->logOnly(['name', 'slug', 'parent_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Categoría padre
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Categorías hijas directas
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('name');
    }

    /**
     * Cursos pertenecientes a esta categoría
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /* ==================== Árbol cacheado ==================== */

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(Course::API_CACHE_KEY); // la API muestra la ruta de categoría
        self::$flatMemo = null;
        self::$pathMemo = null;
    }

    /**
     * Lista plana de todas las categorías con su conteo de cursos (cacheada).
     *
     * @return array<int, array{id:int,name:string,slug:string,parent_id:?int,courses_count:int}>
     */
    public static function flat(): array
    {
        return self::$flatMemo ??= Cache::rememberForever(self::CACHE_KEY, fn () => self::query()
            ->withCount('courses')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'parent_id' => $c->parent_id,
                'courses_count' => (int) $c->courses_count,
            ])
            ->keyBy('id')
            ->all());
    }

    /**
     * Árbol de categorías raíz con la relación "childrenRecursive" ya resuelta
     * (las vistas recorren $category->childrenRecursive sin generar consultas).
     */
    public static function tree(): EloquentCollection
    {
        $nodes = collect(self::flat())->map(function (array $row) {
            $model = (new static)->newFromBuilder($row);
            $model->setAttribute('courses_count', $row['courses_count']);

            return $model;
        });

        $byParent = $nodes->groupBy(fn ($n) => $n->parent_id ?? 0);

        $build = function ($parentId) use (&$build, $byParent) {
            return new EloquentCollection(
                ($byParent->get($parentId) ?? collect())->map(function ($node) use ($build) {
                    $node->setRelation('childrenRecursive', $build($node->id));

                    return $node;
                })->values()->all()
            );
        };

        return $build(0);
    }

    /**
     * Mapa id => "Padre > Hijo > Nieto".
     *
     * @return array<int,string>
     */
    public static function pathMap(): array
    {
        if (self::$pathMemo !== null) {
            return self::$pathMemo;
        }

        $flat = self::flat();
        $paths = [];

        foreach ($flat as $id => $row) {
            $names = [];
            $cursor = $row;
            $guard = 0;

            while ($cursor && $guard++ < 50) {
                array_unshift($names, $cursor['name']);
                $cursor = $cursor['parent_id'] ? ($flat[$cursor['parent_id']] ?? null) : null;
            }

            $paths[$id] = implode(' > ', $names);
        }

        return self::$pathMemo = $paths;
    }

    /**
     * IDs de la categoría y todos sus descendientes (sin consultas extra).
     */
    public static function descendantIdsOf(int $id): Collection
    {
        $byParent = collect(self::flat())->groupBy('parent_id');
        $ids = collect();
        $stack = [$id];

        while ($stack) {
            $current = array_pop($stack);

            if ($ids->contains($current)) {
                continue; // protección ante ciclos
            }

            $ids->push($current);

            foreach ($byParent->get($current, []) as $child) {
                $stack[] = $child['id'];
            }
        }

        return $ids;
    }

    public function getDescendantIds(): Collection
    {
        return self::descendantIdsOf($this->id);
    }

    /**
     * Ruta completa: Tecnología > Programación > Laravel
     */
    public function getFullPathAttribute(): string
    {
        return self::pathMap()[$this->id] ?? $this->name;
    }

    /**
     * Impacto de borrar esta categoría (para la confirmación en pantalla).
     *
     * @return array{subcategories:int,courses:int}
     */
    public function deletionImpact(): array
    {
        $ids = $this->getDescendantIds();
        $flat = self::flat();

        return [
            'subcategories' => $ids->count() - 1,
            'courses' => $ids->sum(fn ($id) => $flat[$id]['courses_count'] ?? 0),
        ];
    }
}
