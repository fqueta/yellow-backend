<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration para criar a tabela de pontos dos clientes
 * Gerencia sistema de pontuação/recompensas
 */
return new class extends Migration
{
    /**
     * Executar a migration
     */
    public function up(): void
    {
        Schema::create('points', function (Blueprint $table) {
            $table->id();
            
            // Relacionamento com cliente
            $table->string('client_id')->index();
            
            // Dados dos pontos
            $table->decimal('valor', 10, 2)->comment('Quantidade de pontos');
            $table->date('data')->comment('Data da movimentação dos pontos');
            $table->text('description')->nullable()->comment('Descrição da movimentação');
            
            // Campos adicionais para controle
            $table->enum('tipo', ['credito', 'debito'])->default('credito')->comment('Tipo da movimentação');
            $table->string('origem')->nullable()->comment('Origem dos pontos (compra, bonus, etc)');
            $table->decimal('valor_referencia', 10, 2)->nullable()->comment('Valor em reais que gerou os pontos');
            $table->date('data_expiracao')->nullable()->comment('Data de expiração dos pontos');
            $table->enum('status', ['ativo', 'expirado', 'usado', 'cancelado'])->default('ativo');
            
            // Relacionamentos opcionais
            $table->string('usuario_id')->nullable()->comment('Usuário que registrou a movimentação');
            $table->string('pedido_id')->nullable()->comment('ID do pedido relacionado');
            
            // Campos de configuração
            $table->json('config')->nullable()->comment('Configurações adicionais em JSON');
            
            // Campos de controle do sistema
            $table->string('autor')->nullable()->comment('ID do usuário que criou o registro');
            $table->enum('ativo', ['s', 'n'])->default('s')->comment('Registro ativo');
            $table->enum('excluido', ['s', 'n'])->default('n')->comment('Registro excluído');
            $table->enum('deletado', ['s', 'n'])->default('n')->comment('Registro deletado');
            
            // Timestamps e soft delete
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para performance
            $table->index(['client_id', 'status']);
            $table->index(['data', 'status']);
            $table->index(['tipo', 'status']);
            $table->index(['data_expiracao']);
            $table->index(['ativo', 'excluido', 'deletado']);
        });
    }

    /**
     * Reverter a migration
     */
    public function down(): void
    {
        Schema::dropIfExists('points');
    }
};