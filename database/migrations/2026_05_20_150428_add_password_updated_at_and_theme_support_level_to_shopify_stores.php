<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_stores', function (Blueprint $table) {
            // kyon147/laravel-shopify paketinin orijinal migration'larında bunlar
            // `users` tablosuna ekleniyordu. Bizim custom shopify_stores tablomuzda
            // eksikti — OAuth callback'inde access token persist edilirken patlıyordu.
            $table->date('password_updated_at')->nullable()->after('shopify_offline_refresh_token_expires_at');
            $table->integer('theme_support_level')->nullable()->after('password_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('shopify_stores', function (Blueprint $table) {
            $table->dropColumn(['password_updated_at', 'theme_support_level']);
        });
    }
};
