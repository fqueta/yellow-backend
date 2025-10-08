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
        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ID do usuário que fez o resgate');
            $table->foreignId('product_id')->constrained('posts')->onDelete('cascade')->comment('ID do produto resgatado');
            $table->integer('quantity')->default(1)->comment('Quantidade de produtos resgatados');
            $table->decimal('pontos', 10, 2)->comment('Total de pontos utilizados (legacy)');
            $table->decimal('points_used', 10, 2)->comment('Quantidade de pontos utilizados');
            $table->decimal('unit_points', 10, 2)->comment('Pontos por unidade do produto');
            $table->enum('status', ['pending', 'processing', 'confirmed', 'shipped', 'delivered', 'cancelled'])->default('pending')->comment('Status do resgate');
            $table->json('delivery_address')->nullable()->comment('Endereço de entrega em JSON');
            $table->date('estimated_delivery_date')->nullable()->comment('Data estimada de entrega');
            $table->date('actual_delivery_date')->nullable()->comment('Data real de entrega');
            $table->text('notes')->nullable()->comment('Observações do resgate');
            $table->text('admin_notes')->nullable()->comment('Notas administrativas');
            $table->json('product_snapshot')->nullable()->comment('Dados do produto no momento do resgate');
            $table->json('config')->nullable()->comment('Configurações do resgate');
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('redemptions');
    }
};
