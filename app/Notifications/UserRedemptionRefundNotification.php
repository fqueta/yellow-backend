<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Redemption;
use App\Models\User;

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
     * Canais de entrega da notificação
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Conteúdo do e-mail enviado ao cliente
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
}