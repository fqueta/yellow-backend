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

        foreach ($clientIds as $clientId) {
            // 1. Resetar valor_usado para todos os créditos deste cliente
            \App\Models\Point::where('client_id', $clientId)
                ->where('tipo', 'credito')
                ->update(['valor_usado' => 0]);

            // 2. Buscar TODOS os débitos do cliente em ordem cronológica
            $debitos = \App\Models\Point::where('client_id', $clientId)
                ->where('tipo', 'debito')
                ->ativos()
                ->orderBy('data', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            if ($debitos->isEmpty()) continue;

            $this->line("  Processando " . $debitos->count() . " débitos para o cliente #{$clientId}...");

            foreach ($debitos as $debito) {
                // Notar que Point::consumePoints já cuida de encontrar os créditos certos
                \App\Models\Point::consumePoints($clientId, $debito->valor);
            }
        }
    }
}
