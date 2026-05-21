<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plenty_orders', function (Blueprint $table) {
            $table->id();

            // Kaynak
            $table->foreignId('shopify_store_id')->constrained('shopify_stores')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->unsignedBigInteger('shopify_order_id'); // Shopify order ID
            $table->string('shopify_order_name')->nullable(); // #1001

            // Hedef
            $table->unsignedBigInteger('plenty_contact_id'); // Rechnung
            $table->unsignedBigInteger('plenty_address_id')->nullable(); // Lieferung (yaratılan adres)
            $table->unsignedBigInteger('plenty_order_id')->nullable(); // Auftrag ID

            // Bilgi
            $table->decimal('total', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->unsignedSmallInteger('items_count')->nullable();
            $table->unsignedSmallInteger('skipped_count')->default(0); // SKU bulunamayan satırlar

            // State
            $table->string('state')->default('pending'); // pending | success | failed
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('pushed_at')->nullable();
            $table->json('payload')->nullable(); // Plenty'ye gönderilen ham payload
            $table->json('response')->nullable(); // Plenty'den dönen ham response

            $table->timestamps();

            $table->unique(['shopify_store_id', 'shopify_order_id']);
            $table->index('plenty_order_id');
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plenty_orders');
    }
};
