<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
      Schema::table('location_payment_methods', function (Blueprint $table) {
    $table->string('mobile_number', 50)
        ->nullable()
        ->after('payment_method_id');

    $table->string('account_holder_name')
        ->nullable()
        ->after('mobile_number');

    $table->string('bank_name')
        ->nullable()
        ->after('account_holder_name');

    $table->string('bank_account_number', 100)
        ->nullable()
        ->after('bank_name');

    $table->string('iban', 100)
        ->nullable()
        ->after('bank_account_number');

    $table->string('wallet_number', 100)
        ->nullable()
        ->after('iban');

    $table->text('payment_instructions')
        ->nullable()
        ->after('wallet_number');

    $table->json('details')
        ->nullable()
        ->after('payment_instructions');
});
    }

    public function down(): void
    {
        Schema::table('location_payment_methods', function (Blueprint $table) {

            $table->dropColumn([
                'mobile_number',
                'account_holder_name',
                'bank_name',
                'bank_account_number',
                'iban',
                'wallet_number',
                'payment_instructions',
                'details',
            ]);

        });
    }
};