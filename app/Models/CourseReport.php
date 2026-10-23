<?php

namespace App\Models;

use App\Services\Reports\SurveyAnswerClassifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Corte de reporte de una carpeta de curso (docs/reportes/INSTRUCTIVO_REPORTES.md).
 * Solo agregados: el `data` nunca lleva nombres ni números de empleado.
 */
class CourseReport extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['title', 'cut_date', 'survey'];

    protected $casts = [
        'course_group_id' => 'integer',
        'course_group_version_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'cut_date' => 'date',
        'data' => 'array',
        'summary' => 'array',
        'warnings' => 'array',
        'pending' => 'array',
        'shared_at' => 'datetime',
    ];

    /** Los datos son pesados (MB): no se cargan en las listas salvo que se pidan. */
    protected $hidden = ['data', 'pending', 'share_token'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(CourseGroup::class, 'course_group_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CourseGroupVersion::class, 'course_group_version_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isShared(): bool
    {
        return filled($this->share_token);
    }

    public function shareUrl(): ?string
    {
        return $this->isShared() ? route('reports.shared', $this->share_token) : null;
    }

    /**
     * Comentarios que piden revisión de una persona: con texto y SIN tema propuesto (2026-10-23; antes
     * contaba todos, también "." y "N/A"). El conteo va en summary['sin_tema']; los cortes anteriores
     * lo calculan si el texto ya está cargado.
     */
    public function pendingCount(): int
    {
        if (array_key_exists('sin_tema', $this->summary ?? [])) {
            return (int) $this->summary['sin_tema'];
        }

        return array_key_exists('pending', $this->attributes)
            ? count($this->reviewQueue()['sin-tema'])
            : (int) ($this->summary['pendientes'] ?? 0);
    }

    /**
     * Cortes anteriores al 2026-10-23: guarda en summary el conteo "sin tema" (y quita de la lista los
     * comentarios vacíos) la primera vez que se abren. Solo datos derivados: no cambia ninguna cifra.
     */
    public function rememberReviewCount(): void
    {
        if (array_key_exists('sin_tema', $this->summary ?? []) || $this->pending === null) {
            return;
        }

        $queue = $this->reviewQueue();
        $keep = array_values(array_intersect_key($this->pending, $queue['sin-tema'] + $queue['con-tema']));
        $this->forceFill([
            'pending' => $keep ?: null,
            'summary' => ['pendientes' => count($keep), 'sin_tema' => count($queue['sin-tema'])] + ($this->summary ?? []),
        ])->saveQuietly();
    }

    /**
     * Comentarios por revisar, con su índice en `pending` y los temas que hoy tienen. Los que no dicen
     * nada ("", ".", "N/A", "nada") no se muestran: no hay nada que clasificar.
     *
     * @return array{sin-tema: array<int, array>, con-tema: array<int, array>}
     */
    public function reviewQueue(): array
    {
        $columns = $this->data['encuesta']['columns'] ?? [];
        $rows = $this->data['encuesta']['rows'] ?? [];
        $queue = ['sin-tema' => [], 'con-tema' => []];

        foreach ($this->pending ?? [] as $index => $item) {
            if (! SurveyAnswerClassifier::meaningful($item['liked'] ?? '') && ! SurveyAnswerClassifier::meaningful($item['improve'] ?? '', true)) {
                continue;
            }

            $row = isset($rows[$item['row']]) ? array_combine($columns, $rows[$item['row']]) : [];
            $codes = array_keys(array_filter($row, fn ($v, $k) => $v === 1 && preg_match('/^(m\d+|v\d+|v_todo)$/', $k), ARRAY_FILTER_USE_BOTH));
            $queue[$codes ? 'con-tema' : 'sin-tema'][$index] = $item + ['codes' => $codes];
        }

        return $queue;
    }

    /** Temas del corte (los que se usaron al armarlo; Higiene tiene los suyos, el resto el catálogo base). */
    public function topicCatalog(): array
    {
        return array_map(fn ($r) => [$r[0], $r[1], $r[2]], $this->data['catalogo']['rows'] ?? []);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('reportes')
            ->logOnly(['title', 'cut_date', 'shared_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
