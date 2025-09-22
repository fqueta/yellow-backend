<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('financial', function (Blueprint $table) {
            $table->id();
            
            // Informações básicas
            $table->string('titulo'); // Título da movimentação
            $table->text('descricao')->nullable(); // Descrição detalhada
            
            // Tipo de movimentação
            $table->enum('tipo', ['receber', 'pagar']); // contas a receber ou contas a pagar
            $table->enum('categoria', ['receita', 'despesa', 'transferencia'])->nullable(); // categoria da movimentação
            
            // Valores financeiros
            $table->decimal('valor', 12, 2); // Valor da movimentação
            $table->decimal('valor_pago', 12, 2)->default(0); // Valor já pago/recebido
            $table->decimal('desconto', 12, 2)->default(0); // Desconto aplicado
            $table->decimal('juros', 12, 2)->default(0); // Juros aplicados
            $table->decimal('multa', 12, 2)->default(0); // Multa aplicada
            
            // Datas importantes
            $table->date('data_vencimento'); // Data de vencimento
            $table->date('data_pagamento')->nullable(); // Data do pagamento/recebimento
            $table->date('data_competencia')->nullable(); // Data de competência
            
            // Status e controle
            $table->enum('status', ['pendente', 'pago', 'parcial', 'vencido', 'cancelado'])->default('pendente');
            $table->integer('parcela_atual')->default(1); // Parcela atual
            $table->integer('total_parcelas')->default(1); // Total de parcelas
            
            // Relacionamentos
            $table->string('cliente_id')->nullable(); // ID do cliente (se for conta a receber)
            $table->string('fornecedor_id')->nullable(); // ID do fornecedor (se for conta a pagar)
            $table->string('usuario_id')->nullable(); // ID do usuário responsável
            
            // Informações adicionais
            $table->string('documento')->nullable(); // Número do documento/nota fiscal
            $table->string('forma_pagamento')->nullable(); // Forma de pagamento
            $table->string('conta_bancaria')->nullable(); // Conta bancária
            $table->json('config')->nullable(); // Configurações adicionais
            
            // Controle padrão do sistema
            $table->string('autor')->nullable();
            $table->string('token', 60)->nullable();
            $table->enum('ativo', ['s', 'n'])->default('s');
            $table->enum('excluido', ['n', 's'])->default('n');
            $table->text('reg_excluido')->nullable();
            $table->enum('deletado', ['n', 's'])->default('n');
            $table->text('reg_deletado')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para melhor performance
            $table->index(['tipo']);
            $table->index(['status']);
            $table->index(['data_vencimento']);
            $table->index(['cliente_id']);
            $table->index(['fornecedor_id']);
            $table->index(['usuario_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial');
    }
};