<?php

/**
 * Script de normalização de dados de pontos.
 * 
 * ATENÇÃO: Este script é DESTRUTIVO para registros de expiração históricos.
 * Ele remove as expirações lançadas com valores incorretos (valor_usado=0)
 * e as relança corretamente após recalcular o consumo PEPS histórico.
 * 
 * Ordem de execução:
 * 1. Remove APENAS os registros de expiração lançados (origem=expiracao)
 * 2. Redefine valor_usado de todos os créditos para 0
 * 3. Recalcula o valor_usado de cada crédito por PEPS (incluindo expirados históricos)
 * 4. Marca como 'expirado' os créditos que já passaram da data
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use App\Models\Point;
use Illuminate\Support\Facades\DB;

$tenants = Tenant::all();

foreach ($tenants as $tenant) {
    $tenant->run(function () use ($tenant) {
        echo "\n=== Normalizando Tenant: {$tenant->id} ===\n";

        DB::beginTransaction();
        try {

            // PASSO 1: Remover registros de expiração lançados (podem estar errados)
            $expiracoes = Point::whereIn('tipo', ['debito', 'expired'])
                ->where('origem', 'expiracao')
                ->get();
            $count = $expiracoes->count();
            echo "  Passo 1: Removendo {$count} registros de expiração existentes...\n";
            Point::whereIn('tipo', ['debito', 'expired'])
                ->where('origem', 'expiracao')
                ->forceDelete();

            // PASSO 2: Resetar valor_usado de todos os créditos
            $creditos = Point::where('tipo', 'credito')->count();
            echo "  Passo 2: Resetando valor_usado de {$creditos} créditos para 0...\n";
            Point::where('tipo', 'credito')
                ->update([
                    'valor_usado' => 0,
                    'status' => 'ativo', // Reativar créditos marcados como expirado incorretamente
                ]);

            // PASSO 3: Recalcular consumo PEPS histórico por cliente
            $clientIds = Point::distinct()->pluck('client_id');
            $total = $clientIds->count();
            $processed = 0;
            echo "  Passo 3: Recalculando PEPS para {$total} clientes...\n";

            foreach ($clientIds as $clientId) {
                $processed++;

                $debitos = Point::where('client_id', $clientId)
                    ->where('tipo', 'debito') // Apenas resgates reais (não expirações que removemos)
                    ->where('status', '!=', 'cancelado')
                    ->where('excluido', 'n')
                    ->where('deletado', 'n')
                    ->orderBy('data', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                foreach ($debitos as $debito) {
                    Point::consumePointsForRecalculation($clientId, abs($debito->valor));
                }

                if ($processed % 50 === 0) {
                    echo "    ... {$processed}/{$total} clientes processados\n";
                }
            }

            // PASSO 4: Marcar créditos vencidos como 'expirado' de acordo com data_expiracao
            $hoje = now()->toDateString();
            $creditosExpirados = Point::where('tipo', 'credito')
                ->where('status', 'ativo')
                ->whereNotNull('data_expiracao')
                ->where('data_expiracao', '<=', $hoje)
                ->count();
            echo "  Passo 4: Marcando {$creditosExpirados} créditos vencidos como 'expirado'...\n";
            Point::where('tipo', 'credito')
                ->where('status', 'ativo')
                ->whereNotNull('data_expiracao')
                ->where('data_expiracao', '<=', $hoje)
                ->update([
                    'status' => 'expirado',
                    'valor_usado' => DB::raw('valor') // Marcar como totalmente consumido
                ]);

            DB::commit();
            echo "  ✓ Normalização concluída com sucesso para tenant {$tenant->id}!\n";

        } catch (\Throwable $e) {
            DB::rollBack();
            echo "  ✗ ERRO ao normalizar tenant {$tenant->id}: " . $e->getMessage() . "\n";
            echo "  Rollback realizado - dados intactos.\n";
        }
    });
}

echo "\n=== Normalização global concluída! ===\n";
echo "Agora rode: php artisan points:expire\n";
echo "Para registrar as expirações corretas com base nos saldos reais.\n";
