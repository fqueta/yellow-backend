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
 * Notificação por e-mail para o cliente sobre extorno de resgate
 * Envia um e-mail informando o cancelamento e o crédito dos pontos
 */
class UserRedemptionRefundNotification extends Notification implements ShouldQueue
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
     * @param int $pointsCredited Pontos creditados no extorno
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
     * @param object $notifiable Cliente destinatário
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        return [BrevoChannel::class];
    }

    /**
     * Conteúdo do e-mail enviado ao cliente (fallback Laravel Mail)
     * Mantido por compatibilidade quando o canal Brevo não estiver ativo.
     *
     * @param object $notifiable Cliente destinatário
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $productName = $this->redemption->product->post_title ?? 'Produto';
        $redemptionCode = \App\Services\Qlib::redeem_id($this->redemption->id);
        $subject = 'Seu resgate foi cancelado e os pontos foram devolvidos';

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Olá!')
            ->line('Seu pedido de resgate foi cancelado e os pontos foram devolvidos ao seu saldo.')
            ->line('Detalhes do resgate:')
            ->line('• Código do resgate: ' . $redemptionCode)
            ->line('• Produto: ' . $productName)
            ->line('• Pontos devolvidos: ' . number_format($this->pointsCredited, 0, ',', '.'))
            ->line('• Status: cancelado');

        if ($this->reason) {
            $mail->line('Motivo informado: ' . $this->reason);
        }
        if ($this->updatedBy) {
            $mail->line('Processado por: ' . $this->updatedBy->name);
        }

        $mail->line('Qualquer dúvida, fale com nosso suporte.');

        return $mail;
    }

    /**
     * Construir payload para envio via Brevo
     *
     * @param object $notifiable Cliente destinatário
     * @return array Payload com 'to', 'subject' e 'htmlContent'
     */
    public function toBrevo($notifiable): array
    {
        $productName = $this->redemption->product->post_title ?? 'Produto';
        $redemptionCode = \App\Services\Qlib::redeem_id($this->redemption->id);
        $subject = 'Seu resgate foi cancelado e os pontos foram devolvidos';
        $htmlContent = $this->buildHtmlContentUser($notifiable, $productName, $redemptionCode);

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
     * Gerar conteúdo HTML para e-mail do cliente via Brevo
     *
     * @param object $notifiable Cliente destinatário
     * @param string $productName Nome do produto
     * @param string $redemptionCode Código do resgate
     * @return string Conteúdo HTML pronto para envio
     */
    private function buildHtmlContentUser($notifiable, string $productName, string $redemptionCode): string
    {
        $userName = $notifiable->name ?? $notifiable->email;
        $points = number_format($this->pointsCredited, 0, ',', '.');
        $updatedByHtml = $this->updatedBy ? "<p>Processado por: <strong>" . htmlspecialchars($this->updatedBy->name) . "</strong> (" . htmlspecialchars($this->updatedBy->email) . ")</p>" : "";
        $reasonHtml = $this->reason ? "<p>Motivo informado: <strong>" . htmlspecialchars($this->reason) . "</strong></p>" : "";

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Resgate cancelado – pontos devolvidos</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f44336; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 20px; text-align: center; color: #666; }
                .highlight { background: #fdecea; padding: 10px; border-left: 4px solid #f44336; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>❗ Resgate cancelado</h1>
                </div>
                <div class='content'>
                    <p>Olá <strong>{$userName}</strong>,</p>
                    <p>Seu pedido de resgate foi cancelado e os pontos foram devolvidos ao seu saldo.</p>
                    <div class='highlight'>
                        <ul>
                            <li><strong>Código do resgate:</strong> {$redemptionCode}</li>
                            <li><strong>Produto:</strong> {$productName}</li>
                            <li><strong>Pontos devolvidos:</strong> {$points}</li>
                            <li><strong>Status:</strong> cancelado</li>
                        </ul>
                    </div>
                    {$reasonHtml}
                    {$updatedByHtml}
                    <p>Qualquer dúvida, fale com nosso suporte.</p>
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