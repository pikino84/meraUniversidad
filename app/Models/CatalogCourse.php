<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Curso del catálogo de Capacitación (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md §6).
 *
 * Código: RUTA-SUB-###-MOD-AA[-PAÍS], p. ej. NRM-HIG-002-EL-26 o NEG-OPE-007-EL-26-MX. En la plataforma
 * aparece como "CÓDIGO | Título"; si la plataforma usa otro código, va en `aliases`.
 * Aprobado = curso al 100 % y calificación ≥ passing_grade (sin evaluación: solo el 100 %).
 */
class CatalogCourse extends Model
{
    use LogsActivity;

    protected $table = 'course_catalog';

    /** Ruta del catálogo → prefijo del código. */
    public const ROUTES = [
        'Normativo' => 'NRM',
        'Negocio' => 'NEG',
        'Competencias' => 'CMP',
        'Desarrollo Personal' => 'DPR',
    ];

    /** Alcance (§7.3): general = para todos; específico = ciertas áreas o puestos. */
    public const SCOPE_GENERAL = 'general';

    public const SCOPE_SPECIFIC = 'specific';

    public const CODE_PATTERN = '/^(NRM|NEG|CMP|DPR)-[A-Z]{3}-\d{3}-(EL|BL|PR)-\d{2}(-[A-Z]{2,})?$/';

    protected $fillable = [
        'code', 'route', 'sub', 'number', 'modality', 'year', 'country', 'title', 'short_title',
        'reference', 'competency', 'level', 'scope', 'hours', 'instructor',
        'aliases', 'passing_grade', 'has_evaluation', 'in_tracking',
    ];

    protected $casts = [
        'number' => 'integer',
        'year' => 'integer',
        'hours' => 'float',
        'aliases' => 'array',
        'passing_grade' => 'integer',
        'has_evaluation' => 'boolean',
        'in_tracking' => 'boolean',
    ];

    /** @var array<string,int>|null código o alias → id (por petición) */
    private static ?array $codeMap = null;

    protected static function booted(): void
    {
        static::saved(fn () => self::$codeMap = null);
        static::deleted(fn () => self::$codeMap = null);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalogo')
            ->logOnly(['code', 'short_title', 'scope', 'aliases', 'passing_grade', 'has_evaluation', 'in_tracking'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('year')->orderBy('code');
    }

    /**
     * Código a partir de las columnas del Excel (la columna "Nomenclatura" es una fórmula).
     */
    public static function buildCode(string $route, string $sub, int $number, string $modality, int $year, ?string $country): ?string
    {
        $prefix = self::ROUTES[$route] ?? null;

        if ($prefix === null) {
            return null;
        }

        $country = strtoupper(trim((string) $country));
        $code = sprintf('%s-%s-%03d-%s-%02d', $prefix, strtoupper($sub), $number, strtoupper($modality), $year % 100)
            .($country !== '' && $country !== 'GLOBAL' ? '-'.$country : '');

        return preg_match(self::CODE_PATTERN, $code) ? $code : null;
    }

    /**
     * Código del inicio de un nombre de la plataforma: "NRM-HIG-002-EL-26 | Introducción…".
     */
    public static function codeFromName(?string $name): ?string
    {
        $code = strtoupper(trim(explode('|', (string) $name, 2)[0]));

        return preg_match(self::CODE_PATTERN, $code) ? $code : null;
    }

    /**
     * Curso del catálogo para un código de la plataforma (código propio o alterno).
     */
    public static function resolve(string $code): ?self
    {
        self::$codeMap ??= self::query()->get(['id', 'code', 'aliases'])
            ->flatMap(fn (self $c) => collect([$c->code, ...($c->aliases ?? [])])->mapWithKeys(fn ($k) => [$k => $c->id]))
            ->all();

        $id = self::$codeMap[strtoupper(trim($code))] ?? null;

        return $id ? self::find($id) : null;
    }

    /** ¿Realizado? Curso al 100 % y, si tiene evaluación, presentada (cualquier calificación). */
    public function done(float $progress, ?float $grade): bool
    {
        return $progress >= 1 && (! $this->has_evaluation || $grade !== null);
    }

    /** ¿Aprobado? Realizado y, si tiene evaluación, calificación ≥ mínima. */
    public function passes(float $progress, ?float $grade): bool
    {
        return $progress >= 1 && (! $this->has_evaluation || ($grade !== null && $grade >= $this->passing_grade));
    }

    public function isGeneral(): bool
    {
        return $this->scope !== self::SCOPE_SPECIFIC;
    }

    public function scopeLabel(): string
    {
        return match ($this->scope) {
            self::SCOPE_GENERAL => 'General',
            self::SCOPE_SPECIFIC => 'Específico',
            default => 'Sin alcance',
        };
    }

    /**
     * Alcance desde el texto del Excel ("General", "Específico"); null si viene vacío o con otro valor.
     */
    public static function scopeFrom(mixed $value): ?string
    {
        $text = Str::lower(Str::ascii(trim((string) $value)));

        return match (true) {
            str_starts_with($text, 'general') => self::SCOPE_GENERAL,
            str_starts_with($text, 'especific'), str_starts_with($text, 'specific') => self::SCOPE_SPECIFIC,
            default => null,
        };
    }

    /** Respaldo si el Excel no trae "Alcance": "Todo MERA" en Nivel = general; lo demás, específico. */
    public static function scopeFromLevel(?string $level): string
    {
        return Str::lower(Str::ascii(trim((string) $level))) === 'todo mera' ? self::SCOPE_GENERAL : self::SCOPE_SPECIFIC;
    }

    public function countryLabel(): string
    {
        return $this->country ?: 'Global';
    }
}
