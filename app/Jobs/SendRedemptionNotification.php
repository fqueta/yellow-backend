<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\BrevoEmailService;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Exception;

class SendRedemptionNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $product;
    public $redemption;
    public $quantity;
    public $pointsUsed;

    /**
     * Create a new job instance.
     */
    public function __construct($user, $product, $redemption, $quantity, $pointsUsed)
    {
        $this->user = $user;
        $this->product = $product;
        $this->redemption = $redemption;
        $this->quantity = $quantity;
        $this->pointsUsed = $pointsUsed;
    }

    /**
     * Execute the job.
     *
     * Envia:
     * - Notificação de sucesso ao usuário do resgate;
     * - Notificação aos administradores (permission_id <= 2);
     * - Notificação ao grupo (permission_id = 5) quando o produto é da categoria 3.
     */
    public function handle(): void
    {
        try {
            $brevoService = new BrevoEmailService();

            // Enviar notificação de sucesso para o usuário
            $userEmailResult = $brevoService->sendRedemptionSuccessNotification(
                $this->user,
                $this->product,
                $this->redemption,
                $this->quantity,
                $this->pointsUsed
            );

            if ($userEmailResult['success']) {
                Log::info('Email de resgate enviado para usuário via Brevo', [
                    'user_id' => $this->user->id,
                    'message_id' => $userEmailResult['message_id'],
                    'email' => $this->user->email
                ]);
            }

            // Buscar administradores e enviar notificação
            $admins = User::where('permission_id','<=', 1)->get();
            if ($admins->count() > 0) {
                $adminEmailResult = $brevoService->sendAdminRedemptionNotification(
                    $this->user,
                    $this->product,
                    $this->redemption,
                    $this->quantity,
                    $this->pointsUsed,
                    $admins
                );

                if ($adminEmailResult['success']) {
                    Log::info('Email de resgate enviado para administradores via Brevo', [
                        'admin_count' => $admins->count(),
                        'message_id' => $adminEmailResult['message_id']
                    ]);
                }
            }

            // Se o produto pertence à categoria 3, notificar grupo permission_id=5
            $categoryId = (int) ($this->product->guid ?? ($this->product->category_id ?? 0));
            if ($categoryId != 3) {
                $groupUsers = User::where('permission_id', 5)
                    ->whereNotNull('email')
                    ->get();

                if ($groupUsers->count() > 0) {
                    $groupEmailResult = $brevoService->sendAdminRedemptionNotification(
                        $this->user,
                        $this->product,
                        $this->redemption,
                        $this->quantity,
                        $this->pointsUsed,
                        $groupUsers
                    );

                    if ($groupEmailResult['success']) {
                        Log::info('Email de resgate enviado ao grupo permission_id=5 via Brevo', [
                            'group_count' => $groupUsers->count(),
                            'message_id' => $groupEmailResult['message_id']
                        ]);
                    }
                }
            }

            Log::info('Notificações de resgate processadas com sucesso via Brevo API', [
                'user_id' => $this->user->id,
                'product_id' => $this->product->ID,
                'redemption_id' => $this->redemption->id,
                'user_email_sent' => $userEmailResult['success'],
                'admin_emails_sent' => isset($adminEmailResult) ? $adminEmailResult['success'] : false,
                'group_emails_sent' => isset($groupEmailResult) ? $groupEmailResult['success'] : false
            ]);

        } catch (Exception $e) {
            Log::error('Erro ao enviar notificações de resgate via Brevo', [
                'error' => $e->getMessage(),
                'user_id' => $this->user->id,
                'product_id' => $this->product->ID,
                'redemption_id' => $this->redemption->id,
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw para que o job seja marcado como falhado e possa ser reprocessado
            throw $e;
        }
    }
}
