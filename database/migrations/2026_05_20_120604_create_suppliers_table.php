<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('kind')->default('plenty'); // plenty | future: shopify_b2b, woocommerce, ...
            $table->string('plenty_base_url')->nullable();
            $table->text('plenty_login_user')->nullable();         // encrypted
            $table->text('plenty_login_password')->nullable();     // encrypted
            $table->unsignedInteger('default_warehouse_id')->nullable();
            $table->unsignedInteger('default_referrer_id')->nullable();
            $table->unsignedInteger('default_order_status_id')->nullable();
            $table->unsignedInteger('default_plenty_id')->nullable(); // Mandant
            $table->json('config')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tenant_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->unsignedBigInteger('plenty_contact_id')->nullable(); // bayinin tedarikçi Plenty'sindeki müşteri ID'si
            $table->unsignedBigInteger('default_billing_address_id')->nullable(); // sabit Rechnungsadresse
            $table->decimal('markup_pct', 6, 2)->default(0);
            $table->string('status')->default('pending'); // pending | active | suspended
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_supplier');
        Schema::dropIfExists('suppliers');
    }
};
