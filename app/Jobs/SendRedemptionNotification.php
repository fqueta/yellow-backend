<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Product;
use App\Models\Redemption;
use App\Notifications\RedemptionSuccessNotification;
use App\Notifications\AdminRedemptionNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendRedemptionNotification implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $user;
    public $product;
    public $redemption;
    public $quantity;
    public $pointsUsed;

    /**
     * Create a new job instance.
     */
    public function __construct($user, $product, $redemption, int $quantity, int $pointsUsed)
    {
        $this->user = $user;
        $this->product = $product;
        $this->redemption = $redemption;
        $this->quantity = $quantity;
        $this->pointsUsed = $pointsUsed;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Registrar execução do job
            Log::info('Job de notificação de resgate iniciado', [
                'user_email' => $this->user->email ?? 'N/A',
                'product_name' => $this->product->name ?? 'N/A',
                'quantity' => $this->quantity,
                'points_used' => $this->pointsUsed
            ]);

            // Por enquanto, apenas simular o envio de notificações
            // TODO: Implementar envio real de emails quando o sistema estiver configurado

            if ($this->user instanceof User) {
                // Enviar notificação real para o usuário
                $this->user->notify(new RedemptionSuccessNotification(
                    $this->user,
                    $this->product,
                    $this->redemption,
                    $this->quantity,
                    $this->pointsUsed
                ));

                // Enviar para administradores
                $admins = User::where('permission_id','<=', 2)
                            //  ->orWhere('email', 'like', '%admin%')
                             ->get();

                foreach ($admins as $admin) {
                    $admin->notify(new AdminRedemptionNotification(
                        $this->user,
                        $this->product,
                        $this->redemption,
                        $this->quantity,
                        $this->pointsUsed
                    ));
                    Log::info('Job de notificação de resgate concluído com sucesso para admin', [
                        'admin_email' => $admin->email
                    ]);
                }
            }

            Log::info('Job de notificação de resgate concluído com sucesso');

        } catch (\Exception $e) {
            Log::error('Erro ao processar job de notificação de resgate', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}
