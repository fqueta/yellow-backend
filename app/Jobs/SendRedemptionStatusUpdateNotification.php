<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Redemption;
use App\Notifications\RedemptionStatusUpdateNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job para enviar notificações de atualização de status de resgate
 * Processa o envio de emails através de fila quando o status de um resgate é alterado
 */
class SendRedemptionStatusUpdateNotification implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $redemption;
    public $oldStatus;
    public $newStatus;
    public $updatedBy;

    /**
     * Criar uma nova instância do job
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
     * Executar o job de notificação
     * Envia email para o cliente informando sobre a mudança de status
     */
    public function handle(): void
    {
        try {
            // Registrar execução do job
            Log::info('Job de notificação de atualização de status iniciado', [
                'redemption_id' => $this->redemption->id,
                'user_email' => $this->redemption->user->email ?? 'N/A',
                'old_status' => $this->oldStatus,
                'new_status' => $this->newStatus,
                'updated_by' => $this->updatedBy->email ?? 'Sistema'
            ]);

            // Carregar o usuário do resgate
            $user = $this->redemption->user;
            
            if ($user) {
                // Enviar notificação para o cliente
                $user->notify(new RedemptionStatusUpdateNotification(
                    $this->redemption,
                    $this->oldStatus,
                    $this->newStatus,
                    $this->updatedBy
                ));

                Log::info('Notificação de atualização de status enviada com sucesso', [
                    'user_email' => $user->email,
                    'redemption_id' => $this->redemption->id,
                    'new_status' => $this->newStatus
                ]);
            } else {
                Log::warning('Usuário não encontrado para o resgate', [
                    'redemption_id' => $this->redemption->id
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Erro ao processar job de notificação de atualização de status', [
                'redemption_id' => $this->redemption->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Determinar quantas vezes o job deve ser tentado
     */
    public function tries(): int
    {
        return 3;
    }

    /**
     * Determinar o tempo limite para o job
     */
    public function timeout(): int
    {
        return 60;
    }
}