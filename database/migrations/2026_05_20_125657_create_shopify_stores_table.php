<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_stores', function (Blueprint $table) {
            $table->id();

            // Multi-tenant: hangi bayiye ait
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // kyon147/laravel-shopify paketinin Shop modeli bu sütunları kullanır.
            // Aşağıdaki isimlendirme paketin Util/IShopModel beklentilerine uygundur.
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password', 100)->nullable(); // legacy, kullanmayacağız ama paket bekleyebilir

            $table->string('shopify_domain')->unique(); // örn. droppilots.myshopify.com
            $table->text('shopify_token')->nullable();  // online/offline access token
            $table->text('shopify_offline_refresh_token')->nullable();
            $table->timestamp('shopify_offline_access_token_expires_at')->nullable();
            $table->timestamp('shopify_offline_refresh_token_expires_at')->nullable();
            $table->string('shopify_namespace')->nullable();
            $table->boolean('shopify_grandfathered')->default(false);
            $table->boolean('shopify_freemium')->default(false);
            $table->integer('plan_id')->unsigned()->nullable();

            // DropPilot-özel meta
            $table->json('scopes')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_stores');
    }
};
