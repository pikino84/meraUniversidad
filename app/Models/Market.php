<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Versión por país de un curso: México, Latam, EUA… (docs/carpetas/INSTRUCTIVO_VERSIONES.md §3.1).
 * Editable en el panel (solo super admin). Su slug es el primer segmento de la URL pública
 * (/eua/<carpeta>), por eso no puede coincidir con una carpeta ni con una ruta de la app.
 */
class Market extends Model
{
    use HasFactory, LogsActivity;

    public const CACHE_KEY = 'markets.all';

    protected $fillable = ['code', 'name', 'slug', 'language', 'hreflang', 'parent_id', 'position', 'is_active'];

    protected $attributes = [
        'language' => 'es',
        'is_active' => true,
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'position' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        $flush = function () {
            Cache::forget(self::CACHE_KEY);
            Cache::forget(Course::API_CACHE_KEY);
        };

        static::saved($flush);
        static::deleted($flush);

        static::creating(function (Market $market) {
            if (! $market->position) {
                $market->position = (int) static::max('position') + 1;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('versiones')
            ->logOnly(['code', 'name', 'slug', 'language', 'hreflang', 'parent_id', 'position', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CourseGroupVersion::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }

    /**
     * Todas las versiones, en orden, desde una consulta cacheada (se usan en casi cada página
     * del panel y para resolver URLs públicas).
     *
     * @return \Illuminate\Support\Collection<int, Market>
     */
    public static function cached()
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::ordered()->get());
    }

    public static function findBySlug(?string $slug): ?self
    {
        return $slug ? self::cached()->firstWhere('slug', $slug) : null;
    }

    /** Versión por defecto de /<carpeta> (config mera.versions.default_market, México). */
    public static function defaultMarket(): ?self
    {
        return self::byCode((string) config('mera.versions.default_market'));
    }

    /** La versión con ese código (MX, US…) o, si no existe, la primera activa. */
    public static function byCode(string $code): ?self
    {
        $all = self::cached();

        return $all->firstWhere('code', $code) ?? $all->firstWhere('is_active', true) ?? $all->first();
    }

    /**
     * Slugs de versiones: no los puede tomar una carpeta (serían ambiguos en /<versión>/<carpeta>).
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        try {
            return Schema::hasTable('markets') ? self::cached()->pluck('slug')->all() : [];
        } catch (\Throwable) {
            return []; // sin BD (p. ej. al cachear rutas en un build)
        }
    }

    /** Nombre con su padre, p. ej. "Panamá (Latam)". */
    public function label(): string
    {
        return $this->parent ? "{$this->name} ({$this->parent->name})" : $this->name;
    }
}
