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
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->index();
            $table->enum('status', ['success', 'warning', 'error', 'info'])->default('info')->index();
            $table->text('description');
            $table->json('metadata')->nullable();
            
            // Controle padrão das outras tabelas se necessário
            $table->string('ativo', 1)->default('s');
            $table->string('excluido', 1)->default('n');
            $table->string('deletado', 1)->default('n');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
