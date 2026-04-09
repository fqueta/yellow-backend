<?php

/**
 * Script de correção: Reverte o Passo 4 da normalização anterior
 * e recalcula o valor_usado corretamente antes de rodar points:expire.
 *
 * O problema: o script normalize_points.php setou valor_usado = valor
 * em todos os créditos vencidos, apagando o cálculo PEPS do Passo 3.
 * Isso fez points:expire ver saldo = 0 e não gerar nenhum registro.
 *
 * A correção:
 * 1. Restaura créditos expirados de volta para status='ativo' e valor_usado=0
 * 2. Refaz o PEPS histórico para recalcular valor_usado corretamente
 * 3. Deixa points:expire fazer sua parte corretamente
 *
 * Após este script, rode: php artisan points:expire
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
        echo "\n=== Corrigindo Tenant: {$tenant->id} ===\n";

        DB::beginTransaction();
        try {

            // PASSO 1: Reverter créditos expirados para o estado pré-expire
            // (status='ativo' e valor_usado=0 para podermos recalcular)
            $count = Point::where('tipo', 'credito')
                ->where('status', 'expirado')
                ->whereNotNull('data_expiracao')
                ->count();

            echo "  Passo 1: Revertendo {$count} créditos expirados para status=ativo...\n";

            Point::where('tipo', 'credito')
                ->where('status', 'expirado')
                ->whereNotNull('data_expiracao')
                ->update([
                    'status'      => 'ativo',
                    'valor_usado' => 0,
                ]);

            // PASSO 2: Resetar valor_usado de TODOS os créditos ativos também
            $countAtivos = Point::where('tipo', 'credito')
                ->where('status', 'ativo')
                ->count();

            echo "  Passo 2: Resetando valor_usado de {$countAtivos} créditos ativos...\n";

            Point::where('tipo', 'credito')
                ->where('status', 'ativo')
                ->update(['valor_usado' => 0]);

            // PASSO 3: Recalcular PEPS histórico
            // Percorre APENAS débitos reais (resgates), não expirações
            $clientIds = Point::distinct()->pluck('client_id');
            $total     = $clientIds->count();
            $processed = 0;

            echo "  Passo 3: Recalculando PEPS para {$total} clientes...\n";

            foreach ($clientIds as $clientId) {
                $processed++;

                $debitos = Point::where('client_id', $clientId)
                    ->where('tipo', 'debito') // apenas resgates reais
                    ->where('status', '!=', 'cancelado')
                    ->where('excluido', 'n')
                    ->where('deletado', 'n')
                    ->orderBy('data', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                foreach ($debitos as $debito) {
                    // consumePointsForRecalculation ignora filtro de data de expiração
                    // pois reconstrói o histórico como era no passado
                    Point::consumePointsForRecalculation($clientId, abs($debito->valor));
                }

                if ($processed % 100 === 0) {
                    echo "    ... {$processed}/{$total} clientes processados\n";
                }
            }

            DB::commit();

            echo "\n  ✓ Correção concluída para tenant {$tenant->id}!\n";
            echo "  Agora execute: php artisan points:expire\n";
            echo "  O comando irá:\n";
            echo "    - Encontrar créditos com data_expiracao <= hoje e saldo real > 0\n";
            echo "    - Criar registros de expiração com o saldo CORRETO (valor - valor_usado)\n";
            echo "    - Marcar os créditos como expirados\n";

        } catch (\Throwable $e) {
            DB::rollBack();
            echo "  ✗ ERRO: " . $e->getMessage() . "\n";
            echo "  Rollback realizado - dados intactos.\n";
        }
    });
}

echo "\n=== Correção global concluída! ===\n";
echo "Execute agora: php artisan points:expire\n";
