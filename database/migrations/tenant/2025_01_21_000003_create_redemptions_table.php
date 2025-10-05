<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration para criar a tabela de resgates de pontos
 * Gerencia pedidos de desconto de pontos por produtos
 */
return new class extends Migration
{
    /**
     * Executar a migration
     */
    public function up(): void
    {
        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            
            // Relacionamento com usuário
            $table->string('user_id')->index()->comment('ID do usuário que fez o resgate');
            
            // Relacionamento com produto
            $table->unsignedInteger('product_id')->index()->comment('ID do produto resgatado');
            
            // Dados do resgate
            $table->integer('quantity')->default(1)->comment('Quantidade de produtos resgatados');
            $table->decimal('points_used', 10, 2)->comment('Quantidade de pontos utilizados');
            $table->decimal('unit_points', 10, 2)->comment('Pontos por unidade do produto');
            
            // Status do resgate
            $table->enum('status', ['pending', 'approved', 'processing', 'shipped', 'delivered', 'cancelled'])
                  ->default('pending')
                  ->comment('Status do resgate');
            
            // Dados de entrega
            $table->json('delivery_address')->nullable()->comment('Endereço de entrega em JSON');
            $table->date('estimated_delivery_date')->nullable()->comment('Data estimada de entrega');
            $table->date('actual_delivery_date')->nullable()->comment('Data real de entrega');
            
            // Observações e notas
            $table->text('notes')->nullable()->comment('Observações do resgate');
            $table->text('admin_notes')->nullable()->comment('Notas administrativas');
            
            // Dados do produto no momento do resgate (snapshot)
            $table->json('product_snapshot')->nullable()->comment('Dados do produto no momento do resgate');
            
            // Campos de controle do sistema
            $table->string('autor')->nullable()->comment('ID do usuário que criou o registro');
            $table->enum('ativo', ['s', 'n'])->default('s')->comment('Registro ativo');
            $table->enum('excluido', ['s', 'n'])->default('n')->comment('Registro excluído');
            $table->enum('deletado', ['s', 'n'])->default('n')->comment('Registro deletado');
            
            // Timestamps e soft delete
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para performance
            $table->index(['user_id', 'status']);
            $table->index(['product_id', 'status']);
            $table->index(['status']);
            $table->index(['created_at']);
            $table->index(['ativo', 'excluido', 'deletado']);
            
            // Índices para melhor performance
            // Nota: Chave estrangeira removida para compatibilidade com a estrutura existente
        });
    }

    /**
     * Reverter a migration
     */
    public function down(): void
    {
        Schema::dropIfExists('redemptions');
    }
};