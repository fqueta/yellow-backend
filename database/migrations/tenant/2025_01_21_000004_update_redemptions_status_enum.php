<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration para atualizar os valores do enum status na tabela redemptions
 * Remove 'approved' e adiciona 'confirmed'
 */
return new class extends Migration
{
    /**
     * Executar a migration
     */
    public function up(): void
    {
        // Verificar se a tabela existe
        if (Schema::hasTable('redemptions')) {
            // Primeiro, atualizar registros existentes com status 'approved' para 'confirmed'
            DB::table('redemptions')
                ->where('status', 'approved')
                ->update(['status' => 'confirmed']);
            
            // Alterar a coluna enum para os novos valores
            DB::statement("ALTER TABLE redemptions MODIFY COLUMN status ENUM('pending', 'processing', 'confirmed', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending' COMMENT 'Status do resgate'");
        }
    }

    /**
     * Reverter a migration
     */
    public function down(): void
    {
        // Verificar se a tabela existe
        if (Schema::hasTable('redemptions')) {
            // Primeiro, atualizar registros existentes com status 'confirmed' para 'approved'
            DB::table('redemptions')
                ->where('status', 'confirmed')
                ->update(['status' => 'approved']);
            
            // Reverter a coluna enum para os valores antigos
            DB::statement("ALTER TABLE redemptions MODIFY COLUMN status ENUM('pending', 'approved', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending' COMMENT 'Status do resgate'");
        }
    }
};