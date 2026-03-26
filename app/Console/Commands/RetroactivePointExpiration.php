<?php

namespace App\Console\Commands;

use App\Models\Point;
use App\Models\SystemLog;
use App\Services\Qlib;
use App\Notifications\PointsExpiredNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Stancl\Tenancy\Tenancy;

class RetroactivePointExpiration extends Command
{
    protected $signature = 'points:retroactive-expiration';
    protected $description = 'Aplica a regra de expiração de pontos retroativamente a pontos antigos sem data de expiração.';

    public function handle()
    {
        $this->info('Iniciando atualização retroativa de expiração de pontos...');

        $totalAtualizados = 0;

        if (class_exists(\App\Models\Tenant::class)) {
            $tenants = \App\Models\Tenant::all();

            if ($tenants->isEmpty()) {
                $this->warn('Nenhum tenant encontrado.');
                return 0;
            }

            foreach ($tenants as $tenant) {
                $tenant->run(function () use (&$totalAtualizados, $tenant) {
                    $count = $this->applyExpirationForCurrentContext();
                    $totalAtualizados += $count;
                    if ($count > 0) {
                        $this->line("  Tenant [{$tenant->id}]: {$count} pontos atualizados retroativamente.");
                        $this->notifyAdmins($count, $tenant->id);
                    }

                    // Registro de LOG do Sistema no banco do tenant
                    SystemLog::create([
                        'event_type' => 'point_expiration_retroactive',
                        'status' => 'success',
                        'description' => "Rotina de expiração retroativa de pontos concluída.",
                        'metadata' => [
                            'total_updated' => $count
                        ],
                    ]);
                });
            }
        } else {
            $count = $this->applyExpirationForCurrentContext();
            $totalAtualizados += $count;
            if ($count > 0) {
                $this->notifyAdmins($count, null);
            }

            SystemLog::create([
                'event_type' => 'point_expiration_retroactive',
                'status' => 'success',
                'description' => "Rotina de expiração retroativa de pontos concluída.",
                'metadata' => [
                    'total_updated' => $count
                ],
            ]);
        }

        $this->info("Concluído! Total de pontos atualizados retroativamente: {$totalAtualizados}");

        return 0;
    }

    private function applyExpirationForCurrentContext(): int
    {
        $diasExpiracao = Qlib::qoption('pontos_dias_expiracao');
        
        if (!$diasExpiracao || (int) $diasExpiracao <= 0) {
            $this->warn("   Regra de expiração não configurada (pontos_dias_expiracao) no contexto atual. Ignorando.");
            return 0;
        }

        $dias = (int) $diasExpiracao;

        $pontos = Point::where('tipo', 'credito')
            ->where('status', 'ativo')
            ->where('ativo', 's')
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->whereNull('data_expiracao')
            ->get();

        $count = 0;

        foreach ($pontos as $point) {
            $dataBase = $point->data ? Carbon::parse($point->data) : $point->created_at;
            $dataExpiracao = $dataBase->addDays($dias)->format('Y-m-d');

            $point->update([
                'data_expiracao' => $dataExpiracao
            ]);

            $count++;
        }

        return $count;
    }

    private function notifyAdmins(int $count, ?string $tenantId): void
    {
        if (!class_exists(\App\Models\User::class)) {
            $this->warn("  Modelo User não disponível para envio de notificações.");
            return;
        }

        $admins = \App\Models\User::where('permission_id', 1)
            ->where('ativo', 's')
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->get();

        if ($admins->isEmpty()) {
            SystemLog::create([
                'event_type' => 'admin_notification_skip',
                'status' => 'warning',
                'description' => "Nenhum administrador encontrado para notificar sobre expiração retroativa (Tenant: {$tenantId}).",
                'metadata' => ['updated_count' => $count, 'tenant_id' => $tenantId]
            ]);
            return;
        }

        SystemLog::create([
            'event_type' => 'admin_notification_start',
            'status' => 'info',
            'description' => "Iniciando notificação de " . $admins->count() . " administradores sobre expiração retroativa.",
            'metadata' => [
                'updated_count' => $count,
                'tenant_id' => $tenantId,
                'admins_count' => $admins->count()
            ]
        ]);

        foreach ($admins as $admin) {
            try {
                $notification = new PointsExpiredNotification($count, $tenantId);
                $admin->notify($notification);
                $this->line("  Notificação enviada para: {$admin->email}");
            } catch (\Exception $e) {
                $this->warn("  Falha ao enviar notificação para {$admin->email}: {$e->getMessage()}");
            }
        }
    }
}
