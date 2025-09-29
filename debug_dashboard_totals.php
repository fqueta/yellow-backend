<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use App\Models\Client;
use Carbon\Carbon;

// Inicializar tenant
$tenant = Tenant::where('id', 'yellow-dev')->first();
if (!$tenant) {
    echo "❌ Tenant 'yellow-dev' não encontrado!\n";
    exit(1);
}

$tenant->run(function () {
    echo "🔍 DEBUG: Analisando períodos do dashboard\n\n";
    
    $now = Carbon::now();
    echo "📅 Data atual: {$now->format('d/m/Y H:i:s')}\n\n";
    
    // Período atual (últimos 30 dias incluindo hoje)
    $currentStart = $now->copy()->subDays(29)->startOfDay();
    echo "📊 PERÍODO ATUAL (últimos 30 dias incluindo hoje):\n";
    echo "   De: {$currentStart->format('d/m/Y H:i:s')}\n";
    echo "   Até: {$now->format('d/m/Y H:i:s')}\n";
    
    $currentClients = Client::where('created_at', '>=', $currentStart)->get();
    echo "   Total: {$currentClients->count()} clientes\n";
    foreach ($currentClients as $client) {
        echo "   - {$client->name} ({$client->created_at->format('d/m/Y H:i:s')})\n";
    }
    
    // Período anterior (30-59 dias atrás)
    $previousStart = $now->copy()->subDays(59)->startOfDay();
    $previousEnd = $now->copy()->subDays(30)->endOfDay();
    echo "\n📊 PERÍODO ANTERIOR (30-59 dias atrás):\n";
    echo "   De: {$previousStart->format('d/m/Y H:i:s')}\n";
    echo "   Até: {$previousEnd->format('d/m/Y H:i:s')}\n";
    
    $previousClients = Client::whereBetween('created_at', [$previousStart, $previousEnd])->get();
    echo "   Total: {$previousClients->count()} clientes\n";
    foreach ($previousClients as $client) {
        echo "   - {$client->name} ({$client->created_at->format('d/m/Y H:i:s')})\n";
    }
    
    // Todos os clientes
    echo "\n📊 TODOS OS CLIENTES:\n";
    $allClients = Client::orderBy('created_at', 'desc')->get();
    echo "   Total: {$allClients->count()} clientes\n";
    foreach ($allClients as $client) {
        echo "   - {$client->name} ({$client->created_at->format('d/m/Y H:i:s')})\n";
    }
    
    // Testar método getDashboardTotals
    echo "\n🧮 RESULTADO DO MÉTODO getDashboardTotals():\n";
    $totals = Client::getDashboardTotals();
    print_r($totals);
});