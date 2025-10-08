<?php

require_once 'vendor/autoload.php';

// Configurar o ambiente Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

echo "=== CORRIGINDO COLUNA PONTOS ===".PHP_EOL.PHP_EOL;

try {
    // Conectar ao tenant yellow-dev
    $tenant = \App\Models\Tenant::where('id', 'yellow-dev')->first();
    if (!$tenant) {
        echo "❌ Tenant 'yellow-dev' não encontrado!".PHP_EOL;
        exit(1);
    }
    
    $tenant->run(function () {
        echo "🔧 Alterando coluna 'pontos' para ter valor padrão 0...".PHP_EOL;
        
        // Alterar a coluna pontos para ter valor padrão
        Schema::table('redemptions', function (Blueprint $table) {
            $table->integer('pontos')->default(0)->change();
        });
        
        echo "✅ Coluna 'pontos' alterada com sucesso!".PHP_EOL.PHP_EOL;
        
        echo "🧪 Testando inserção de dados...".PHP_EOL;
        
        // Buscar um usuário existente
        $user = \App\Models\User::first();
        if (!$user) {
            echo "❌ Nenhum usuário encontrado!".PHP_EOL;
            return;
        }
        
        // Buscar um produto existente
        $product = \App\Models\Product::first();
        if (!$product) {
            echo "❌ Nenhum produto encontrado!".PHP_EOL;
            return;
        }
        
        // Criar um resgate de teste
        $redemption = DB::table('redemptions')->insert([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'pontos' => 100, // Definindo explicitamente
            'points_used' => 100,
            'unit_points' => 100,
            'status' => 'pending',
            'ativo' => 's',
            'excluido' => 'n',
            'deletado' => 'n',
            'autor' => $user->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        if ($redemption) {
            echo "✅ Resgate de teste criado com sucesso!".PHP_EOL;
            
            // Verificar se foi inserido
            $count = DB::table('redemptions')->count();
            echo "📊 Total de resgates na tabela: {$count}".PHP_EOL;
        } else {
            echo "❌ Erro ao criar resgate de teste!".PHP_EOL;
        }
    });
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . PHP_EOL;
    echo "📍 Arquivo: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
}

echo PHP_EOL."=== FIM DA CORREÇÃO ===".PHP_EOL;