<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Crea los Super Admin iniciales SIN credenciales en el código.
 *
 *   SEED_SUPERADMIN_EMAILS="uno@empresa.com,dos@empresa.com"
 *   SEED_SUPERADMIN_PASSWORD=    (opcional; si falta se genera una aleatoria y se muestra una vez)
 *
 * No modifica usuarios existentes (no cambia contraseñas), solo asegura el rol.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $emails = array_filter(array_map('trim', explode(',', (string) env('SEED_SUPERADMIN_EMAILS', ''))));

        if ($emails === []) {
            $this->command?->warn('SEED_SUPERADMIN_EMAILS vacío: no se creó ningún Super Admin.');

            return;
        }

        foreach ($emails as $email) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                $password = env('SEED_SUPERADMIN_PASSWORD') ?: Str::password(16);

                $user = User::create([
                    'name' => 'Super Admin',
                    'email' => $email,
                    'password' => Hash::make($password),
                ]);

                if (! env('SEED_SUPERADMIN_PASSWORD')) {
                    $this->command?->info("Super Admin {$email} creado. Contraseña temporal: {$password}");
                }
            }

            $user->assignRole(User::ROLE_SUPER_ADMIN);
        }
    }
}
