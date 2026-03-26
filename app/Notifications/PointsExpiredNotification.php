<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\BrevoChannel;

class PointsExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $expiredCount;
    public ?string $tenantId;
    public string $date;

    public function __construct(int $expiredCount, ?string $tenantId = null)
    {
        $this->expiredCount = $expiredCount;
        $this->tenantId = $tenantId;
        $this->date = now()->format('d/m/Y');
    }

    public function via(object $notifiable): array
    {
        return [BrevoChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Expira├º├úo de Pontos - Relat├│rio')
            ->markdown('emails.points-expired', [
                'expiredCount' => $this->expiredCount,
                'tenantId' => $this->tenantId,
                'date' => $this->date,
            ]);
    }

    public function toBrevo(object $notifiable): array
    {
        $to = [[
            'email' => $notifiable->email,
            'name' => $notifiable->name ?? $notifiable->email
        ]];

        $subject = 'Expira├º├úo de Pontos - Relat├│rio';
        $htmlContent = $this->buildHtml();

        return [
            'to' => $to,
            'subject' => $subject,
            'htmlContent' => $htmlContent,
        ];
    }

    private function buildHtml(): string
    {
        $tenantInfo = $this->tenantId ? "Tenant: {$this->tenantId}" : 'Sistema Principal';

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Expira├º├úo de Pontos</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .stats { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 15px 0; }
                .footer { padding: 20px; text-align: center; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📅 Expira├º├úo de Pontos</h1>
                </div>
                <div class='content'>
                    <p>Uma opera├º├úo de expira├º├úo de pontos foi executada.</p>
                    
                    <div class='stats'>
                        <h3>Resultados</h3>
                        <ul>
                            <li><strong>Pontos expirados:</strong> {$this->expiredCount}</li>
                            <li><strong>Data da execu├º├úo:</strong> {$this->date}</li>
                            <li><strong>{$tenantInfo}</strong></li>
                        </ul>
                    </div>

                    <p>Este ├® um email autom├ítico, n├úo responda.</p>
                </div>
                <div class='footer'>
                    <p>Point Store - Sistema de Fidelidade</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'points_expired',
            'expired_count' => $this->expiredCount,
            'tenant_id' => $this->tenantId,
            'date' => $this->date,
        ];
    }
}
