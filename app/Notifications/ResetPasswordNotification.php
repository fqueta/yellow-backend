<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordBase;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPasswordBase
{
    public function toMail($notifiable)
    {
        $frontendUrl = rtrim(env('FRONTEND_URL'), '/');

        return (new MailMessage)
            ->subject('Redefinição de Senha')
            ->greeting('Olá!')
            ->line('Você está recebendo este e-mail porque recebemos um pedido de redefinição de senha para a sua conta.')
            ->action('Redefinir Senha', $frontendUrl . '/reset-password/' . $this->token . '?email=' . urlencode($notifiable->email))
            ->line('Se você não solicitou a redefinição de senha, nenhuma ação adicional é necessária.');
    }
}
