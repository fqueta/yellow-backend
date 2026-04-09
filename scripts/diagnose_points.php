<?php

// Script de diagnóstico e normalização de dados de pontos
// Uso: php artisan tinker < scripts/normalize_points.php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use App\Models\Point;
use Illuminate\Support\Facades\DB;

$tenants = Tenant::all();

foreach ($tenants as $tenant) {
    $tenant->run(function () use ($tenant) {
        echo "\n=== Tenant: {$tenant->id} ===\n";

        $totalCreditos = Point::where('tipo', 'credito')->count();
        $totalDebitos  = Point::where('tipo', 'debito')->count();
        $totalExpirados = Point::whereIn('tipo', ['debito', 'expired'])->where('origem', 'expiracao')->count();

        echo "Créditos:          {$totalCreditos}\n";
        echo "Débitos (resgates): {$totalDebitos}\n";
        echo "Registros de expiração: {$totalExpirados}\n";

        // Checar créditos com valor_usado = 0 mas com débitos associados
        $credSemUso = Point::where('tipo', 'credito')
            ->where('valor_usado', 0)
            ->count();
        echo "Créditos com valor_usado=0: {$credSemUso}\n";

        // Saldo atual calculado
        $clientIds = Point::distinct()->pluck('client_id');
        $saldosNegativos = 0;
        foreach ($clientIds as $clientId) {
            $saldo = Point::saldoCliente($clientId);
            if ($saldo < 0) $saldosNegativos++;
        }
        echo "Clientes com saldo negativo: {$saldosNegativos} de {$clientIds->count()}\n";
    });
}

echo "\nDiagnóstico concluído.\n";
