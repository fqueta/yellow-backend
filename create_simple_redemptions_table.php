<?php

/**
 * Script para criar a tabela redemptions sem chaves estrangeiras
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CRIAÇÃO SIMPLES DA TABELA REDEMPTIONS ===\n\n";

try {
    echo "🗑️  Removendo tabela 'redemptions' existente...\n";
    \Illuminate\Support\Facades\Schema::dropIfExists('redemptions');
    echo "✅ Tabela removida com sucesso!\n\n";
    
    echo "🔧 Criando tabela 'redemptions' sem chaves estrangeiras...\n";
    \Illuminate\Support\Facades\Schema::create('redemptions', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->comment('ID do usuário que fez o resgate');
        $table->unsignedBigInteger('product_id')->comment('ID do produto resgatado');
        $table->integer('quantity')->default(1)->comment('Quantidade de produtos resgatados');
        $table->decimal('pontos', 10, 2)->comment('Total de pontos utilizados (legacy)');
        $table->decimal('points_used', 10, 2)->comment('Quantidade de pontos utilizados');
        $table->decimal('unit_points', 10, 2)->comment('Pontos por unidade do produto');
        $table->enum('status', ['pending', 'approved', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending')->comment('Status do resgate');
        $table->json('delivery_address')->nullable()->comment('Endereço de entrega em JSON');
        $table->date('estimated_delivery_date')->nullable()->comment('Data estimada de entrega');
        $table->date('actual_delivery_date')->nullable()->comment('Data real de entrega');
        $table->text('notes')->nullable()->comment('Observações do resgate');
        $table->text('admin_notes')->nullable()->comment('Notas administrativas');
        $table->json('product_snapshot')->nullable()->comment('Dados do produto no momento do resgate');
        $table->string('autor')->nullable()->comment('ID do usuário que criou o registro');
        $table->enum('ativo', ['s', 'n'])->default('s')->comment('Registro ativo');
        $table->enum('excluido', ['s', 'n'])->default('n')->comment('Registro excluído');
        $table->enum('deletado', ['s', 'n'])->default('n')->comment('Registro deletado');
        $table->timestamps();
        $table->softDeletes();
        
        // Índices para melhor performance
        $table->index(['user_id', 'status']);
        $table->index(['product_id']);
        $table->index(['created_at']);
    });
    
    echo "✅ Tabela 'redemptions' criada com sucesso!\n\n";
    
    // Verificar se a tabela foi criada corretamente
    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('redemptions');
    echo "📊 Colunas criadas: " . implode(', ', $columns) . "\n\n";
    
    // Criar alguns dados de teste
    echo "🧪 Criando dados de teste...\n";
    
    // Verificar se existe usuário
    $user = \App\Models\User::first();
    if (!$user) {
        echo "❌ Nenhum usuário encontrado. Criando usuário de teste...\n";
        $user = \App\Models\User::create([
            'name' => 'Usuário Teste',
            'email' => 'teste@redemption.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);
        echo "✅ Usuário de teste criado: {$user->email}\n";
    }
    
    // Verificar se existe produto
    $product = \App\Models\Post::where('post_type', 'product')->first();
    if (!$product) {
        echo "❌ Nenhum produto encontrado. Criando produto de teste...\n";
        $product = \App\Models\Post::create([
            'post_title' => 'Produto Teste para Resgate',
            'post_content' => 'Descrição do produto de teste',
            'post_status' => 'publish',
            'post_type' => 'product',
            'author' => $user->id,
            'guid' => \Illuminate\Support\Str::uuid(),
        ]);
        echo "✅ Produto de teste criado: {$product->post_title}\n";
    }
    
    // Criar resgate de teste
    $redemption = \App\Models\Redemption::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'pontos' => 100,
        'points_used' => 100,
        'unit_points' => 100,
        'status' => 'pending',
        'autor' => $user->id,
        'ativo' => 's',
        'excluido' => 'n',
        'deletado' => 'n',
    ]);
    
    echo "✅ Resgate de teste criado com ID: {$redemption->id}\n";
    
    // Testar busca de resgates
    echo "\n🔍 Testando busca de resgates...\n";
    $redemptions = \App\Models\Redemption::with(['product', 'user'])->get();
    echo "📦 Total de resgates encontrados: " . $redemptions->count() . "\n";
    
    if ($redemptions->count() > 0) {
        $firstRedemption = $redemptions->first();
        echo "🔍 Primeiro resgate:\n";
        echo "  - ID: {$firstRedemption->id}\n";
        echo "  - User: {$firstRedemption->user->name}\n";
        echo "  - Produto: {$firstRedemption->product->post_title}\n";
        echo "  - Pontos: {$firstRedemption->pontos}\n";
        echo "  - Status: {$firstRedemption->status}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "📍 Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== FIM DA CRIAÇÃO ===\n";