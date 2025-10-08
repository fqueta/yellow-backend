<?php

require_once 'vendor/autoload.php';

// Configurar o ambiente Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

echo "=== CRIANDO TOKEN VÁLIDO PARA TESTE ===".PHP_EOL.PHP_EOL;

try {
    // Conectar ao tenant yellow-dev
    $tenant = \App\Models\Tenant::where('id', 'yellow-dev')->first();
    if (!$tenant) {
        echo "❌ Tenant 'yellow-dev' não encontrado!".PHP_EOL;
        exit(1);
    }
    
    $tenant->run(function () {
        echo "🔍 Buscando usuário para criar token...".PHP_EOL;
        
        // Buscar um usuário existente
        $user = User::first();
        if (!$user) {
            echo "❌ Nenhum usuário encontrado!".PHP_EOL;
            return;
        }
        
        echo "👤 Usuário encontrado: {$user->name} (ID: {$user->id})".PHP_EOL;
        
        // Revogar tokens existentes para este usuário
        $user->tokens()->delete();
        echo "🗑️  Tokens antigos removidos.".PHP_EOL;
        
        // Criar novo token
        $token = $user->createToken('test-token');
        $plainTextToken = $token->plainTextToken;
        
        echo "✅ Token criado com sucesso!".PHP_EOL;
        echo "🔑 Token: {$plainTextToken}".PHP_EOL.PHP_EOL;
        
        echo "📋 Informações do token:".PHP_EOL;
        echo "  - ID do token: {$token->accessToken->id}".PHP_EOL;
        echo "  - Nome: {$token->accessToken->name}".PHP_EOL;
        echo "  - Usuário: {$user->name}".PHP_EOL;
        echo "  - Criado em: {$token->accessToken->created_at}".PHP_EOL.PHP_EOL;
        
        echo "🧪 Para testar, use este token na requisição:".PHP_EOL;
        echo "Authorization: Bearer {$plainTextToken}".PHP_EOL.PHP_EOL;
        
        echo "🌐 URL de teste:".PHP_EOL;
        echo "http://yellow-dev.localhost:8000/api/v1/products/redemptions".PHP_EOL;
    });
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . PHP_EOL;
    echo "📍 Arquivo: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
}

echo PHP_EOL."=== FIM DA CRIAÇÃO ===".PHP_EOL;