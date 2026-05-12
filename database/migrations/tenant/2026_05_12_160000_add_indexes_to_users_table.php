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
        Schema::table('users', function (Blueprint $table) {
            // Laravel 12 standard native schema indices
            $table->index('status');
            $table->index('permission_id');
            $table->index('created_at');
            $table->index('cnpj');
            $table->index(['excluido', 'deletado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['permission_id']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['cnpj']);
            $table->dropIndex(['excluido', 'deletado']);
        });
    }
};
