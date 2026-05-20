<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('kind'); // referrer | warehouse | order_status | plenty_id
            $table->string('external_id'); // Plenty tarafındaki ID (string — referrer "1.00" gibi olabiliyor)
            $table->string('label')->nullable(); // gösterim için
            $table->json('payload')->nullable(); // ham obje
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'kind', 'external_id']);
            $table->index(['supplier_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_references');
    }
};
