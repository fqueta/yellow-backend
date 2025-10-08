<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Product;
use App\Models\Redemption;
use App\Jobs\SendRedemptionNotification;
use Illuminate\Support\Facades\Queue;

try {
    echo "Testando sistema de filas...\n";
    
    // Verificar se a tabela jobs existe
    $jobsTableExists = \Illuminate\Support\Facades\Schema::hasTable('jobs');
    echo "Tabela 'jobs' existe: " . ($jobsTableExists ? 'Sim' : 'Não') . "\n";
    
    // Verificar configuração de fila
    $queueConnection = config('queue.default');
    echo "Conexão de fila padrão: {$queueConnection}\n";
    
    // Verificar se existem usuários
    $userCount = User::count();
    echo "Número de usuários: {$userCount}\n";
    
    // Verificar se existem produtos
    $productCount = Product::count();
    echo "Número de produtos: {$productCount}\n";
    
    // Verificar se existem resgates
    $redemptionCount = Redemption::count();
    echo "Número de resgates: {$redemptionCount}\n";
    
    echo "\nSistema de filas configurado corretamente!\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}