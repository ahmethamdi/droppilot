<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_stores', function (Blueprint $table) {
            // Shopify paketi Shop kaydını yaratırken tenant_id'yi bilmez.
            // OAuth callback sonrası ShopifyStoreObserver tenant_id'yi yapıştırır.
            $table->dropForeign(['tenant_id']);
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shopify_stores', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }
};
