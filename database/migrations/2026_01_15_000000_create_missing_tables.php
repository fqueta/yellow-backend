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
        // Table: dashboard_metrics
        if (!Schema::hasTable('dashboard_metrics')) {
            Schema::create('dashboard_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable(); // or unsignedBigInteger if no relation enforced
                $table->date('period');
                $table->decimal('investment', 15, 2)->nullable();
                $table->integer('visitors')->nullable();
                $table->integer('bot_conversations')->nullable();
                $table->integer('human_conversations')->nullable();
                $table->integer('proposals')->nullable();
                $table->integer('closed_deals')->nullable();
                $table->timestamps();
            });
        }

        // Table: categories
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('entidade')->nullable();
                $table->string('description')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        // Table: options
        if (!Schema::hasTable('options')) {
            Schema::create('options', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->text('value')->nullable();
                $table->string('url')->nullable();
                $table->string('tags')->nullable();
                $table->timestamps();
            });
        }

        // Table: posts (used for products and product-units)
        if (!Schema::hasTable('posts')) {
            Schema::create('posts', function (Blueprint $table) {
                $table->id();
                $table->string('post_author')->nullable();
                $table->longText('post_content')->nullable();
                $table->string('post_title')->nullable();
                $table->text('post_excerpt')->nullable();
                $table->string('post_status')->default('publish');
                $table->string('comment_status')->default('closed');
                $table->string('ping_status')->default('closed');
                $table->string('post_password')->nullable();
                $table->string('post_name')->nullable()->index(); // slug
                $table->string('to_ping')->nullable();
                $table->string('pinged')->nullable();
                $table->text('post_content_filtered')->nullable();
                $table->unsignedBigInteger('post_parent')->default(0);
                $table->string('guid')->nullable();
                $table->integer('menu_order')->default(0);
                
                // Custom fields for products
                $table->decimal('post_value1', 15, 2)->nullable(); // costPrice
                $table->decimal('post_value2', 15, 2)->nullable(); // salePrice
                $table->string('post_type')->default('post')->index();
                $table->string('post_mime_type')->nullable();
                $table->integer('comment_count')->default(0); // stock
                $table->json('config')->nullable();
                $table->string('token')->nullable();

                // Soft deletes / generic status fields
                $table->char('excluido', 1)->default('n');
                $table->text('reg_excluido')->nullable();
                $table->char('deletado', 1)->default('n');
                $table->text('reg_deletado')->nullable();

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
        Schema::dropIfExists('options');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('dashboard_metrics');
    }
};
