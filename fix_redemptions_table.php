<?php

/**
 * Script para verificar e corrigir a estrutura da tabela redemptions
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== VERIFICAÇÃO E CORREÇÃO DA TABELA REDEMPTIONS ===\n\n";

try {
    // Verificar se a tabela existe
    $tableExists = \Illuminate\Support\Facades\Schema::hasTable('redemptions');
    echo "📋 Tabela 'redemptions' existe: " . ($tableExists ? 'SIM' : 'NÃO') . "\n";
    
    if ($tableExists) {
        // Verificar colunas existentes
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('redemptions');
        echo "📊 Colunas existentes: " . implode(', ', $columns) . "\n\n";
        
        // Verificar se a coluna deleted_at existe
        $hasDeletedAt = \Illuminate\Support\Facades\Schema::hasColumn('redemptions', 'deleted_at');
        echo "🗑️  Coluna 'deleted_at' existe: " . ($hasDeletedAt ? 'SIM' : 'NÃO') . "\n";
        
        if (!$hasDeletedAt) {
            echo "🔧 Adicionando coluna 'deleted_at'...\n";
            \Illuminate\Support\Facades\Schema::table('redemptions', function ($table) {
                $table->softDeletes();
            });
            echo "✅ Coluna 'deleted_at' adicionada com sucesso!\n";
        }
        
        // Verificar outras colunas necessárias
        $requiredColumns = ['user_id', 'product_id', 'pontos', 'status'];
        foreach ($requiredColumns as $column) {
            $hasColumn = \Illuminate\Support\Facades\Schema::hasColumn('redemptions', $column);
            echo "📋 Coluna '$column' existe: " . ($hasColumn ? 'SIM' : 'NÃO') . "\n";
        }
        
        // Tentar buscar resgates agora
        echo "\n🔍 Testando busca de resgates...\n";
        $redemptions = \App\Models\Redemption::count();
        echo "📦 Total de resgates encontrados: $redemptions\n";
        
        if ($redemptions > 0) {
            $firstRedemption = \App\Models\Redemption::first();
            echo "🔍 Primeiro resgate:\n";
            echo "  - ID: {$firstRedemption->id}\n";
            echo "  - User ID: {$firstRedemption->user_id}\n";
            echo "  - Product ID: {$firstRedemption->product_id}\n";
            echo "  - Pontos: {$firstRedemption->pontos}\n";
            echo "  - Status: {$firstRedemption->status}\n";
        }
        
    } else {
        echo "❌ Tabela 'redemptions' não existe. Execute as migrações primeiro.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "📍 Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    // Se o erro for sobre a coluna deleted_at, tentar criar a tabela do zero
    if (strpos($e->getMessage(), 'deleted_at') !== false) {
        echo "\n🔧 Tentando recriar a tabela com a estrutura correta...\n";
        
        try {
            \Illuminate\Support\Facades\Schema::dropIfExists('redemptions');
            
            \Illuminate\Support\Facades\Schema::create('redemptions', function ($table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('product_id')->constrained('posts')->onDelete('cascade');
                $table->integer('quantity')->default(1);
                $table->decimal('pontos', 10, 2);
                $table->decimal('points_used', 10, 2);
                $table->decimal('unit_points', 10, 2);
                $table->enum('status', ['pending', 'approved', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
                $table->json('delivery_address')->nullable();
                $table->date('estimated_delivery_date')->nullable();
                $table->date('actual_delivery_date')->nullable();
                $table->text('notes')->nullable();
                $table->text('admin_notes')->nullable();
                $table->json('product_snapshot')->nullable();
                $table->string('autor')->nullable();
                $table->enum('ativo', ['s', 'n'])->default('s');
                $table->enum('excluido', ['s', 'n'])->default('n');
                $table->enum('deletado', ['s', 'n'])->default('n');
                $table->timestamps();
                $table->softDeletes();
            });
            
            echo "✅ Tabela 'redemptions' recriada com sucesso!\n";
            
        } catch (Exception $createError) {
            echo "❌ Erro ao recriar tabela: " . $createError->getMessage() . "\n";
        }
    }
}

echo "\n=== FIM DA VERIFICAÇÃO ===\n";