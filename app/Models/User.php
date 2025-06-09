<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = ['name', 'last_name', 'email', 'phone', 'password'];

    public function lounges()
    {
        return $this->belongsToMany(Lounge::class);
    }
    public function agency()
    {
        return $this->belongsTo(\App\Models\Agency::class);
    }
    public function agent()
    {
        return $this->hasOne(Agent::class);
    }
}
