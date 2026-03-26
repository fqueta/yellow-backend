<?php

namespace App\Console\Commands;

use App\Models\Point;
use App\Models\User;
use App\Notifications\PointsExpiredNotification;
use Illuminate\Console\Command;
use Stancl\Tenancy\Tenancy;

/**
 * Comando Artisan para expirar pontos vencidos em lote.
 * Busca todos os pontos de crédito ativos com data_expiracao < hoje
 * e marca como status = 'expirado'.
 *
 * Compatível com multi-tenancy: itera sobre todos os tenants.
 *
 * Uso: php artisan points:expire
 */
class ExpirePoints extends Command
{
    protected $signature = 'points:expire';
    protected $description = 'Expirar pontos de crédito vencidos (data_expiracao < hoje)';

    public function handle()
    {
        $this->info('Iniciando expiração de pontos...');

        $totalExpirados = 0;

        // Multi-tenancy: iterar sobre todos os tenants
        if (class_exists(\App\Models\Tenant::class)) {
            $tenants = \App\Models\Tenant::all();

            if ($tenants->isEmpty()) {
                $this->warn('Nenhum tenant encontrado.');
                return 0;
            }

            foreach ($tenants as $tenant) {
                $tenant->run(function () use (&$totalExpirados, $tenant) {
                    $count = $this->expirePointsForCurrentContext();
                    $totalExpirados += $count;
                    if ($count > 0) {
                        $this->line("  Tenant [{$tenant->id}]: {$count} pontos expirados");
                        $this->notifyAdmins($count, $tenant->id);
                    }
                });
            }
        } else {
            // Sem multi-tenancy
            $totalExpirados = $this->expirePointsForCurrentContext();
            if ($totalExpirados > 0) {
                $this->notifyAdmins($totalExpirados, null);
            }
        }

        $this->info("Concluído! Total de pontos expirados: {$totalExpirados}");

        return 0;
    }

    /**
     * Envia notificação aos administradores (permission_id = 1)
     */
    private function notifyAdmins(int $expiredCount, ?string $tenantId): void
    {
        $admins = User::where('permission_id', 1)
            ->where('ativo', 's')
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->get();

        foreach ($admins as $admin) {
            try {
                $admin->notify(new PointsExpiredNotification($expiredCount, $tenantId));
                $this->line("  Notificação enviada para: {$admin->email}");
            } catch (\Exception $e) {
                $this->warn("  Falha ao enviar notificação para {$admin->email}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Expira pontos no contexto atual (tenant ou padrão)
     */
    private function expirePointsForCurrentContext(): int
    {
        return Point::where('tipo', 'credito')
            ->where('status', 'ativo')
            ->where('ativo', 's')
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->whereNotNull('data_expiracao')
            ->where('data_expiracao', '<', now()->toDateString())
            ->update(['status' => 'expirado']);
    }
}
