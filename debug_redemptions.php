<?php

/**
 * Script para debugar resgates e produtos no sistema
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== DEBUG DE RESGATES E PRODUTOS ===\n\n";

try {
    // Verificar resgates existentes
    $redemptions = \App\Models\Redemption::with(['product', 'user'])->get();
    echo "📦 Total de resgates no sistema: " . $redemptions->count() . "\n\n";
    
    if ($redemptions->count() > 0) {
        echo "🔍 Primeiros 3 resgates:\n";
        foreach ($redemptions->take(3) as $redemption) {
            echo "  - ID: {$redemption->id}\n";
            echo "    User ID: {$redemption->user_id}\n";
            echo "    Product ID: {$redemption->product_id}\n";
            echo "    Pontos: {$redemption->pontos}\n";
            echo "    Status: {$redemption->status}\n";
            echo "    Produto: " . ($redemption->product ? $redemption->product->post_title : 'NULL') . "\n";
            echo "    Usuário: " . ($redemption->user ? $redemption->user->name : 'NULL') . "\n";
            echo "\n";
        }
    }
    
    // Verificar produtos existentes
    $products = \App\Models\Post::where('post_type', 'product')->get();
    echo "🛍️  Total de produtos no sistema: " . $products->count() . "\n\n";
    
    if ($products->count() > 0) {
        echo "🔍 Primeiros 3 produtos:\n";
        foreach ($products->take(3) as $product) {
            echo "  - ID: {$product->id}\n";
            echo "    GUID: {$product->guid}\n";
            echo "    Título: {$product->post_title}\n";
            echo "    Status: {$product->post_status}\n";
            echo "\n";
        }
    }
    
    // Verificar usuários com tokens
    $users = \App\Models\User::with('tokens')->get();
    echo "👥 Total de usuários no sistema: " . $users->count() . "\n\n";
    
    if ($users->count() > 0) {
        echo "🔍 Usuários com tokens:\n";
        foreach ($users as $user) {
            $tokenCount = $user->tokens->count();
            echo "  - ID: {$user->id}, Nome: {$user->name}, Email: {$user->email}, Tokens: {$tokenCount}\n";
            
            if ($tokenCount > 0) {
                foreach ($user->tokens->take(2) as $token) {
                    echo "    Token: " . substr($token->token, 0, 20) . "... (ID: {$token->id})\n";
                }
            }
        }
    }
    
    // Testar o método get_category_by_id se existir produto
    if ($products->count() > 0) {
        $firstProduct = $products->first();
        echo "\n🧪 Testando get_category_by_id com produto ID: {$firstProduct->guid}\n";
        
        try {
            $categoryData = \App\Services\Qlib::get_category_by_id($firstProduct->guid);
            echo "✅ Categoria encontrada: " . json_encode($categoryData) . "\n";
        } catch (Exception $e) {
            echo "❌ Erro ao buscar categoria: " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "📍 Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== FIM DO DEBUG ===\n";