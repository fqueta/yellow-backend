<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Product;
use App\Models\Redemption;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
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
