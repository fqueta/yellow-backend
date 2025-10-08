<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Jobs\SendRedemptionNotification;
use Illuminate\Support\Facades\Log;

try {
    echo "Testando sistema de notificação final...\n";
    
    // Criar dados de teste simples
    $testUser = (object) [
        'id' => 1,
        'email' => 'teste@exemplo.com',
        'name' => 'Usuário Teste'
    ];
    
    $testProduct = (object) [
        'id' => 1,
        'name' => 'Produto Teste',
        'price' => 100
    ];
    
    $testRedemption = (object) [
        'id' => 1,
        'created_at' => now()
    ];
    
    echo "Despachando job de notificação...\n";
    
    // Despachar job
    $job = new SendRedemptionNotification(
        $testUser,
        $testProduct, 
        $testRedemption,
        2, // quantidade
        200 // pontos usados
    );
    
    dispatch($job);
    
    echo "Job despachado com sucesso!\n";
    echo "Verifique os logs em storage/logs/laravel.log para confirmar a execução.\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}