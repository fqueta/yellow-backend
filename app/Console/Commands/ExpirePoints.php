<?php

namespace App\Console\Commands;

use App\Models\Point;
use App\Models\User;
use App\Models\SystemLog;
use App\Services\Qlib;
use App\Notifications\PointsExpiredNotification;
use Illuminate\Console\Command;
use Stancl\Tenancy\Tenancy;
use Illuminate\Support\Facades\Schema;

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

                    // Criar log do sistema no banco do tenant
                    SystemLog::create([
                        'event_type' => 'point_expiration_daily',
                        'status' => 'success',
                        'description' => "Rotina diária de expiração de pontos concluída.",
                        'metadata' => [
                            'total_expired' => $count
                        ],
                    ]);
                });
            }
        } else {
            // Sem multi-tenancy
            $count = $this->expirePointsForCurrentContext();
            $totalExpirados += $count;
            if ($count > 0) {
                $this->notifyAdmins($count, null);
            }

            try {
                if (Schema::hasTable('system_logs')) {
                    SystemLog::create([
                        'event_type' => 'point_expiration_daily',
                        'status' => 'success',
                        'description' => "Rotina diária de expiração de pontos concluída.",
                        'metadata' => [
                            'total_expired' => $count
                        ],
                    ]);
                }
            } catch (\Throwable $e) {
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

        if ($admins->isEmpty()) {
            SystemLog::create([
                'event_type' => 'admin_notification_skip',
                'status' => 'warning',
                'description' => "Nenhum administrador encontrado para notificar (Tenant: {$tenantId}).",
                'metadata' => ['expired_count' => $expiredCount, 'tenant_id' => $tenantId]
            ]);
            return;
        }

        SystemLog::create([
            'event_type' => 'admin_notification_start',
            'status' => 'info',
            'description' => "Iniciando notificação de " . $admins->count() . " administradores sobre expiração de pontos.",
            'metadata' => [
                'expired_count' => $expiredCount,
                'tenant_id' => $tenantId,
                'admins_count' => $admins->count()
            ]
        ]);

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
        $expiracaoAtiva = Qlib::qoption('pontos_expiracao_ativa') ?? 'n';
        if ($expiracaoAtiva !== 's') {
            $this->warn('  Expiração de pontos desativada para este tenant. Ignorando.');
            try {
                if (Schema::hasTable('system_logs')) {
                    SystemLog::create([
                        'event_type' => 'point_expiration_skipped',
                        'status' => 'info',
                        'description' => "A rotina de expiração de pontos foi ignorada pois a funcionalidade está desativida nas configurações do tenant.",
                    ]);
                }
            } catch (\Throwable $e) {
            }
            return 0;
        }

        // Buscar créditos ativos vencidos que ainda possuem saldo (valor > valor_usado)
        $pontosParaExpirar = Point::where('tipo', 'credito')
            ->where('status', 'ativo')
            ->where('ativo', 's')
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->whereNotNull('data_expiracao')
            ->where('data_expiracao', '<=', now()->toDateString())
            ->get();

        $count = 0;

        foreach ($pontosParaExpirar as $ponto) {
            $saldoRestante = (float) $ponto->valor - (float) $ponto->valor_usado;

            if ($saldoRestante > 0) {
                // Criar um DÉBITO de expiração para registrar no extrato e baixar o saldo total
                Point::create([
                    'client_id' => $ponto->client_id,
                    'valor' => -$saldoRestante,
                    'tipo' => 'expired',
                    'origem' => 'expiracao',
                    'status' => 'finalizado',
                    'description' => "Expiração de pontos (Crédito #{$ponto->id} de " . $ponto->data->format('d/m/Y') . ")",
                    'data' => now()->toDateString(),
                    'config' => [
                        'referencia_credito_id' => $ponto->id,
                        'valor_original_credito' => $ponto->valor,
                        'valor_expirado' => $saldoRestante
                    ]
                ]);
            }

            // Marcar o crédito original como expirado e zerar o saldo "disponível" dele
            $ponto->update([
                'status' => 'expirado',
                'valor_usado' => $ponto->valor // Marcar como totalmente usado para não contar mais em saldos manuais
            ]);

            $count++;
        }

        return $count;
    }
}
