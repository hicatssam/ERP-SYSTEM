<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_payment_accounts', function (Blueprint $table) {
            $table->id();

            /*
             |--------------------------------------------------------------------------
             | Relationships
             |--------------------------------------------------------------------------
             */

            $table->foreignId('location_id')
                ->constrained('locations')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
             | Optional direct link to the master payment method.
             |
             | Example:
             | cash
             | bank_transfer
             | mobile_wallet
             */

            $table->foreignId('payment_method_id')
                ->nullable()
                ->constrained('payment_methods')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            /*
             |--------------------------------------------------------------------------
             | Account Information
             |--------------------------------------------------------------------------
             */

            // الاسم الذي سيظهر للعميل
            $table->string('name');

            // مثال: بنك فلسطين / Jawwal Pay
            $table->string('provider_name')
                ->nullable();

            // اسم صاحب الحساب
            $table->string('account_holder_name')
                ->nullable();

            // رقم الحساب البنكي
            $table->string('account_number')
                ->nullable();

            // IBAN
            $table->string('iban')
                ->nullable();

            // رقم الجوال أو المحفظة
            $table->string('phone_number')
                ->nullable();

            /*
             |--------------------------------------------------------------------------
             | Extra Information
             |--------------------------------------------------------------------------
             */

            $table->text('instructions')
                ->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Indexes
             |--------------------------------------------------------------------------
             */

            $table->index([
                'location_id',
                'payment_method_id',
            ]);

            $table->index([
                'location_id',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_payment_accounts');
    }
};