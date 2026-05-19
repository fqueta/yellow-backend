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
        // 1. Otimizar tabela 'usermeta'
        Schema::table('usermeta', function (Blueprint $table) {
            // meta_key precisa ser do tipo string para permitir indexação padrão no MySQL
            $table->string('meta_key', 191)->nullable()->change();
            
            // Adicionar índice composto para busca instantânea de metadados
            $table->index(['user_id', 'meta_key']);
        });

        // 2. Otimizar tabela 'options'
        Schema::table('options', function (Blueprint $table) {
            // Adicionar índice em 'name' para otimizar leituras do Qlib::qoption
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Reverter tabela 'usermeta'
        Schema::table('usermeta', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'meta_key']);
            $table->text('meta_key')->nullable()->change();
        });

        // 2. Reverter tabela 'options'
        Schema::table('options', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });
    }
};
