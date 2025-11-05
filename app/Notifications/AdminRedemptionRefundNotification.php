<?php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Redemption;
use App\Models\User;
use App\Notifications\Channels\BrevoChannel;

/**
 * Notificação por e-mail para administradores sobre extorno de resgate
 * Informa cancelamento do resgate e crédito dos pontos ao cliente
 */
class AdminRedemptionRefundNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $redemption;
    protected $pointsCredited;
    protected $reason;
    protected $updatedBy;

    /**
     * Construtor da notificação
     *
     * @param Redemption $redemption Resgate extornado
     * @param int $pointsCredited Pontos creditados
     * @param string|null $reason Motivo do extorno
     * @param User|null $updatedBy Usuário que realizou o extorno
     */
    public function __construct(Redemption $redemption, int $pointsCredited, ?string $reason = null, ?User $updatedBy = null)
    {
        $this->redemption = $redemption;
        $this->pointsCredited = $pointsCredited;
        $this->reason = $reason;
        $this->updatedBy = $updatedBy;
    }

    /**
     * Canais de entrega da notificação (Brevo)
     *
     * @param object $notifiable Destinatário da notificação
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        return [BrevoChannel::class];
    }

    /**
     * Conteúdo do e-mail enviado ao administrador (fallback Laravel Mail)
     * Mantido por compatibilidade quando o canal Brevo não estiver ativo.
     *
     * @param object $notifiable Destinatário
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $productName = $this->redemption->product->post_title ?? 'Produto';
        $clientName = $this->redemption->user->name ?? 'Cliente';
        $clientEmail = $this->redemption->user->email ?? 'Email não informado';
        $redemptionCode = \App\Services\Qlib::redeem_id($this->redemption->id);

        $mail = (new MailMessage)
            ->subject('Resgate cancelado (extorno) - pontos devolvidos')
            ->greeting('Olá, Admin!')
            ->line('Um resgate foi cancelado e os pontos foram devolvidos ao cliente.')
            ->line('Detalhes:')
            ->line('• Código do resgate: ' . $redemptionCode)
            ->line('• Cliente: ' . $clientName . ' (' . $clientEmail . ')')
            ->line('• Produto: ' . $productName)
            ->line('• Pontos devolvidos: ' . number_format($this->pointsCredited, 0, ',', '.'))
            ->line('• Status atualizado para: cancelado');

        if ($this->reason) {
            $mail->line('Motivo do extorno: ' . $this->reason);
        }
        if ($this->updatedBy) {
            $mail->line('Processado por: ' . $this->updatedBy->name);
        }

        return $mail;
    }

    /**
     * Construir payload para envio via Brevo
     *
     * @param object $notifiable Admin destinatário
     * @return array Payload com 'to', 'subject' e 'htmlContent'
     */
    public function toBrevo($notifiable): array
    {
        $productName = $this->redemption->product->post_title ?? 'Produto';
        $clientName = $this->redemption->user->name ?? 'Cliente';
        $clientEmail = $this->redemption->user->email ?? 'Email não informado';
        $redemptionCode = \App\Services\Qlib::redeem_id($this->redemption->id);

        $subject = 'Resgate cancelado (extorno) - pontos devolvidos';
        $htmlContent = $this->buildHtmlContentAdmin($notifiable, $productName, $clientName, $clientEmail, $redemptionCode);

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
     * Gerar conteúdo HTML para e-mail de administrador via Brevo
     *
     * @param object $notifiable Admin destinatário
     * @param string $productName Nome do produto
     * @param string $clientName Nome do cliente
     * @param string $clientEmail Email do cliente
     * @param string $redemptionCode Código do resgate
     * @return string Conteúdo HTML pronto para envio
     */
    private function buildHtmlContentAdmin($notifiable, string $productName, string $clientName, string $clientEmail, string $redemptionCode): string
    {
        $points = number_format($this->pointsCredited, 0, ',', '.');
        $updatedByHtml = $this->updatedBy ? "<p>Processado por: <strong>" . htmlspecialchars($this->updatedBy->name) . "</strong> (" . htmlspecialchars($this->updatedBy->email) . ")</p>" : "";
        $reasonHtml = $this->reason ? "<p>Motivo do extorno: <strong>" . htmlspecialchars($this->reason) . "</strong></p>" : "";

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Resgate cancelado (extorno) - pontos devolvidos</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #FF9800; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 20px; text-align: center; color: #666; }
                .alert { background: #fff3cd; padding: 10px; border-left: 4px solid #FF9800; }
                .user-info { background: #e3f2fd; padding: 10px; border-left: 4px solid #2196F3; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔔 Resgate cancelado (extorno)</h1>
                </div>
                <div class='content'>
                    <div class='alert'>
                        <p>Um resgate foi cancelado e os pontos foram devolvidos ao cliente.</p>
                    </div>
                    <div class='user-info'>
                        <h3>Informações do Cliente</h3>
                        <ul>
                            <li><strong>Nome:</strong> {$clientName}</li>
                            <li><strong>Email:</strong> {$clientEmail}</li>
                        </ul>
                    </div>
                    <h3>Detalhes do Resgate</h3>
                    <ul>
                        <li><strong>Código:</strong> {$redemptionCode}</li>
                        <li><strong>Produto:</strong> {$productName}</li>
                        <li><strong>Pontos devolvidos:</strong> {$points}</li>
                        <li><strong>Status atualizado para:</strong> cancelado</li>
                    </ul>
                    {$reasonHtml}
                    {$updatedByHtml}
                </div>
                <div class='footer'>
                    <p>Este é um email automático, não responda.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}