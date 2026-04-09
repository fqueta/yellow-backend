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
        Schema::table('points', function (Blueprint $table) {
            // Alterar o campo tipo de ENUM para STRING para permitir novos tipos como 'expired', 'bonus', etc.
            $table->string('tipo', 20)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('points', function (Blueprint $table) {
            $table->enum('tipo', ['credito', 'debito'])->default('credito')->change();
        });
    }
};
