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

        Schema::table('location_payment_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('location_payment_accounts', 'location_payment_method_id')) {
                $table->unsignedBigInteger('location_payment_method_id')
                    ->nullable()
                    ->after('id');

                $table->index(
                    ['location_payment_method_id', 'is_active', 'sort_order'],
                    'lpa_method_active_sort_idx'
                );
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

        // Backfill the canonical parent relation from the legacy location/method pair.
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

                    if (! $locationPaymentMethod) {
                        $id = DB::table('location_payment_methods')->insertGetId([
                            'location_id' => $account->location_id,
                            'payment_method_id' => $account->payment_method_id,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $id = $locationPaymentMethod->id;
                    }

                    DB::table('location_payment_accounts')
                        ->where('id', $account->id)
                        ->update([
                            'location_payment_method_id' => $id,
                            'updated_at' => now(),
                        ]);
                }
            });

        // Preserve any legacy branch-level payment details by moving them into
        // a real account row when there is meaningful data and no account yet.
        $legacyColumns = [
            'mobile_number',
            'account_holder_name',
            'bank_name',
            'bank_account_number',
            'iban',
            'wallet_number',
            'payment_instructions',
        ];

        $hasAllLegacyColumns = collect($legacyColumns)
            ->every(fn (string $column): bool => Schema::hasColumn('location_payment_methods', $column));

        if ($hasAllLegacyColumns) {
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

                        $alreadyHasAccount = DB::table('location_payment_accounts')
                            ->where('location_payment_method_id', $row->id)
                            ->exists();

                        if ($alreadyHasAccount) {
                            continue;
                        }

                        DB::table('location_payment_accounts')->insert([
                            'location_payment_method_id' => $row->id,
                            // Legacy compatibility: keep these populated until
                            // the old columns are removed in a later migration.
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

        // Add the FK only after data is normalized. Laravel rebuilds the table
        // where required by the database driver, keeping this migration usable
        // in both MySQL and the project's SQLite test environment.
        Schema::table('location_payment_accounts', function (Blueprint $table) {
            try {
                $table->foreign('location_payment_method_id', 'lpa_location_payment_method_fk')
                    ->references('id')
                    ->on('location_payment_methods')
                    ->cascadeOnDelete();
            } catch (Throwable) {
                // Some existing/test databases may not support adding an FK to
                // an already-created table. Application validation still keeps
                // the relationship safe; the index remains in place.
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('location_payment_accounts')) {
            return;
        }

        Schema::table('location_payment_accounts', function (Blueprint $table) {
            try {
                $table->dropForeign('lpa_location_payment_method_fk');
            } catch (Throwable) {
                // FK may not exist on drivers that could not add it.
            }

            if (Schema::hasColumn('location_payment_accounts', 'location_payment_method_id')) {
                try {
                    $table->dropIndex('lpa_method_active_sort_idx');
                } catch (Throwable) {
                    // Index may not exist in a partially migrated database.
                }

                $table->dropColumn('location_payment_method_id');
            }

            if (Schema::hasColumn('location_payment_accounts', 'wallet_number')) {
                $table->dropColumn('wallet_number');
            }
        });
    }
};