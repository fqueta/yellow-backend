<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordBase;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Channels\BrevoChannel;
use App\Services\Qlib;

class ResetPasswordNotification extends ResetPasswordBase
{
    /**
     * Canais de entrega da notificação (Brevo)
     *
     * @param object $notifiable Usuário destinatário
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        return [BrevoChannel::class];
    }

    /**
     * Conteúdo do e-mail (fallback Laravel Mail)
     * Mantido por compatibilidade quando o canal Brevo não estiver ativo.
     *
     * @param object $notifiable Usuário destinatário
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        // Obtém a URL do frontend do .env e o tempo de expiração do token
        $frontendUrl = rtrim(Qlib::qoption('frontend_url'), '/');
        $passwordBroker = config('auth.defaults.passwords');
        $expireMinutes = (int) config("auth.passwords.$passwordBroker.expire", 60);

        return (new MailMessage)
            ->subject('Redefinição de Senha')
            ->greeting('Olá!')
            ->line('Você está recebendo este e-mail porque recebemos um pedido de redefinição de senha para a sua conta.')
            ->line("Importante: este link expira em {$expireMinutes} minutos.")
            ->action('Redefinir Senha', $frontendUrl . '/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->email))
            ->line('Se o botão acima não funcionar, copie e cole o link diretamente no seu navegador.')
            ->line('Se você não solicitou a redefinição de senha, nenhuma ação adicional é necessária.');
    }

    /**
     * Construir payload para envio via Brevo.
     *
     * @param object $notifiable Usuário destinatário
     * @return array Payload com 'to', 'subject' e 'htmlContent'
     */
    public function toBrevo($notifiable): array
    {
        $frontendUrl = rtrim(Qlib::qoption('frontend_url'), '/');
        $resetUrl = $frontendUrl . '/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->email);
        $subject = 'Redefinição de Senha';
        $htmlContent = $this->buildResetHtmlContent($notifiable, $resetUrl);

        return [
            'to' => [[
                'email' => $notifiable->email,
                'name' => $notifiable->name ?? $notifiable->email,
            ]],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
        ];
    }

    /**
     * Gerar conteúdo HTML para e-mail de redefinição de senha via Brevo.
     *
     * @param object $notifiable Usuário destinatário
     * @param string $resetUrl URL para redefinição de senha
     * @return string Conteúdo HTML pronto para envio
     */
    /**
     * Gerar conteúdo HTML para e-mail de redefinição de senha via Brevo.
     * Inclui tempo de expiração do token e link do FRONTEND_URL.
     *
     * @param object $notifiable Usuário destinatário
     * @param string $resetUrl URL para redefinição de senha
     * @return string Conteúdo HTML pronto para envio
     */
    private function buildResetHtmlContent($notifiable, string $resetUrl): string
    {
        $userName = $notifiable->name ?? $notifiable->email;
        $safeUrl = htmlspecialchars($resetUrl);
        $frontendBase = rtrim(Qlib::qoption('frontend_url'), '/');
        $passwordBroker = config('auth.defaults.passwords');
        $expireMinutes = (int) config("auth.passwords.$passwordBroker.expire", 60);

        $safeFrontend = htmlspecialchars($frontendBase);

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Redefinição de Senha</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1976D2; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 20px; text-align: center; color: #666; }
                .btn { display: inline-block; background: #1976D2; color: #fff; padding: 12px 18px; text-decoration: none; border-radius: 4px; }
                .note { background: #fff3cd; padding: 10px; border-left: 4px solid #ffb300; margin: 16px 0; }
                .list { margin: 8px 0; padding-left: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Redefinição de Senha</h1>
                </div>
                <div class='content'>
                    <p>Olá, <strong>" . htmlspecialchars($userName) . "</strong>!</p>
                    <p>Recebemos um pedido para redefinir a senha da sua conta.</p>
                    <p>Para continuar, clique no botão abaixo:</p>
                    <p style='text-align:center;'>
                        <a class='btn' href='" . $safeUrl . "' target='_blank' rel='noopener'
                           style='display:inline-block;background:#1976D2;color:#ffffff !important;padding:12px 18px;text-decoration:none;border-radius:4px;font-weight:600;'>
                            Redefinir Senha
                        </a>
                    </p>
                    <p>Se o botão acima não funcionar, copie e cole este link no seu navegador:</p>
                    <p><a href='" . $safeUrl . "' target='_blank' rel='noopener'>" . $safeUrl . "</a></p>

                    <div class='note'>
                        <strong>Importante:</strong>
                        <ul class='list'>
                            <li>Este link expira em <strong>" . $expireMinutes . " minutos</strong>.</li>
                            <li>Por segurança, não compartilhe este link com terceiros.</li>
                            <li>Após redefinir sua senha, acesse o sistema pelo link abaixo.</li>
                        </ul>
                    </div>

                    <p>Link do sistema: <a href='" . $safeFrontend . "' target='_blank' rel='noopener'>" . $safeFrontend . "</a></p>
                    <p>Se você não solicitou a redefinição de senha, nenhuma ação adicional é necessária.</p>
                </div>
                <div class='footer'>
                    <p>Este é um e-mail automático, por favor não responda.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
