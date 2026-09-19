<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('location_payment_accounts')) {
            return;
        }

        Schema::table('location_payment_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('location_payment_accounts', 'location_payment_method_id')) {
                $table->unsignedBigInteger('location_payment_method_id')
                    ->nullable()
                    ->after('id');
            }

            if (! Schema::hasColumn('location_payment_accounts', 'wallet_number')) {
                $table->string('wallet_number', 100)
                    ->nullable()
                    ->after('phone_number');
            }
        });

        if (! Schema::hasTable('location_payment_methods')) {
            return;
        }

        DB::table('location_payment_accounts')
            ->whereNull('location_payment_method_id')
            ->whereNotNull('location_id')
            ->whereNotNull('payment_method_id')
            ->orderBy('id')
            ->chunkById(100, function ($accounts): void {
                foreach ($accounts as $account) {
                    $locationPaymentMethod = DB::table('location_payment_methods')
                        ->where('location_id', $account->location_id)
                        ->where('payment_method_id', $account->payment_method_id)
                        ->first();

                    $locationPaymentMethodId = $locationPaymentMethod?->id;

                    if (! $locationPaymentMethodId) {
                        $locationPaymentMethodId = DB::table('location_payment_methods')->insertGetId([
                            'location_id' => $account->location_id,
                            'payment_method_id' => $account->payment_method_id,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('location_payment_accounts')
                        ->where('id', $account->id)
                        ->update([
                            'location_payment_method_id' => $locationPaymentMethodId,
                            'updated_at' => now(),
                        ]);
                }
            });

        $legacyColumns = [
            'mobile_number',
            'account_holder_name',
            'bank_name',
            'bank_account_number',
            'iban',
            'wallet_number',
            'payment_instructions',
        ];

        $hasLegacyDetails = collect($legacyColumns)
            ->every(fn (string $column): bool => Schema::hasColumn('location_payment_methods', $column));

        if ($hasLegacyDetails) {
            DB::table('location_payment_methods')
                ->orderBy('id')
                ->chunkById(100, function ($rows): void {
                    foreach ($rows as $row) {
                        $hasDetails = collect([
                            $row->mobile_number,
                            $row->account_holder_name,
                            $row->bank_name,
                            $row->bank_account_number,
                            $row->iban,
                            $row->wallet_number,
                            $row->payment_instructions,
                        ])->contains(fn ($value): bool => filled($value));

                        if (! $hasDetails) {
                            continue;
                        }

                        if (DB::table('location_payment_accounts')
                            ->where('location_payment_method_id', $row->id)
                            ->exists()) {
                            continue;
                        }

                        DB::table('location_payment_accounts')->insert([
                            'location_payment_method_id' => $row->id,
                            'location_id' => $row->location_id,
                            'payment_method_id' => $row->payment_method_id,
                            'name' => $row->bank_name ?: 'حساب الدفع',
                            'provider_name' => $row->bank_name,
                            'account_holder_name' => $row->account_holder_name,
                            'account_number' => $row->bank_account_number,
                            'iban' => $row->iban,
                            'phone_number' => $row->mobile_number,
                            'wallet_number' => $row->wallet_number,
                            'instructions' => $row->payment_instructions,
                            'is_active' => (bool) $row->is_active,
                            'sort_order' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });
        }

        // The canonical parent id is indexed after backfill. We intentionally
        // keep the legacy location_id/payment_method_id columns during this
        // compatibility sprint so older screens can keep working safely.
        try {
            Schema::table('location_payment_accounts', function (Blueprint $table): void {
                $table->index(
                    ['location_payment_method_id', 'is_active', 'sort_order'],
                    'lpa_method_active_sort_idx'
                );
            });
        } catch (Throwable) {
            // Existing installations may already have the index.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('location_payment_accounts')) {
            return;
        }

        try {
            Schema::table('location_payment_accounts', function (Blueprint $table): void {
                $table->dropIndex('lpa_method_active_sort_idx');
            });
        } catch (Throwable) {
            // Index may be absent in a partially migrated database.
        }

        Schema::table('location_payment_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('location_payment_accounts', 'location_payment_method_id')) {
                $table->dropColumn('location_payment_method_id');
            }

            if (Schema::hasColumn('location_payment_accounts', 'wallet_number')) {
                $table->dropColumn('wallet_number');
            }
        });
    }
};
