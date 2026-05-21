<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // Plenty variation
            $table->unsignedBigInteger('plenty_variation_id');
            $table->string('sku')->nullable(); // = Plenty variation.number (SKU)
            $table->string('model')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true);

            // Price snapshot
            $table->decimal('retail_price', 12, 2)->nullable();
            $table->unsignedInteger('retail_price_source_id')->nullable(); // hangi salesPriceId
            $table->string('currency', 3)->default('EUR');

            // Inventory snapshot
            $table->decimal('stock_net', 12, 2)->nullable();

            // Physical (Plenty)
            $table->unsignedInteger('weight_g')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();

            // Image
            $table->string('image_url')->nullable();

            $table->json('payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'plenty_variation_id']);
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};
