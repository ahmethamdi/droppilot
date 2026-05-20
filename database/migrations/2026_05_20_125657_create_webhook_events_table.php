<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('source'); // shopify | plenty
            $table->string('topic')->nullable();
            $table->string('external_id'); // shopify: X-Shopify-Webhook-Id
            $table->foreignId('shopify_store_id')->nullable()->constrained('shopify_stores')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('status')->default('received'); // received | processing | done | failed
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['source', 'topic']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
