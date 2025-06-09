<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lounge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'terminal',
        'capacity',
        'status',
    ];

    // Relación con usuarios
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    // Relación con agencias
    public function agencies()
    {
        return $this->belongsToMany(Agency::class);
    }

    // Relación con reservas
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
