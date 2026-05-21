<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();

            // Plenty item
            $table->unsignedBigInteger('plenty_item_id');
            $table->unsignedBigInteger('main_variation_id')->nullable();
            $table->unsignedInteger('manufacturer_id')->nullable();
            $table->unsignedTinyInteger('item_type_id')->nullable();

            // Display
            $table->string('name')->nullable();
            $table->string('name2')->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->text('meta_description')->nullable();

            // Sync state
            $table->json('payload')->nullable(); // ham response (debug için)
            $table->timestamp('plenty_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();

            $table->unique(['supplier_id', 'plenty_item_id']);
            $table->index('main_variation_id');
            $table->index('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
