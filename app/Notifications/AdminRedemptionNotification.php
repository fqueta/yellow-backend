<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Product;
use App\Models\Redemption;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\BrevoChannel;

class AdminRedemptionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $user;
    public $product;
    public $redemption;
    public $quantity;
    public $pointsUsed;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user, Product $product, Redemption $redemption, int $quantity, int $pointsUsed)
    {
        $this->user = $user;
        $this->product = $product;
        $this->redemption = $redemption;
        $this->quantity = $quantity;
        $this->pointsUsed = $pointsUsed;
    }

    /**
     * Get the notification's delivery channels (Brevo).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [BrevoChannel::class];
    }

    /**
     * Get the mail representation of the notification (fallback Laravel Mail).
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Novo Resgate de Pontos Realizado - Ação Necessária')
            ->markdown('emails.admin-redemption', [
                'user' => $this->user,
                'product' => $this->product,
                'redemption' => $this->redemption,
                'quantity' => $this->quantity,
                'pointsUsed' => $this->pointsUsed,
            ]);
    }

    /**
     * Build Brevo payload for transactional email.
     *
     * @param object $notifiable Admin receiving the notification
     * @return array Payload with 'to', 'subject' and 'htmlContent'
     */
    public function toBrevo(object $notifiable): array
    {
        $to = [[
            'email' => $notifiable->email,
            'name' => $notifiable->name ?? $notifiable->email
        ]];

        $subject = 'Novo Resgate de Pontos Realizado - Ação Necessária';
        $htmlContent = $this->buildAdminRedemptionHtml(
            $this->user,
            $this->product,
            $this->redemption,
            $this->quantity,
            $this->pointsUsed
        );

        return [
            'to' => $to,
            'subject' => $subject,
            'htmlContent' => $htmlContent,
        ];
    }

    /**
     * Generate HTML content for Brevo transactional email (admin view).
     *
     * @param object $user
     * @param object $product
     * @param object $redemption
     * @param int $quantity
     * @param int $pointsUsed
     * @return string
     */
    private function buildAdminRedemptionHtml(
        $user,
        $product,
        $redemption,
        int $quantity,
        int $pointsUsed
    ): string {
        $productName = $product->post_title ?? $product->name ?? 'Produto';
        $userName = $user->name ?? $user->email;

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Novo Resgate - Ação Necessária</title>
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
                    <h1>🔔 Novo Resgate Realizado</h1>
                </div>
                <div class='content'>
                    <div class='alert'>
                        <h3>⚠️ Ação Necessária</h3>
                        <p>Um novo resgate foi realizado e requer sua atenção.</p>
                    </div>

                    <div class='user-info'>
                        <h3>Informações do Cliente</h3>
                        <ul>
                            <li><strong>Nome:</strong> {$userName}</li>
                            <li><strong>Email:</strong> {$user->email}</li>
                            <li><strong>CPF:</strong> {$user->cpf}</li>
                            <li><strong>ID do Cliente:</strong> #{$user->id}</li>
                        </ul>
                    </div>

                    <h3>Detalhes do Resgate</h3>
                    <ul>
                        <li><strong>Produto:</strong> {$productName}</li>
                        <li><strong>Quantidade:</strong> {$quantity}</li>
                        <li><strong>Pontos Utilizados:</strong> {$pointsUsed}</li>
                        <li><strong>ID do Resgate:</strong> #{$redemption->id}</li>
                        <li><strong>Status:</strong> {$redemption->status}</li>
                        <li><strong>Data:</strong> " . $redemption->created_at->setTimezone(config('app.timezone'))->format('d/m/Y') . "</li>
                    </ul>

                    <p><strong>Próximos passos:</strong></p>
                    <ol>
                        <li>Verificar disponibilidade do produto</li>
                        <li>Processar a entrega</li>
                        <li>Atualizar o status do resgate</li>
                        <li>Notificar o cliente sobre o andamento</li>
                    </ol>
                </div>
                <div class='footer'>
                    <p>Este é um email automático, não responda.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'admin_redemption_alert',
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
            'product_id' => $this->product->ID,
            'product_name' => $this->product->post_title,
            'redemption_id' => $this->redemption->id,
            'quantity' => $this->quantity,
            'points_used' => $this->pointsUsed,
            'status' => $this->redemption->status,
            'redeemed_at' => $this->redemption->created_at->toISOString(),
        ];
    }
}
