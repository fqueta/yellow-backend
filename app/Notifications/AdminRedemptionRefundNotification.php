<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Redemption;
use App\Models\User;

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
     * Canais de entrega da notificação
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Conteúdo do e-mail enviado ao administrador
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
}