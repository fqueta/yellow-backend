<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Jobs\SendRedemptionNotification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

try {
    echo "Testando sistema de filas básico...\n";
    
    // Verificar configuração de fila
    $queueConnection = config('queue.default');
    echo "Conexão de fila padrão: {$queueConnection}\n";
    
    // Criar dados de teste simples
    $testUser = (object) [
        'id' => 1,
        'name' => 'Usuário Teste',
        'email' => 'teste@exemplo.com'
    ];
    
    $testProduct = (object) [
        'id' => 1,
        'name' => 'Produto Teste',
        'points_required' => 100
    ];
    
    $testRedemption = (object) [
        'id' => 1,
        'user_id' => 1,
        'product_id' => 1,
        'quantity' => 1,
        'total_points' => 100,
        'created_at' => now()
    ];
    
    echo "Dados de teste criados\n";
    echo "Tentando despachar job...\n";
    
    // Tentar despachar o job
    $job = new SendRedemptionNotification($testUser, $testProduct, $testRedemption, 1, 100);
    
    // Usar dispatch para adicionar à fila
    dispatch($job);
    
    echo "Job despachado com sucesso!\n";
    echo "Verifique o worker de fila para processar o job.\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . " Linha: " . $e->getLine() . "\n";
}