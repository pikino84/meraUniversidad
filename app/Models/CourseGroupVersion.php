<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Una versión de un curso: carpeta + año + país (docs/carpetas/INSTRUCTIVO_VERSIONES.md §3.2).
 * Tiene sus módulos en orden, su código del catálogo, su página pública (se publica por
 * versión) y sus cortes de reporte.
 */
class CourseGroupVersion extends Model
{
    use LogsActivity;

    protected $fillable = [
        'year', 'market_id', 'catalog_code', 'title', 'description', 'evaluation_url', 'survey_url', 'position',
    ];

    protected $attributes = [
        'is_published' => false,
    ];

    protected $casts = [
        'course_group_id' => 'integer',
        'market_id' => 'integer',
        'year' => 'integer',
        'position' => 'integer',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(Course::API_CACHE_KEY);

        static::saved($flush);
        static::deleted($flush);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('carpetas')
            ->logOnly(['year', 'market_id', 'catalog_code', 'title', 'description', 'evaluation_url', 'survey_url', 'is_published'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CourseGroup::class, 'course_group_id');
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Course::class, 'course_group_version_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CourseReport::class, 'course_group_version_id');
    }

    /** Orden del panel: año más reciente primero y, dentro del año, el orden de las versiones. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('year')
            ->orderBy(Market::select('position')->whereColumn('markets.id', 'course_group_versions.market_id'))
            ->orderBy('id');
    }

    /** Idioma de la página pública (el de la versión: EUA = inglés). */
    public function language(): string
    {
        return $this->market?->language ?? 'es';
    }

    /** "2026 · México" */
    public function label(): string
    {
        return $this->year.' · '.($this->market?->name ?? '—');
    }

    /**
     * Curso del catálogo por su código (no se guarda en la versión): de ahí salen el alcance
     * General/Específico y la mínima para aprobar (INSTRUCTIVO_REPORTE_PERSONAS.md §7.3).
     */
    public function catalogCourse(): ?CatalogCourse
    {
        return filled($this->catalog_code) ? CatalogCourse::resolve($this->catalog_code) : null;
    }

    /** Título de la página: el propio de la versión o, si va vacío, el de la carpeta. */
    public function displayTitle(): string
    {
        return filled($this->title) ? $this->title : (string) $this->group?->name;
    }

    /**
     * Link público: /<carpeta>/<año>/<versión> (p. ej. /higiene/2026/mexico), igual que la ruta del
     * panel. Siempre lleva año y versión: el link compartido no cambia al crear un año nuevo.
     */
    public function publicUrl(): string
    {
        return route('course-pages.version', [
            'group' => $this->group->slug,
            'year' => $this->year,
            'market' => $this->market->slug,
        ]);
    }

    /** Clave del avance por dispositivo (localStorage) en la página pública. */
    public function storageKey(): string
    {
        return "carpeta:{$this->group->slug}:{$this->year}:{$this->market->slug}";
    }

    /**
     * Clave anterior a las versiones (carpeta:<slug>:es / :en). La página la lee una vez para no
     * perder el avance de quien ya había empezado; solo aplica a la versión que heredó ese idioma.
     */
    public function legacyStorageKey(): ?string
    {
        $language = array_search($this->market->code, (array) config('mera.versions.legacy_language_markets'), true);

        return $language ? "carpeta:{$this->group->slug}:{$language}" : null;
    }
}
