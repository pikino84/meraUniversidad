<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Persona de un corte del seguimiento (dato personal: solo administradores). */
class ProgressReportPerson extends Model
{
    public $timestamps = false;

    protected $table = 'progress_report_people';

    protected $guarded = ['id'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProgressReport::class, 'progress_report_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProgressReportItem::class);
    }
}
