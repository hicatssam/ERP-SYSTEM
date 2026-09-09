<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_currencies', function (Blueprint $table) {
            $table->id();

            // ISO-like code: ILS, USD, EUR...
            $table->string('code', 10)->unique();

            $table->string('name_ar', 120);
            $table->string('name_en', 120)->nullable();

            // $, ₪, €, د.أ ...
            $table->string('symbol', 20);

            // Emoji أو مفتاح أيقونة نصي فقط، بدون أي اعتماد خارجي.
            $table->string('icon', 50)->nullable();

            // صورة/علم اختياري.
            $table->string('image')->nullable();

            $table->unsignedTinyInteger('decimal_places')->default(2);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_currencies');
    }
};
