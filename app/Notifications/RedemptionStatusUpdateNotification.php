<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Redemption;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\BrevoChannel;

/**
 * Notificação para informar o cliente sobre atualização de status do resgate
 * Enviada quando o status de um resgate é alterado por um administrador
 */
class RedemptionStatusUpdateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $redemption;
    public $oldStatus;
    public $newStatus;
    public $updatedBy;

    /**
     * Criar uma nova instância da notificação
     *
     * @param Redemption $redemption O resgate que teve o status atualizado
     * @param string $oldStatus Status anterior
     * @param string $newStatus Novo status
     * @param User|null $updatedBy Usuário que fez a atualização
     */
    public function __construct(Redemption $redemption, string $oldStatus, string $newStatus, ?User $updatedBy = null)
    {
        $this->redemption = $redemption;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->updatedBy = $updatedBy;
    }

    /**
     * Obter os canais de entrega da notificação (usa canal Brevo personalizado)
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [BrevoChannel::class];
    }

    /**
     * Construir payload para envio via Brevo.
     *
     * @param object $notifiable Usuário notificado
     * @return array Dados necessários para o BrevoEmailService
     */
    public function toBrevo(object $notifiable): array
    {
        $statusLabels = [
            'pending' => 'Pendente',
            'processing' => 'Em Processamento',
            'confirmed' => 'Confirmado',
            'shipped' => 'Enviado',
            'delivered' => 'Entregue',
            'cancelled' => 'Cancelado'
        ];

        $oldStatusLabel = $statusLabels[$this->oldStatus] ?? $this->oldStatus;
        $newStatusLabel = $statusLabels[$this->newStatus] ?? $this->newStatus;

        $subject = 'Atualização do Status do seu Resgate';
        $htmlContent = $this->buildHtmlContent($notifiable, $oldStatusLabel, $newStatusLabel);

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
     * Gerar conteúdo HTML para e-mail Brevo.
     *
     * @param object $notifiable Usuário notificado
     * @param string $oldStatusLabel Status anterior (humanizado)
     * @param string $newStatusLabel Novo status (humanizado)
     * @return string Conteúdo HTML pronto para envio
     */
    private function buildHtmlContent(object $notifiable, string $oldStatusLabel, string $newStatusLabel): string
    {
        $userName = $notifiable->name ?? $notifiable->email;
        $resId = $this->redemption->id;
        $resDate = method_exists($this->redemption->created_at, 'format')
            ? $this->redemption->created_at->setTimezone(config('app.timezone'))->format('d/m/Y')
            : (string) $this->redemption->created_at;

        return "\n        <!DOCTYPE html>\n        <html>\n        <head>\n            <meta charset='utf-8'>\n            <title>Atualização de Status do Resgate</title>\n            <style>\n                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\n                .container { max-width: 600px; margin: 0 auto; padding: 20px; }\n                .header { background: #2196F3; color: white; padding: 20px; text-align: center; }\n                .content { padding: 20px; background: #f9f9f9; }\n                .footer { padding: 20px; text-align: center; color: #666; }\n                .highlight { background: #e3f2fd; padding: 10px; border-left: 4px solid #2196F3; }\n            </style>\n        </head>\n        <body>\n            <div class='container'>\n                <div class='header'>\n                    <h1>🔔 Atualização do Status do seu Resgate</h1>\n                </div>\n                <div class='content'>\n                    <p>Olá <strong>{$userName}</strong>,</p>\n                    <p>O status do seu resgate foi atualizado.</p>\n                    <div class='highlight'>\n                        <ul>\n                            <li><strong>Status Anterior:</strong> {$oldStatusLabel}</li>\n                            <li><strong>Novo Status:</strong> {$newStatusLabel}</li>\n                            <li><strong>ID do Resgate:</strong> #{$resId}</li>\n                            <li><strong>Data do Resgate:</strong> {$resDate}</li>\n                        </ul>\n                    </div>\n                    " . ($this->updatedBy ? "<p>Atualizado por: <strong>" . htmlspecialchars($this->updatedBy->name) . "</strong> (" . htmlspecialchars($this->updatedBy->email) . ")</p>" : "") . "\n                    <p>Qualquer dúvida, entre em contato com nosso suporte.</p>\n                </div>\n                <div class='footer'>\n                    <p>Este é um email automático, não responda.</p>\n                </div>\n            </div>\n        </body>\n        </html>\n        ";
    }

    /**
     * (Opcional) Fallback para email padrão do Laravel.
     * Mantido por compatibilidade, mas não utilizado quando via() usa Brevo.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $statusLabels = [
            'pending' => 'Pendente',
            'processing' => 'Em Processamento',
            'confirmed' => 'Confirmado',
            'shipped' => 'Enviado',
            'delivered' => 'Entregue',
            'cancelled' => 'Cancelado'
        ];

        $oldStatusLabel = $statusLabels[$this->oldStatus] ?? $this->oldStatus;
        $newStatusLabel = $statusLabels[$this->newStatus] ?? $this->newStatus;

        return (new MailMessage)
            ->subject('Atualização do Status do seu Resgate')
            ->markdown('emails.redemption-status-update', [
                'redemption' => $this->redemption,
                'oldStatus' => $this->oldStatus,
                'newStatus' => $this->newStatus,
                'oldStatusLabel' => $oldStatusLabel,
                'newStatusLabel' => $newStatusLabel,
                'updatedBy' => $this->updatedBy,
                'user' => $notifiable,
            ]);
    }

    /**
     * Obter a representação em array da notificação
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'redemption_status_update',
            'redemption_id' => $this->redemption->id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'updated_by' => $this->updatedBy ? [
                'id' => $this->updatedBy->id,
                'name' => $this->updatedBy->name,
                'email' => $this->updatedBy->email,
            ] : null,
            'updated_at' => now()->toISOString(),
        ];
    }
}