<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_pushed_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->constrained('shopify_stores')->cascadeOnDelete();

            // Shopify side
            $table->unsignedBigInteger('shopify_product_id')->nullable();
            $table->string('sku')->nullable(); // hızlı arama için

            // State
            $table->string('state')->default('pending'); // pending | success | failed | skipped
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('pushed_at')->nullable();
            $table->json('shopify_payload')->nullable(); // dönen ham response

            $table->timestamps();

            $table->unique(['product_id', 'shopify_store_id']);
            $table->index('shopify_product_id');
            $table->index('sku');
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_pushed_products');
    }
};
