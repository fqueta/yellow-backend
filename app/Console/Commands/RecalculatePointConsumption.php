<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RecalculatePointConsumption extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'points:recalculate-consumption';
    protected $description = 'Recalcula o valor_usado de todos os créditos com base nos débitos históricos (PEPS).';

    public function handle()
    {
        $this->info('Iniciando recálculo de consumo de pontos...');

        if (class_exists(\App\Models\Tenant::class)) {
            $tenants = \App\Models\Tenant::all();

            foreach ($tenants as $tenant) {
                $this->info("Processando Tenant: [{$tenant->id}]");
                $tenant->run(function () {
                    $this->recalculateForCurrentContext();
                });
            }
        } else {
            $this->recalculateForCurrentContext();
        }

        $this->info('Recálculo concluído com sucesso!');
        return 0;
    }

    private function recalculateForCurrentContext()
    {
        $clientIds = \App\Models\Point::distinct()->pluck('client_id');
        $totalClients = $clientIds->count();
        $processed = 0;

        foreach ($clientIds as $clientId) {
            $processed++;

            // 1. Resetar valor_usado para todos os créditos deste cliente
            \App\Models\Point::where('client_id', $clientId)
                ->where('tipo', 'credito')
                ->update(['valor_usado' => 0]);

            // 2. Buscar TODOS os débitos do cliente em ordem cronológica
            // Inclui tipo 'debito' (resgates) E tipo 'expired' (expirações)
            // NÃO usa o scope ativos() pois registros de expiração têm status='finalizado'
            $debitos = \App\Models\Point::where('client_id', $clientId)
                ->whereIn('tipo', ['debito', 'expired'])
                ->where('status', '!=', 'cancelado')
                ->where('excluido', 'n')
                ->where('deletado', 'n')
                ->orderBy('data', 'asc')
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            if ($debitos->isEmpty()) continue;

            $this->line("  [{$processed}/{$totalClients}] Recalculando " . $debitos->count() . " débitos para o cliente #{$clientId}...");

            foreach ($debitos as $debito) {
                // Usa o método de recálculo histórico que ignora filtros de data de expiração
                \App\Models\Point::consumePointsForRecalculation($clientId, abs($debito->valor));
            }
        }
    }
}
