<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super admin';

    public const ROLE_ADMIN = 'admin';

    /** Roles con acceso al panel. */
    public const PANEL_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN];

    protected $fillable = ['name', 'last_name', 'email', 'phone', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        // Nunca registrar la contraseña ni el token.
        return LogOptions::defaults()
            ->useLogName('usuarios')
            ->logOnly(['name', 'last_name', 'email', 'phone'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    public static function superAdminCount(): int
    {
        return static::role(self::ROLE_SUPER_ADMIN)->count();
    }
}
