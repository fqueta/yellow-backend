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
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('url')->nullable();
            $table->string('icon')->nullable();
            $table->integer('order')->default(0);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('redirect_login')->nullable();
            $table->json('config')->nullable();
            $table->string('guard_name')->nullable();
            $table->string('autor')->nullable();
            $table->string('token')->nullable();
            $table->char('active', 1)->default('s');
            $table->char('excluido', 1)->default('n');
            $table->text('reg_excluido')->nullable();
            $table->char('deletado', 1)->default('n');
            $table->text('reg_deletado')->nullable();
            $table->timestamps();
        });

        Schema::create('menu_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->onDelete('cascade');
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
            $table->boolean('can_view')->default(true);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_upload')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('menus');
    }
};
