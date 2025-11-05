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

class RedemptionSuccessNotification extends Notification implements ShouldQueue
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
            ->subject('Resgate de Pontos Realizado com Sucesso!')
            ->markdown('emails.redemption-success', [
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
     * @param object $notifiable User receiving the notification
     * @return array Payload with 'to', 'subject' and 'htmlContent'
     */
    public function toBrevo(object $notifiable): array
    {
        $to = [[
            'email' => $notifiable->email,
            'name' => $notifiable->name ?? $notifiable->email
        ]];

        $subject = 'Resgate de Pontos Realizado com Sucesso!';
        $htmlContent = $this->buildRedemptionSuccessHtml(
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
     * Generate HTML content for Brevo transactional email (client view).
     *
     * @param object $user
     * @param object $product
     * @param object $redemption
     * @param int $quantity
     * @param int $pointsUsed
     * @return string
     */
    private function buildRedemptionSuccessHtml(
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
            <title>Resgate Realizado com Sucesso</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 20px; text-align: center; color: #666; }
                .highlight { background: #e8f5e8; padding: 10px; border-left: 4px solid #4CAF50; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Resgate Realizado com Sucesso!</h1>
                </div>
                <div class='content'>
                    <p>Olá <strong>{$userName}</strong>,</p>
                    <p>Seu resgate foi processado com sucesso! Aqui estão os detalhes:</p>

                    <div class='highlight'>
                        <h3>Detalhes do Resgate</h3>
                        <ul>
                            <li><strong>Produto:</strong> {$productName}</li>
                            <li><strong>Quantidade:</strong> {$quantity}</li>
                            <li><strong>Pontos Utilizados:</strong> {$pointsUsed}</li>
                            <li><strong>CPF:</strong> {$user->cpf}</li>
                            <li><strong>ID do Resgate:</strong> #{$redemption->id}</li>
                            <li><strong>Data:</strong> " . $redemption->created_at->setTimezone(config('app.timezone'))->format('d/m/Y') . "</li>
                        </ul>
                    </div>

                    <p>Em breve você receberá mais informações sobre a entrega do seu produto.</p>
                    <p>Obrigado por usar nosso sistema de pontos!</p>
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
            'type' => 'redemption_success',
            'user_id' => $this->user->id,
            'product_id' => $this->product->ID,
            'product_name' => $this->product->post_title,
            'redemption_id' => $this->redemption->id,
            'quantity' => $this->quantity,
            'points_used' => $this->pointsUsed,
            'redeemed_at' => $this->redemption->created_at->toISOString(),
        ];
    }
}
