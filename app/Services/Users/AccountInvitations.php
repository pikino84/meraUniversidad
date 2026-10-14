<?php

namespace App\Services\Users;

use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * Envía el link para crear o cambiar la contraseña (U24). Usa el broker "invitations"
 * (misma tabla que "olvidé mi contraseña", pero con vigencia más larga).
 */
class AccountInvitations
{
    /**
     * @return bool true si el correo salió; false si falló el envío (queda en el log).
     */
    public function send(User $user, bool $isNewAccount): bool
    {
        try {
            $token = Password::broker('invitations')->createToken($user);
            $user->notify(new AccountInvitation($token, $isNewAccount));

            return true;
        } catch (Throwable $e) {
            Log::error('No se pudo enviar el link de contraseña', ['user_id' => $user->id, 'exception' => $e]);

            return false;
        }
    }
}
