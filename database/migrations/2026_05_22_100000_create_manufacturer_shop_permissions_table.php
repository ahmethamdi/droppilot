<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bir Hersteller'ın ürünlerini hangi B2B Shopify shop'larına push etmemize
 * izin verildiğini tutar. Admin Hersteller detayında shop'ları seçer.
 *
 * Anahtar: (supplier_id, manufacturer_id, shopify_store_id) — bir kombinasyon
 * için tek satır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturer_shop_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->unsignedInteger('manufacturer_id');
            $table->string('manufacturer_name')->nullable();
            $table->foreignId('shopify_store_id')->constrained('shopify_stores')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['supplier_id', 'manufacturer_id', 'shopify_store_id'], 'msp_unique');
            $table->index(['supplier_id', 'manufacturer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturer_shop_permissions');
    }
};
