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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
