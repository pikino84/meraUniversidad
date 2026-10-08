<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use App\Support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Course extends Model
{
    use HasFactory, HasUniqueSlug, LogsActivity;

    public const API_CACHE_KEY = 'api.courses';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'cover_image',
        'path',
        'category_id',
    ];

    protected static function booted(): void
    {
        $flush = function () {
            Cache::forget(self::API_CACHE_KEY);
            Category::flushCache(); // los conteos de cursos por categoría cambian
        };

        static::saved($flush);
        static::deleted($flush);
    }

    protected function slugFallback(): string
    {
        return 'curso';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('cursos')
            ->logOnly(['name', 'slug', 'description', 'category_id', 'cover_image', 'path'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Categoría a la que pertenece el curso
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Búsqueda por nombre escapando los comodines de LIKE.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return Like::contains($query, ['name'], $term);
    }

    /**
     * Filtra por una categoría incluyendo todas sus subcategorías.
     */
    public function scopeInCategoryTree(Builder $query, int|string|null $categoryId): Builder
    {
        if (! $categoryId) {
            return $query;
        }

        return $query->whereIn('category_id', Category::descendantIdsOf((int) $categoryId));
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_image ? self::publicUrl($this->cover_image) : null;
    }

    public function getContentUrlAttribute(): ?string
    {
        return $this->path ? self::publicUrl(rtrim($this->path, '/').'/'.config('mera.courses.entry_file')) : null;
    }

    /**
     * URL pública de un archivo de cursos. Permite servir el contenido desde otro
     * dominio (MERA_COURSES_URL) sin tocar vistas ni API.
     */
    public static function publicUrl(string $relativePath): string
    {
        // url() (no asset()): config/app.php tiene asset_url = '/', que produce rutas relativas.
        $base = config('mera.courses.base_url') ?: url('storage');

        return rtrim($base, '/').'/'.self::encodePath($relativePath);
    }

    /**
     * Ruta pública RELATIVA y sin "/" inicial ("storage/cursos/x/index.html").
     * Formato que espera el WordPress actual, que antepone "https://cursos.meracorporation.com/"
     * (con "/" inicial se generaba el doble slash "…com//storage/…").
     */
    public static function publicPath(string $relativePath): string
    {
        return 'storage/'.self::encodePath($relativePath);
    }

    private static function encodePath(string $relativePath): string
    {
        return implode('/', array_map('rawurlencode', explode('/', ltrim($relativePath, '/'))));
    }
}
