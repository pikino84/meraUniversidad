<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Corte del seguimiento anual por persona (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md).
 * Trae nombres de personas: solo administradores (rutas del panel).
 */
class ProgressReport extends Model
{
    use LogsActivity;

    protected $fillable = ['title', 'cut_date', 'summary', 'warnings', 'created_by', 'updated_by'];

    protected static function booted(): void
    {
        // Link legible: la fecha de corte (/seguimiento/2026-10-12). Cambia si se edita la fecha.
        static::saving(function (self $report) {
            if ($report->slug === null || $report->isDirty('cut_date')) {
                $report->slug = self::uniqueSlug($report->cut_date->format('Y-m-d'), $report->id);
            }
        });
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base;
        for ($i = 2; self::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Acepta el link nuevo (fecha) y el anterior (número): /seguimiento/1 sigue funcionando. */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? 'slug', $value)->first()
            ?? (ctype_digit((string) $value) ? $this->find((int) $value) : null);
    }

    protected $casts = [
        'cut_date' => 'date',
        'summary' => 'array',
        'warnings' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('seguimiento')
            ->logOnly(['title', 'cut_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function people(): HasMany
    {
        return $this->hasMany(ProgressReportPerson::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProgressReportItem::class);
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(ProgressReportSurvey::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ProgressReportShare::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
