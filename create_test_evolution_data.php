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
    echo "🔧 Criando dados de teste para evolução do dashboard...\n\n";
    
    // Limpar dados existentes
    Client::truncate();
    echo "🗑️ Dados anteriores removidos\n";
    
    // Criar clientes do período atual (últimos 30 dias)
    echo "📊 Criando clientes do período atual (últimos 30 dias):\n";
    for ($i = 0; $i < 5; $i++) {
        $createdAt = Carbon::now()->subDays(rand(0, 29))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
        $client = new Client([
            'name' => 'Cliente Atual ' . ($i + 1),
            'email' => 'atual' . ($i + 1) . '@test.com',
        ]);
        $client->created_at = $createdAt;
        $client->updated_at = Carbon::now();
        $client->save();
        echo "   ✅ {$client->name} - {$createdAt->format('d/m/Y H:i')}\n";
    }
    
    // Criar clientes do período anterior (30-59 dias atrás)
    echo "\n📊 Criando clientes do período anterior (30-59 dias atrás):\n";
    for ($i = 0; $i < 3; $i++) {
        $createdAt = Carbon::now()->subDays(rand(30, 59))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
        $client = new Client([
            'name' => 'Cliente Anterior ' . ($i + 1),
            'email' => 'anterior' . ($i + 1) . '@test.com',
        ]);
        $client->created_at = $createdAt;
        $client->updated_at = Carbon::now();
        $client->save();
        echo "   ✅ {$client->name} - {$createdAt->format('d/m/Y H:i')}\n";
    }
    
    // Criar alguns clientes muito antigos (mais de 60 dias)
    echo "\n📊 Criando clientes antigos (mais de 60 dias):\n";
    for ($i = 0; $i < 2; $i++) {
        $createdAt = Carbon::now()->subDays(rand(60, 120))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
        $client = new Client([
            'name' => 'Cliente Antigo ' . ($i + 1),
            'email' => 'antigo' . ($i + 1) . '@test.com',
        ]);
        $client->created_at = $createdAt;
        $client->updated_at = Carbon::now();
        $client->save();
        echo "   ✅ {$client->name} - {$createdAt->format('d/m/Y H:i')}\n";
    }
    
    echo "\n✅ Dados de teste criados com sucesso!\n";
    echo "📈 Total de clientes: " . Client::count() . "\n";
    echo "📊 Período atual (últimos 30 dias): " . Client::where('created_at', '>=', now()->subDays(29)->startOfDay())->count() . "\n";
    echo "📊 Período anterior (30-59 dias): " . Client::whereBetween('created_at', [now()->subDays(59)->startOfDay(), now()->subDays(30)->endOfDay()])->count() . "\n";
});