<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Plenty'de seçilecek sales_price ID. Auftrag payload'ında
            // priceOriginalGross bu fiyat tipinden çekilir.
            $table->unsignedInteger('default_sales_price_id')
                ->nullable()
                ->after('default_order_status_id');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('default_sales_price_id');
        });
    }
};
