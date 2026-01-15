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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('empresa')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('tipo_pessoa')->nullable();
            $table->string('razao')->nullable();
            $table->string('cpf')->nullable();
            $table->string('cnpj')->nullable();
            $table->string('status')->nullable()->default('actived');
            $table->string('genero')->nullable();
            $table->char('verificado', 1)->default('n');
            $table->integer('permission_id')->nullable();
            $table->json('config')->nullable();
            $table->json('preferencias')->nullable();
            $table->string('foto_perfil')->nullable();
            $table->char('ativo', 1)->default('s');
            $table->string('autor')->nullable();
            $table->string('token')->nullable();
            $table->char('excluido', 1)->default('n');
            $table->text('reg_excluido')->nullable();
            $table->char('deletado', 1)->default('n');
            $table->text('reg_deletado')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
