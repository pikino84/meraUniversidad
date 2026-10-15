<?php

namespace App\Models;

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

    /** Comentarios por clasificar (se guarda el conteo en summary para no cargar los textos en las listas). */
    public function pendingCount(): int
    {
        return (int) ($this->summary['pendientes'] ?? 0);
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
