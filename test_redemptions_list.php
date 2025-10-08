<?php

/**
 * Script de teste para verificar a listagem de resgates de usuário
 * Testa a nova rota GET /api/products/redemptions
 */

require_once 'vendor/autoload.php';

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Redemption;
use App\Models\Post;
use App\Http\Controllers\api\ProductController;

// Simular ambiente Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TESTE DE LISTAGEM DE RESGATES ===\n\n";

try {
    // Buscar um usuário existente para teste
    $user = User::first();
    if (!$user) {
        echo "❌ Nenhum usuário encontrado no sistema\n";
        exit(1);
    }
    
    echo "✅ Usuário encontrado: {$user->name} (ID: {$user->id})\n";
    
    // Verificar se existem resgates para este usuário
    $redemptionsCount = Redemption::where('user_id', $user->id)->count();
    echo "📊 Total de resgates do usuário: {$redemptionsCount}\n\n";
    
    if ($redemptionsCount === 0) {
        echo "ℹ️  Usuário não possui resgates. Criando um resgate de teste...\n";
        
        // Buscar um produto para criar um resgate de teste
        $product = Post::where('post_type', 'product')->first();
        if (!$product) {
            echo "❌ Nenhum produto encontrado para criar resgate de teste\n";
            exit(1);
        }
        
        // Criar um resgate de teste
        $redemption = Redemption::create([
            'user_id' => $user->id,
            'product_id' => $product->ID,
            'quantity' => 1,
            'points_used' => 1000,
            'unit_points' => 1000,
            'status' => 'delivered',
            'notes' => 'Resgate de teste criado automaticamente'
        ]);
        
        echo "✅ Resgate de teste criado (ID: {$redemption->id})\n\n";
    }
    
    // Simular request autenticado
    $request = Request::create('/api/products/redemptions', 'GET');
    $request->setUserResolver(function () use ($user) {
        return $user;
    });
    
    // Instanciar controller e chamar método
    $controller = new ProductController();
    $response = $controller->getUserRedemptions($request);
    
    // Verificar resposta
    $statusCode = $response->getStatusCode();
    $content = json_decode($response->getContent(), true);
    
    echo "📡 Status da resposta: {$statusCode}\n";
    
    if ($statusCode === 200) {
        echo "✅ Requisição bem-sucedida!\n\n";
        
        if (isset($content['data']) && is_array($content['data'])) {
            echo "📋 Resgates encontrados: " . count($content['data']) . "\n\n";
            
            foreach ($content['data'] as $index => $redemption) {
                echo "--- Resgate " . ($index + 1) . " ---\n";
                echo "ID: {$redemption['id']}\n";
                echo "Produto ID: {$redemption['productId']}\n";
                echo "Nome do Produto: {$redemption['productName']}\n";
                echo "Imagem: {$redemption['productImage']}\n";
                echo "Pontos Usados: {$redemption['pointsUsed']}\n";
                echo "Data do Resgate: {$redemption['redemptionDate']}\n";
                echo "Status: {$redemption['status']}\n";
                echo "Código de Rastreamento: {$redemption['trackingCode']}\n";
                echo "Categoria: {$redemption['category']}\n\n";
            }
            
            // Verificar estrutura dos dados
            $firstRedemption = $content['data'][0] ?? null;
            if ($firstRedemption) {
                $expectedFields = ['id', 'productId', 'productName', 'productImage', 'pointsUsed', 'redemptionDate', 'status', 'trackingCode', 'category'];
                $missingFields = [];
                
                foreach ($expectedFields as $field) {
                    if (!isset($firstRedemption[$field])) {
                        $missingFields[] = $field;
                    }
                }
                
                if (empty($missingFields)) {
                    echo "✅ Estrutura de dados está correta!\n";
                } else {
                    echo "⚠️  Campos faltando na estrutura: " . implode(', ', $missingFields) . "\n";
                }
            }
        } else {
            echo "⚠️  Nenhum resgate encontrado ou estrutura de dados incorreta\n";
        }
        
        echo "\n📄 Resposta completa:\n";
        echo json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        
    } else {
        echo "❌ Erro na requisição!\n";
        echo "Resposta: " . $response->getContent() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro durante o teste: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIM DO TESTE ===\n";