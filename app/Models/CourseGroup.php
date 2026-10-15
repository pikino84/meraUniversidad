<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use App\Support\ProgramCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Carpeta de curso: agrupa módulos (registros de `courses`) por idioma y en orden, y puede
 * publicar su página pública: /<slug> (español) y /en/<slug> (inglés). Nace apagada.
 */
class CourseGroup extends Model
{
    use HasFactory, HasUniqueSlug, LogsActivity;

    /** Idiomas fijos de esta fase (CLAUDE.md §10). El primero es el idioma por defecto. */
    public const LANGUAGES = [
        'es' => 'Español',
        'en' => 'Inglés',
    ];

    /** Formato válido del link: minúsculas, números y guiones. */
    public const SLUG_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    protected $fillable = [
        'name', 'name_en', 'slug', 'position',
        'description_es', 'description_en',
        'evaluation_url_es', 'evaluation_url_en',
        'survey_url_es', 'survey_url_en',
    ];

    /** Las páginas nacen apagadas. */
    protected $attributes = [
        'is_published' => false,
    ];

    protected $casts = [
        'position' => 'integer',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(Course::API_CACHE_KEY);

        static::saved($flush);
        static::deleted($flush);

        static::creating(function (CourseGroup $group) {
            if ($group->position === null || $group->position === 0) {
                $group->position = (int) static::max('position') + 1;
            }
        });
    }

    protected function slugFallback(): string
    {
        return 'curso';
    }

    /** El slug es el link público: renombrar la carpeta NO lo cambia (no rompe links compartidos). */
    protected function slugFollowsName(): bool
    {
        return false;
    }

    /**
     * Links que no puede tomar una carpeta: rutas de la app (login, cursos, dashboard…),
     * carpetas reales de public/ (images, css, storage…) y links de programas en config.
     */
    public function slugIsReserved(string $slug): bool
    {
        return in_array($slug, self::reservedSlugs(), true);
    }

    /**
     * @return list<string>
     */
    public static function reservedSlugs(): array
    {
        $reserved = ['en', 'es', 'admin', 'api', 'storage', 'build', 'index', 'index-php'];

        foreach (Route::getRoutes() as $route) {
            $first = explode('/', trim($route->uri(), '/'))[0] ?? '';
            if ($first !== '' && ! str_starts_with($first, '{')) {
                $reserved[] = $first;
            }
        }

        foreach (File::glob(public_path('*')) as $entry) {
            $reserved[] = strtolower(pathinfo($entry, PATHINFO_FILENAME));
        }

        return array_values(array_unique(array_merge($reserved, ProgramCatalog::slugs())));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('carpetas')
            ->logOnly([
                'name', 'name_en', 'slug', 'position', 'is_published',
                'description_es', 'description_en',
                'evaluation_url_es', 'evaluation_url_en', 'survey_url_es', 'survey_url_en',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Course::class, 'course_group_id');
    }

    /** Cortes del reporte de avance, evaluación y satisfacción (docs/reportes/INSTRUCTIVO_REPORTES.md). */
    public function reports(): HasMany
    {
        return $this->hasMany(CourseReport::class, 'course_group_id');
    }

    public static function languageLabel(?string $code): string
    {
        return self::LANGUAGES[$code] ?? 'Sin idioma';
    }

    /* ==================== Página pública ==================== */

    /** URL pública de un idioma: /<slug> (es) o /en/<slug>. */
    public function publicUrl(string $language = 'es'): string
    {
        return $language === 'es'
            ? route('course-pages.show', $this)
            : route('course-pages.show.lang', ['language' => $language, 'courseGroup' => $this]);
    }

    public function titleFor(string $language): string
    {
        return $language === 'en' && filled($this->name_en) ? $this->name_en : $this->name;
    }

    public function descriptionFor(string $language): ?string
    {
        $value = $this->{"description_{$language}"} ?? null;

        return filled($value) ? $value : null;
    }

    /**
     * Link de evaluación o encuesta del idioma; si no tiene, el de español.
     */
    public function actionUrl(string $type, string $language): ?string
    {
        $own = $this->{"{$type}_url_{$language}"} ?? null;

        return filled($own) ? $own : ($this->{"{$type}_url_es"} ?: null);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
