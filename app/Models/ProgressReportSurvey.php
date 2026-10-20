<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Encuesta de satisfacción de un curso dentro de un corte del seguimiento (§6.1). */
class ProgressReportSurvey extends Model
{
    protected $guarded = ['id'];

    // Llaves como entero: en el MySQL del servidor pueden llegar como texto y `=== $report->id` fallaría.
    protected $casts = [
        'progress_report_id' => 'integer',
        'catalog_course_id' => 'integer',
        'created_by' => 'integer',
        'data' => 'array',
        'warnings' => 'array',
        'responses' => 'integer',
        'active' => 'integer',
        'not_in_cut' => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProgressReport::class, 'progress_report_id');
    }

    public function catalogCourse(): BelongsTo
    {
        return $this->belongsTo(CatalogCourse::class);
    }

    public function indicator(string $key): mixed
    {
        return $this->data['indicators'][$key] ?? null;
    }

    /**
     * Temas por tipo (valora / mejora) con su número de menciones y su % sobre quienes respondieron esa
     * pregunta, el más mencionado primero; más los comentarios sin tema.
     *
     * @return array{valora: list<array>, mejora: list<array>, liked_n: int, improve_n: int, unclassified: int, unreviewed: int}|null
     */
    public function topicSummary(): ?array
    {
        $topics = $this->data['topics'] ?? null;

        if (! $topics) {
            return null;
        }

        $counts = array_count_values(array_merge(...array_map(fn ($a) => $a['c'], $topics['answers'] ?: [['c' => []]])));
        $out = ['valora' => [], 'mejora' => [], 'liked_n' => $topics['liked_n'], 'improve_n' => $topics['improve_n']];

        foreach ($topics['catalog'] as [$code, $type, $label]) {
            $n = $counts[$code] ?? 0;
            $base = $type === 'valora' ? $topics['liked_n'] : $topics['improve_n'];
            if ($n > 0) {
                $out[$type][] = ['code' => $code, 'label' => $label, 'n' => $n, 'pct' => $base ? round($n / $base * 100, 1) : 0];
            }
        }

        usort($out['valora'], fn ($a, $b) => $b['n'] <=> $a['n']);
        usort($out['mejora'], fn ($a, $b) => $b['n'] <=> $a['n']);

        return $out + [
            'unclassified' => count(array_filter($topics['answers'], fn ($a) => $a['c'] === [])),
            'unreviewed' => count(array_filter($topics['answers'], fn ($a) => ! $a['r'])),
        ];
    }
}
