<?php

namespace App\Console\Commands;

use App\Models\Point;
use App\Models\SystemLog;
use App\Services\Qlib;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comando Artisan para migrar pontos legados (anteriores à implantação do sistema de expiração).
 *
 * Este comando deve ser executado UMA VEZ na implantação do sistema de expiração.
 * Ele encontra todos os créditos com saldo restante que não possuem data de expiração
 * e realiza a transição de forma transparente:
 *
 *   1) Cria um DÉBITO de migração zerando o crédito antigo.
 *   2) Cria um novo CRÉDITO com o mesmo saldo e data_expiracao calculada a partir de HOJE.
 *
 * Isso garante que nenhum cliente seja surpreendido por pontos expirados retroativamente.
 *
 * Uso: php artisan points:migrate-legacy
 * Uso (dry-run): php artisan points:migrate-legacy --dry-run
 */
class MigrateLegacyPoints extends Command
{
    protected $signature = 'points:migrate-legacy
                            {--dry-run : Simula a migração sem salvar nada no banco}
                            {--cutoff= : Data de corte (padrão: hoje). Apenas créditos anteriores a esta data serão migrados. Formato: YYYY-MM-DD}';

    protected $description = 'Migra pontos legados (sem data de expiração) criando Baixa + Novo Crédito com expiração a partir de hoje.';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $cutoffDate = $this->option('cutoff') ?? now()->toDateString();
        $batchId    = 'MIG-' . now()->format('Ymd-His');

        $this->info("Iniciando migração de pontos legados... [Lote: {$batchId}]");
        if ($isDryRun) {
            $this->warn('  [DRY-RUN] Nenhuma alteração será salva no banco de dados.');
        }
        $this->info("  Data de corte: {$cutoffDate}");

        $totalMigrados = 0;
        $totalPontosTransferidos = 0;

        if (class_exists(\App\Models\Tenant::class)) {
            $tenants = \App\Models\Tenant::all();

            if ($tenants->isEmpty()) {
                $this->warn('Nenhum tenant encontrado.');
                return 0;
            }

            foreach ($tenants as $tenant) {
                $tenant->run(function () use (&$totalMigrados, &$totalPontosTransferidos, $tenant, $isDryRun, $cutoffDate, $batchId) {
                    [$count, $pontos] = $this->migrateForCurrentContext($isDryRun, $cutoffDate, $batchId);
                    $totalMigrados += $count;
                    $totalPontosTransferidos += $pontos;

                    if ($count > 0 && !$isDryRun) {
                        $this->line("  Tenant [{$tenant->id}]: {$count} créditos migrados ({$pontos} pontos transferidos).");

                        SystemLog::create([
                            'event_type' => 'point_legacy_migration',
                            'status'     => 'success',
                            'description' => "Migração de pontos legados concluída. Lote: {$batchId}",
                            'metadata'   => [
                                'batch_id'   => $batchId,
                                'cutoff_date' => $cutoffDate,
                                'total_migrated' => $count,
                                'total_points_transferred' => $pontos,
                            ],
                        ]);
                    } elseif ($count > 0 && $isDryRun) {
                        $this->line("  [DRY-RUN] Tenant [{$tenant->id}]: {$count} créditos seriam migrados ({$pontos} pontos).");
                    } else {
                        $this->line("  Tenant [{$tenant->id}]: Nenhum crédito legado encontrado.");
                    }
                });
            }
        } else {
            [$count, $pontos] = $this->migrateForCurrentContext($isDryRun, $cutoffDate, $batchId);
            $totalMigrados += $count;
            $totalPontosTransferidos += $pontos;

            if (!$isDryRun && $count > 0) {
                SystemLog::create([
                    'event_type' => 'point_legacy_migration',
                    'status'     => 'success',
                    'description' => "Migração de pontos legados concluída. Lote: {$batchId}",
                    'metadata'   => [
                        'batch_id'   => $batchId,
                        'cutoff_date' => $cutoffDate,
                        'total_migrated' => $count,
                        'total_points_transferred' => $pontos,
                    ],
                ]);
            }
        }

        $prefix = $isDryRun ? '[DRY-RUN] ' : '';
        $this->info("{$prefix}Concluído! {$totalMigrados} créditos migrados | {$totalPontosTransferidos} pontos transferidos. [Lote: {$batchId}]");

        return 0;
    }

    /**
     * Executa a migração para o contexto do tenant atual.
     * Agrupa todos os créditos legados por cliente e gera apenas
     * 1 DÉBITO (baixa do total) + 1 CRÉDITO (novo prazo) por cliente.
     *
     * @return array [int $clientesAfetados, float $totalPontos]
     */
    private function migrateForCurrentContext(bool $isDryRun, string $cutoffDate, string $batchId): array
    {
        $diasExpiracao = (int) (Qlib::qoption('pontos_dias_expiracao') ?? 0);

        if ($diasExpiracao <= 0) {
            $this->warn('  Configuração pontos_dias_expiracao não definida. Ignorando tenant.');
            return [0, 0];
        }

        // Créditos com saldo restante (valor > valor_usado), sem data de expiração,
        // criados ANTES da data de corte (pontos legados).
        $creditos = Point::where('tipo', 'credito')
            ->where('status', 'ativo')
            ->where('ativo', 's')
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->whereNull('data_expiracao')
            ->whereRaw('valor > valor_usado')
            ->where('data', '<', $cutoffDate)
            ->orderBy('client_id', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($creditos->isEmpty()) {
            return [0, 0];
        }

        $novaDataExpiracao = Carbon::parse($cutoffDate)->addDays($diasExpiracao)->format('Y-m-d');

        // Agrupa os créditos por cliente
        $porCliente = $creditos->groupBy('client_id');

        $clientesAfetados = 0;
        $totalPontos = 0;

        foreach ($porCliente as $clientId => $creditosDoCliente) {
            $saldoTotalCliente = 0;
            $idsReferencia     = [];

            foreach ($creditosDoCliente as $credito) {
                $saldo = (float) $credito->valor - (float) $credito->valor_usado;
                if ($saldo <= 0) continue;
                $saldoTotalCliente += $saldo;
                $idsReferencia[]    = $credito->id;
            }

            if ($saldoTotalCliente <= 0) continue;

            $this->line(sprintf(
                '  %s Cliente: %s | %d cr(s) legado(s) | Total: %.0f pts | Nova expiração: %s',
                $isDryRun ? '[SIM]' : '  OK ',
                $clientId,
                count($idsReferencia),
                $saldoTotalCliente,
                $novaDataExpiracao
            ));

            if (!$isDryRun) {
                DB::transaction(function () use (
                    $clientId, $creditosDoCliente, $saldoTotalCliente,
                    $idsReferencia, $novaDataExpiracao, $batchId, $cutoffDate
                ) {
                    // 1) Marcar todos os créditos originais como migrados
                    foreach ($creditosDoCliente as $credito) {
                        DB::table('points')->where('id', $credito->id)->update([
                            'status'      => 'expirado',
                            'valor_usado' => $credito->valor, // zera o saldo
                        ]);
                    }

                    // 2) UM DÉBITO consolidado: baixa o total dos créditos legados
                    Point::create([
                        'client_id'   => $clientId,
                        'valor'       => -$saldoTotalCliente,
                        'tipo'        => 'expired',
                        'origem'      => 'migracao_legado',
                        'status'      => 'expirado',
                        'description' => "[MIGRAÇÃO] Baixa de saldo legado — pontos sem prazo de validade definido",
                        'data'        => $cutoffDate,
                        'config'      => [
                            'batch_id'              => $batchId,
                            'tipo_operacao'         => 'migracao_legado_debito',
                            'creditos_referencia'   => $idsReferencia,
                            'total_pontos_migrados' => $saldoTotalCliente,
                        ],
                    ]);

                    // 3) UM CRÉDITO consolidado: devolve o total com nova validade
                    Point::create([
                        'client_id'      => $clientId,
                        'valor'          => $saldoTotalCliente,
                        'tipo'           => 'credito',
                        'origem'         => 'migracao_legado',
                        'status'         => 'ativo',
                        'description'    => "[MIGRAÇÃO] Saldo reativado com novo prazo de validade",
                        'data'           => $cutoffDate,
                        'data_expiracao' => $novaDataExpiracao,
                        'config'         => [
                            'batch_id'              => $batchId,
                            'tipo_operacao'         => 'migracao_legado_credito',
                            'creditos_referencia'   => $idsReferencia,
                            'total_pontos_migrados' => $saldoTotalCliente,
                            'nova_expiracao'        => $novaDataExpiracao,
                        ],
                    ]);
                });
            }

            $clientesAfetados++;
            $totalPontos += $saldoTotalCliente;
        }

        return [$clientesAfetados, $totalPontos];
    }
}
