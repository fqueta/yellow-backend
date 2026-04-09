<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Point;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

$tenantId = 'api-mileto'; // Tenant do dev
$targetId = 336;

$tenant = Tenant::find($tenantId);
$tenant->run(function() use ($targetId) {
    echo "=== Diagnosticando Crédito #{$targetId} ===\n";
    
    $point = Point::find($targetId);
    if (!$point) {
        echo "Erro: Crédito #{$targetId} não encontrado.\n";
        return;
    }
    
    echo "Status do Crédito: {$point->status}\n";
    echo "Valor Usado: {$point->valor_usado}\n";
    
    echo "\nBuscando registros de expiração vinculados...\n";
    $expiracoes = Point::where('client_id', $point->client_id)
        ->where('origem', 'expiracao')
        ->where('description', 'like', "%#{$targetId}%")
        ->get();
        
    echo "Encontrados: " . $expiracoes->count() . " registros.\n";
    
    foreach ($expiracoes as $exp) {
        echo " - ID: {$exp->id} | Valor: {$exp->valor} | Descrição: {$exp->description} | Origem: {$exp->origem}\n";
    }
    
    echo "\nBusca sem filtro de origem (apenas descrição):\n";
    $qualquer = Point::where('client_id', $point->client_id)
        ->where('description', 'like', "%#{$targetId}%")
        ->where('tipo', '!=', 'credito')
        ->get();
    
    echo "Encontrados: " . $qualquer->count() . " registros.\n";
    foreach ($qualquer as $q) {
        echo " - ID: {$q->id} | Tipo: {$q->tipo} | Valor: {$q->valor} | Descrição: {$q->description}\n";
    }
});
