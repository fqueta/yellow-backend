<?php

/**
 * Script para verificar usuários existentes e criar um usuário de teste
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== VERIFICAÇÃO DE USUÁRIOS ===\n\n";

try {
    // Verificar usuários existentes
    $users = \App\Models\User::select('id', 'name', 'email')->get();
    
    echo "📊 Total de usuários encontrados: " . $users->count() . "\n\n";
    
    if ($users->count() > 0) {
        echo "👥 Usuários existentes:\n";
        foreach ($users as $user) {
            echo "  - ID: {$user->id}, Nome: {$user->name}, Email: {$user->email}\n";
        }
        echo "\n";
    } else {
        echo "ℹ️  Nenhum usuário encontrado.\n\n";
    }
    
    // Verificar se existe um usuário de teste
    $testUser = \App\Models\User::where('email', 'test@example.com')->first();
    
    if (!$testUser) {
        echo "🔧 Criando usuário de teste...\n";
        
        $testUser = \App\Models\User::create([
            'name' => 'Usuário Teste',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);
        
        echo "✅ Usuário de teste criado com sucesso!\n";
        echo "📧 Email: test@example.com\n";
        echo "🔑 Senha: password123\n\n";
    } else {
        echo "✅ Usuário de teste já existe!\n";
        echo "📧 Email: test@example.com\n";
        echo "🔑 Senha: password123\n\n";
    }
    
    // Verificar resgates existentes
    $redemptions = \App\Models\Redemption::count();
    echo "📦 Total de resgates no sistema: $redemptions\n";
    
    if ($redemptions === 0) {
        echo "ℹ️  Nenhum resgate encontrado. Criando um resgate de teste...\n";
        
        // Verificar se existe algum produto
        $product = \App\Models\Post::where('post_type', 'product')->first();
        
        if ($product) {
            $redemption = \App\Models\Redemption::create([
                'user_id' => $testUser->id,
                'product_id' => $product->id,
                'pontos' => 100,
                'ativo' => 's',
                'excluido' => 'n',
                'deletado' => 'n',
                'author' => $testUser->id,
            ]);
            
            echo "✅ Resgate de teste criado com ID: {$redemption->id}\n";
        } else {
            echo "⚠️  Nenhum produto encontrado para criar resgate de teste\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "📍 Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== FIM DA VERIFICAÇÃO ===\n";