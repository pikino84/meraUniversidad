<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Encuesta de satisfacción de un curso dentro de un corte del seguimiento (§6.1). */
class ProgressReportSurvey extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
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
}
