<?php

/**
 * Script de normalização de dados de pontos.
 *
 * ATENÇÃO: Este script é DESTRUTIVO para registros de expiração históricos.
 * Ele remove as expirações lançadas com valores incorretos (valor_usado=0)
 * e prepara os dados para que points:expire as relance corretamente.
 *
 * Ordem de execução:
 * 1. Remove os registros de expiração existentes (origem=expiracao)
 * 2. Redefine valor_usado e status de todos os créditos para ativo/0
 * 3. Recalcula o valor_usado via PEPS usando apenas débitos reais (resgates)
 *
 * ⚠️  NÃO faz a expiração em si - isso é responsabilidade do points:expire
 *     que corretamente calcula saldo = valor - valor_usado e cria os registros.
 *
 * Após este script, rode obrigatoriamente: php artisan points:expire
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

            // PASSO 1: Remover registros de expiração existentes (podem estar errados)
            $count = Point::whereIn('tipo', ['debito', 'expired'])
                ->where('origem', 'expiracao')
                ->count();
            echo "  Passo 1: Removendo {$count} registros de expiração existentes...\n";
            Point::whereIn('tipo', ['debito', 'expired'])
                ->where('origem', 'expiracao')
                ->forceDelete();

            // PASSO 2: Resetar valor_usado e status de todos os créditos
            $creditos = Point::where('tipo', 'credito')->count();
            echo "  Passo 2: Resetando {$creditos} créditos (valor_usado=0, status=ativo)...\n";
            Point::where('tipo', 'credito')
                ->update([
                    'valor_usado' => 0,
                    'status'      => 'ativo', // Reativar para que points:expire possa processar
                ]);

            // PASSO 3: Recalcular consumo PEPS histórico (apenas resgates reais)
            $clientIds = Point::distinct()->pluck('client_id');
            $total     = $clientIds->count();
            $processed = 0;
            echo "  Passo 3: Recalculando PEPS para {$total} clientes...\n";

            foreach ($clientIds as $clientId) {
                $processed++;

                $debitos = Point::where('client_id', $clientId)
                    ->where('tipo', 'debito') // Apenas resgates reais (não expirações)
                    ->where('status', '!=', 'cancelado')
                    ->where('excluido', 'n')
                    ->where('deletado', 'n')
                    ->orderBy('data', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                foreach ($debitos as $debito) {
                    // consumePointsForRecalculation: não filtra por data de expiração
                    // pois reconstrói o estado histórico de quando os créditos eram válidos
                    Point::consumePointsForRecalculation($clientId, abs($debito->valor));
                }

                if ($processed % 50 === 0) {
                    echo "    ... {$processed}/{$total} clientes processados\n";
                }
            }

            DB::commit();
            echo "  ✓ Normalização concluída para tenant {$tenant->id}!\n";

        } catch (\Throwable $e) {
            DB::rollBack();
            echo "  ✗ ERRO ao normalizar tenant {$tenant->id}: " . $e->getMessage() . "\n";
            echo "  Rollback realizado - dados intactos.\n";
        }
    });
}

echo "\n=== Normalização concluída! ===\n";
echo "Execute agora: php artisan points:expire\n";
echo "O comando irá calcular saldo = valor - valor_usado para cada crédito vencido\n";
echo "e criar os registros de expiração com os valores CORRETOS.\n";
