<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Plenty manufacturer adı (örn. "ELFBAR V1 0% Paket")
            $table->string('manufacturer_name')->nullable()->after('manufacturer_id');
            // "Paket" kelimesi içeriyorsa true — Shopify B2B satışı için
            $table->boolean('is_package')->default(false)->after('manufacturer_name');

            $table->index('is_package');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['manufacturer_name', 'is_package']);
        });
    }
};
