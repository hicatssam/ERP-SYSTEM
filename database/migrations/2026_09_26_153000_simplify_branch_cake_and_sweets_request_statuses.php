<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->prepareStatusColumn('showroom_cake_requests');
        $this->prepareStatusColumn('showroom_sweets_requests');

        if (Schema::hasTable('showroom_cake_requests')) {
            $this->mapStatuses('showroom_cake_requests', [
                'pending' => [
                    'draft',
                    'submitted',
                    'pending',
                    'pending_factory_review',
                    'modification_requested',
                ],
                'in_progress' => [
                    'accepted',
                    'scheduled',
                    'in_progress',
                    'in_preparation',
                    'decorating',
                    'quality_check',
                ],
                'ready' => [
                    'ready',
                    'ready_for_dispatch',
                    'out_for_delivery',
                    'sent_to_branch',
                    'received_by_branch',
                    'received_at_branch',
                    'ready_for_customer',
                    'ready_for_pickup',
                ],
                'completed' => [
                    'completed',
                    'fulfilled',
                    'delivered',
                ],
                'cancelled' => [
                    'cancelled',
                    'canceled',
                    'rejected',
                ],
            ]);
        }

        if (Schema::hasTable('showroom_sweets_requests')) {
            $this->mapStatuses('showroom_sweets_requests', [
                'pending' => [
                    'submitted',
                    'pending',
                ],
                'in_progress' => [
                    'in_progress',
                ],
                'ready' => [
                    'ready',
                    'ready_for_dispatch',
                    'out_for_delivery',
                ],
                'completed' => [
                    'completed',
                    'received_at_branch',
                    'fulfilled',
                ],
                'cancelled' => [
                    'cancelled',
                    'canceled',
                    'rejected',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('showroom_cake_requests')) {
            $this->mapStatuses('showroom_cake_requests', [
                'submitted' => ['pending'],
                'in_progress' => ['in_progress'],
                'fulfilled' => ['ready', 'completed'],
                'cancelled' => ['cancelled'],
            ]);
        }

        if (Schema::hasTable('showroom_sweets_requests')) {
            $this->mapStatuses('showroom_sweets_requests', [
                'submitted' => ['pending'],
                'in_progress' => ['in_progress'],
                'ready_for_dispatch' => ['ready'],
                'received_at_branch' => ['completed'],
                'cancelled' => ['cancelled'],
            ]);
        }
    }

    private function mapStatuses(
        string $table,
        array $mappings
    ): void {
        foreach ($mappings as $target => $sources) {
            DB::table($table)
                ->whereIn('status', $sources)
                ->update(['status' => $target]);
        }
    }

    private function prepareStatusColumn(string $tableName): void
    {
        if (
            ! Schema::hasTable($tableName)
            || ! Schema::hasColumn($tableName, 'status')
        ) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            Schema::table(
                $tableName,
                function (Blueprint $table): void {
                    $table
                        ->string('status', 50)
                        ->change();
                }
            );

            return;
        }

        if ($driver !== 'mysql') {
            return;
        }

        $column = DB::selectOne(
            "SHOW COLUMNS FROM {$tableName} LIKE 'status'"
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
            "ALTER TABLE {$tableName} "
            . "MODIFY status VARCHAR(50) NOT NULL"
        );
    }
};
