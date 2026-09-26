<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('special_cake_orders')
            || ! Schema::hasColumn(
                'special_cake_orders',
                'status'
            )
        ) {
            return;
        }

        $this->ensureStatusColumnAcceptsSimplifiedValues();

        $mappings = [
            'pending' => [
                'draft',
                'pending_deposit',
                'deposit_paid',
                'pending_factory_review',
                'modification_requested',
            ],

            'in_progress' => [
                'accepted',
                'scheduled',
                'in_preparation',
                'decorating',
                'in_decoration',
                'quality_check',
                'delayed',
                'issue_open',
            ],

            'ready' => [
                'ready',
                'sent_to_branch',
                'dispatched_to_branch',
                'received_by_branch',
                'received_at_branch',
                'ready_for_customer',
                'ready_for_pickup',
            ],

            'completed' => [
                'completed',
                'delivered',
            ],

            'cancelled' => [
                'cancelled',
                'canceled',
                'rejected',
            ],
        ];

        foreach (
            $mappings
            as $target => $sourceStatuses
        ) {
            DB::table('special_cake_orders')
                ->whereIn(
                    'status',
                    $sourceStatuses
                )
                ->update([
                    'status' => $target,
                ]);
        }

        DB::table('special_cake_orders')
            ->whereNull('status')
            ->update([
                'status' => 'pending',
            ]);
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('special_cake_orders')
            || ! Schema::hasColumn(
                'special_cake_orders',
                'status'
            )
        ) {
            return;
        }

        /*
         * Roll back only to representative legacy workflow states.
         * Historical status rows are intentionally never rewritten.
         */
        $mappings = [
            'pending' =>
                'pending_factory_review',
            'in_progress' =>
                'in_preparation',
            'ready' =>
                'ready_for_customer',
        ];

        foreach (
            $mappings
            as $source => $target
        ) {
            DB::table('special_cake_orders')
                ->where(
                    'status',
                    $source
                )
                ->update([
                    'status' => $target,
                ]);
        }
    }

    private function ensureStatusColumnAcceptsSimplifiedValues(): void
    {
        /*
         * Most installations already use VARCHAR. This guard only upgrades
         * older MySQL installations that may still have an ENUM column.
         * SQLite tests skip this branch.
         */
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $column = DB::selectOne(
            "SHOW COLUMNS FROM special_cake_orders LIKE 'status'"
        );

        $type = strtolower(
            (string) (
                $column->Type
                ?? $column->type
                ?? ''
            )
        );

        if (! str_starts_with($type, 'enum(')) {
            return;
        }

        DB::statement(
            "ALTER TABLE special_cake_orders "
            . "MODIFY status VARCHAR(50) "
            . "NOT NULL DEFAULT 'draft'"
        );
    }
};
