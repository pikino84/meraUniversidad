<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Rota contraseñas de usuarios generando una aleatoria segura que se muestra UNA sola vez.
 *
 *   php artisan mera:rotate-password jesus.castro@meracorporation.com
 *   php artisan mera:rotate-password --super-admins
 *
 * También invalida el "recordarme" (remember_token) para cerrar sesiones persistentes.
 */
class RotatePassword extends Command
{
    protected $signature = 'mera:rotate-password
        {email?* : Correo(s) de los usuarios}
        {--super-admins : Rotar a todos los Super Admin}
        {--length=20 : Longitud de la contraseña generada}';

    protected $description = 'Genera y asigna contraseñas nuevas (se muestran una sola vez)';

    public function handle(): int
    {
        $users = $this->option('super-admins')
            ? User::role(User::ROLE_SUPER_ADMIN)->orderBy('email')->get()
            : User::whereIn('email', $this->argument('email'))->orderBy('email')->get();

        if ($users->isEmpty()) {
            $this->error('No se encontró ningún usuario. Indica correos o usa --super-admins.');

            return self::FAILURE;
        }

        $this->table(['Usuario', 'Correo'], $users->map(fn ($u) => [$u->name, $u->email]));

        if (! $this->confirm("¿Rotar la contraseña de {$users->count()} usuario(s)?", false)) {
            return self::INVALID;
        }

        $rows = [];
        $length = max(12, (int) $this->option('length'));

        foreach ($users as $user) {
            $password = Str::password($length, symbols: false);

            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            $rows[] = [$user->email, $password];
        }

        $this->newLine();
        $this->warn('Contraseñas nuevas (cópialas ahora; no se vuelven a mostrar ni se guardan en logs):');
        $this->table(['Correo', 'Contraseña nueva'], $rows);
        $this->line('Entrégalas por un canal privado y pide que cada persona la cambie en "Mi perfil".');

        return self::SUCCESS;
    }
}
