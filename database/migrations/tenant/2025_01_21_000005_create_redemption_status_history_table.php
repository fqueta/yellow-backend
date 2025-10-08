<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration para criar a tabela de histórico de status dos resgates
 * Registra todas as mudanças de status com informações detalhadas
 */
return new class extends Migration
{
    /**
     * Executar a migration
     */
    public function up(): void
    {
        Schema::create('redemption_status_history', function (Blueprint $table) {
            $table->id();
            
            // Relacionamento com o resgate
            $table->unsignedBigInteger('redemption_id')->index()->comment('ID do resgate');
            
            // Status anterior e novo
            $table->enum('old_status', ['pending', 'processing', 'confirmed', 'shipped', 'delivered', 'cancelled'])
                  ->nullable()
                  ->comment('Status anterior');
            $table->enum('new_status', ['pending', 'processing', 'confirmed', 'shipped', 'delivered', 'cancelled'])
                  ->comment('Novo status');
            
            // Comentário sobre a mudança
            $table->text('comment')->nullable()->comment('Comentário sobre a mudança de status');
            
            // Quem fez a mudança
            $table->string('created_by')->comment('ID ou identificador de quem fez a mudança');
            $table->string('created_by_name')->comment('Nome de quem fez a mudança');
            
            // Timestamps
            $table->timestamps();
            
            // Índices para performance
            $table->index(['redemption_id', 'created_at']);
            $table->index(['new_status']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverter a migration
     */
    public function down(): void
    {
        Schema::dropIfExists('redemption_status_history');
    }
};