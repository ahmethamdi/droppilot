<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Plenty'de B2B müşterilerin classId listesi (admin tarafından girilir).
            // Ör: [12, 50, 1] — sadece bu class'lardaki contact'lar B2B sayılır,
            // companyName check ile çapraz doğrulanır. Boşsa tüm contact'lar
            // companyName non-empty filtresinden geçer (yavaş, fallback).
            $table->json('b2b_class_ids')->nullable()->after('default_sales_price_id');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('b2b_class_ids');
        });
    }
};
