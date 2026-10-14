<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use App\Support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Course extends Model
{
    // SoftDeletes: el módulo borrado queda en la Papelera (F2) hasta que se purga.
    use HasFactory, HasUniqueSlug, LogsActivity, SoftDeletes;

    public const API_CACHE_KEY = 'api.courses';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'cover_image',
        'path',
        'category_id',
        'course_group_id',
        'language',
        'position',
    ];

    protected $casts = [
        'course_group_id' => 'integer',
        'position' => 'integer',
        'trash_items' => 'array',
    ];

    protected static function booted(): void
    {
        $flush = function () {
            Cache::forget(self::API_CACHE_KEY);
            Category::flushCache(); // los conteos de cursos por categoría cambian
        };

        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
    }

    protected function slugFallback(): string
    {
        return 'curso';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('cursos')
            ->logOnly(['name', 'slug', 'description', 'category_id', 'course_group_id', 'language', 'position', 'cover_image', 'path'])
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
     * Carpeta de curso (módulo → curso, CLAUDE.md §10)
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CourseGroup::class, 'course_group_id');
    }

    /**
     * Número de módulo escrito en el título ("MODULE 2", "Módulo 3"), o null si no lo trae.
     * Sirve para avisar cuando no coincide con la posición en la carpeta (CLAUDE.md §12, F3).
     */
    public function titleModuleNumber(): ?int
    {
        return preg_match('/\b(?:m[oó]dulo|module)\s*(\d{1,3})\b/iu', (string) $this->name, $m) ? (int) $m[1] : null;
    }

    /**
     * Módulos en el orden de su carpeta: posición y, como desempate, nombre.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw('position is null')->orderBy('position')->orderBy('name');
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
