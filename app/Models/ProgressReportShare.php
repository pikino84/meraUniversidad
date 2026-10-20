<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Link para compartir un corte del seguimiento sin sesión (§7.2.7): fijo en una localidad (o toda la
 * empresa), sin nombres, sin la consulta de colaboradores y sin comparar localidades.
 */
class ProgressReportShare extends Model
{
    protected $fillable = ['progress_report_id', 'locality', 'token', 'created_by'];

    // Llaves como entero: en el MySQL del servidor pueden llegar como texto y `=== $report->id` fallaría.
    protected $casts = [
        'progress_report_id' => 'integer',
        'created_by' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $share) => $share->token ??= Str::random(48));
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProgressReport::class, 'progress_report_id');
    }

    public function url(): string
    {
        return route('progress.shared', $this->token);
    }

    public function label(): string
    {
        return $this->locality ?? 'Toda la empresa';
    }
}
