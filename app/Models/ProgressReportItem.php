<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persona × curso de un corte del seguimiento. Estados (respuestas de Capacitación, 2026-10-19):
 *  - aprobado:    curso al 100 % y calificación ≥ mínima del curso (sin evaluación: solo el 100 %)
 *  - reprobado:   curso al 100 % y calificación menor a la mínima
 *  - en curso:    empezó y no ha terminado (incluye 100 % sin evaluación presentada)
 *  - sin iniciar: progreso 0
 * "Realizado" = aprobado + reprobado.
 */
class ProgressReportItem extends Model
{
    public const STATUS_PASSED = 1;

    public const STATUS_FAILED = 2;

    public const STATUS_IN_PROGRESS = 3;

    public const STATUS_NOT_STARTED = 4;

    /** Clave en la URL ↔ estado. */
    public const STATUS_KEYS = [
        'aprobados' => self::STATUS_PASSED,
        'reprobados' => self::STATUS_FAILED,
        'en-curso' => self::STATUS_IN_PROGRESS,
        'sin-iniciar' => self::STATUS_NOT_STARTED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PASSED => 'Aprobado',
        self::STATUS_FAILED => 'Reprobado',
        self::STATUS_IN_PROGRESS => 'En curso',
        self::STATUS_NOT_STARTED => 'Sin iniciar',
    ];

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'progress_report_id' => 'integer',
        'progress_report_person_id' => 'integer',
        'catalog_course_id' => 'integer',
        'year' => 'integer',
        'progress' => 'float',
        'grade' => 'float',
        'hours' => 'float',
        'status' => 'integer',
        'started_at' => 'date',
        'completed_at' => 'date',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(ProgressReportPerson::class, 'progress_report_person_id');
    }

    public function catalogCourse(): BelongsTo
    {
        return $this->belongsTo(CatalogCourse::class);
    }

    /**
     * Estado de una asignación según las reglas del curso del catálogo (o las generales si el
     * código no está en el catálogo: 80 y con evaluación).
     */
    public static function statusFor(?CatalogCourse $course, float $progress, ?float $grade): int
    {
        if ($progress <= 0) {
            return self::STATUS_NOT_STARTED;
        }

        $hasEvaluation = $course?->has_evaluation ?? true;
        $passing = $course?->passing_grade ?? (int) config('mera.progress.passing_grade', 80);

        if ($progress < 1 || ($hasEvaluation && $grade === null)) {
            return self::STATUS_IN_PROGRESS;
        }

        return ! $hasEvaluation || $grade >= $passing ? self::STATUS_PASSED : self::STATUS_FAILED;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? '—';
    }
}
