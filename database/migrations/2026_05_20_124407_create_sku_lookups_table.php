<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sku_lookups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('sku');
            $table->unsignedBigInteger('plenty_variation_id')->nullable();
            $table->unsignedBigInteger('plenty_item_id')->nullable();
            $table->string('name')->nullable();
            $table->decimal('supplier_price', 12, 2)->nullable();
            $table->unsignedInteger('supplier_price_source_id')->nullable(); // hangi salesPriceId
            $table->decimal('stock_net', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('found')->default(false); // false = Plenty'de yok (negative cache)
            $table->json('payload')->nullable(); // ham variation response
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'sku']);
            $table->index(['supplier_id', 'plenty_variation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_lookups');
    }
};
