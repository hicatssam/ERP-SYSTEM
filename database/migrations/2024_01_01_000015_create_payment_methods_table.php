<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar');
            $table->string('code', 30)->unique();
            $table->string('logo')->nullable();
            $table->enum('type', ['cash', 'electronic_wallet', 'bank_transfer', 'card_pos', 'other']);
            $table->boolean('requires_verification')->default(false);
            $table->boolean('requires_reference')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('location_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['location_id', 'payment_method_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_payment_methods');
        Schema::dropIfExists('payment_methods');
    }
};
