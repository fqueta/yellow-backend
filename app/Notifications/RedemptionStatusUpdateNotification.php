<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Redemption;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
     * Obter os canais de entrega da notificação
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Obter a representação de email da notificação
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