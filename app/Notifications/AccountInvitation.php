<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo con el link para crear (o cambiar) la contraseña de una cuenta del panel
 * (CLAUDE.md §12, U24). El administrador nunca define ni conoce la contraseña.
 */
class AccountInvitation extends Notification
{
    public function __construct(
        private readonly string $token,
        private readonly bool $isNewAccount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hours = (int) round(config('auth.passwords.invitations.expire') / 60);
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
            'invitacion' => 1,
        ]);

        return (new MailMessage)
            ->subject($this->isNewAccount ? 'Crea tu contraseña de Mera Universidad' : 'Cambia tu contraseña de Mera Universidad')
            ->greeting("Hola, {$notifiable->name}")
            ->line($this->isNewAccount
                ? 'Se creó tu cuenta en el panel de Mera Universidad. Para entrar, primero crea tu contraseña.'
                : 'Un administrador te envió un link para cambiar la contraseña de tu cuenta de Mera Universidad.')
            ->action($this->isNewAccount ? 'Crear mi contraseña' : 'Cambiar mi contraseña', $url)
            ->line("El link vence en {$hours} horas. Si vence, pide a un administrador que te envíe otro.")
            ->line('Si no esperabas este correo, ignóralo: tu cuenta no cambia hasta que uses el link.')
            ->salutation('Mera Universidad');
    }
}
