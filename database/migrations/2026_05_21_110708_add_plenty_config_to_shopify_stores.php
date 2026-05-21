<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plenty fiyat/depo ayarları ShopifyStore başına özelleşir (per-mağaza).
     * Aynı tedarikçide farklı bayilerin farklı fiyat tipleri olabilir (örn. Level 5 vs Standard).
     */
    public function up(): void
    {
        Schema::table('shopify_stores', function (Blueprint $table) {
            // Plenty'deki bağlı Supplier (tedarikçi) — fiyat/depo bu supplier'ın referans verisinden gelir.
            $table->foreignId('supplier_id')->nullable()->after('tenant_id')->constrained('suppliers')->nullOnDelete();

            // Bu mağazadan gelen siparişlerin Plenty Auftrag'da Rechnungsadresse'si.
            $table->unsignedBigInteger('plenty_contact_id')->nullable()->after('supplier_id');

            // Bu mağaza için Plenty Auftrag oluştururken kullanılacak fiyat tipi (Level 5, B2B Standard, vb.)
            $table->unsignedInteger('plenty_sales_price_id')->nullable()->after('plenty_contact_id');

            // Bu mağaza için Plenty Auftrag oluştururken kullanılacak depo (Hilden, vb.)
            $table->unsignedInteger('plenty_warehouse_id')->nullable()->after('plenty_sales_price_id');

            // Bu mağaza için yeni Auftrag'ların başlangıç statüsü (örn. "7 | Auftrag bestätigt")
            $table->decimal('plenty_order_status_id', 6, 2)->nullable()->after('plenty_warehouse_id');

            $table->index('supplier_id');
            $table->index('plenty_contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('shopify_stores', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn([
                'supplier_id',
                'plenty_contact_id',
                'plenty_sales_price_id',
                'plenty_warehouse_id',
                'plenty_order_status_id',
            ]);
        });
    }
};
