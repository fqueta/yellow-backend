<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use App\Models\Point;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tenants = Tenant::all();

foreach ($tenants as $tenant) {
    echo "\n--- Analisando Tenant: {$tenant->id} ---\n";
    $tenant->run(function() {
        $hoje = now()->toDateString();
        
        // Contar quantos pontos o robô de expiração está encontrando
        $pontosQuery = Point::where('tipo', 'credito')
            ->where('status', 'ativo')
            ->where('ativo', 's')
            ->where('excluido', 'n')
            ->where('deletado', 'n')
            ->whereNotNull('data_expiracao')
            ->where('data_expiracao', '<=', $hoje);

        $totalEncontrados = $pontosQuery->count();
        echo "Total de créditos vencidos/ativos encontrados: {$totalEncontrados}\n";

        // Detalhar saldo desses pontos
        $pontos = $pontosQuery->get();
        $comSaldo = 0;
        $semSaldo = 0;

        foreach ($pontos as $p) {
            $saldo = (float)$p->valor - (float)$p->valor_usado;
            if ($saldo > 0) {
                $comSaldo++;
            } else {
                $semSaldo++;
            }
        }

        echo "-> Destes, possuem SALDO REAL: {$comSaldo}\n";
        echo "-> Destes, possuem SALDO ZERO (já usados): {$semSaldo}\n";
        
        if ($totalEncontrados > 0) {
            echo "\nExemplo de ponto no limbo (ID | Valor | Valor Usado | Data Exp):\n";
            foreach ($pontos->take(5) as $p) {
                echo "#{$p->id} | {$p->valor} | {$p->valor_usado} | {$p->data_expiracao}\n";
            }
        }
    });
}
