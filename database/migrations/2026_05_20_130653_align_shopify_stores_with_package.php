<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // kyon147/laravel-shopify paketi shop domain'i `name` kolonunda,
        // access_token'i `password` kolonunda tutuyor (User-as-Shop legacy).
        // O yüzden bizim ek `shopify_domain` + `shopify_token` kolonları gereksiz.
        Schema::table('shopify_stores', function (Blueprint $table) {
            $table->dropUnique(['shopify_domain']);
            $table->dropColumn(['shopify_domain', 'shopify_token']);
        });

        Schema::table('shopify_stores', function (Blueprint $table) {
            // name = shopify domain — unique olmalı
            $table->unique('name');
            // password kolon zaten 100 char; access_token uzun olabilir, genişlet
            $table->text('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shopify_stores', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->string('password', 100)->nullable()->change();
        });

        Schema::table('shopify_stores', function (Blueprint $table) {
            $table->string('shopify_domain')->unique()->nullable();
            $table->text('shopify_token')->nullable();
        });
    }
};
