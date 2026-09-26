<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('special_cake_orders')) {
            Schema::table(
                'special_cake_orders',
                function (Blueprint $table): void {
                    if (! Schema::hasColumn('special_cake_orders', 'is_urgent')) {
                        $table
                            ->boolean('is_urgent')
                            ->default(false);
                    }

                    if (! Schema::hasColumn('special_cake_orders', 'urgent_reason')) {
                        $table
                            ->string('urgent_reason', 500)
                            ->nullable();
                    }
                }
            );
        }

        if (Schema::hasTable('showroom_cake_request_items')) {
            Schema::table(
                'showroom_cake_request_items',
                function (Blueprint $table): void {
                    if (! Schema::hasColumn('showroom_cake_request_items', 'reserved_quantity')) {
                        $table
                            ->unsignedInteger('reserved_quantity')
                            ->default(0);
                    }

                    if (! Schema::hasColumn('showroom_cake_request_items', 'reservation_notes')) {
                        $table
                            ->string('reservation_notes', 1000)
                            ->nullable();
                    }
                }
            );
        }

        if (Schema::hasTable('showroom_sweets_request_items')) {
            Schema::table(
                'showroom_sweets_request_items',
                function (Blueprint $table): void {
                    if (! Schema::hasColumn('showroom_sweets_request_items', 'reserved_quantity')) {
                        $table
                            ->decimal('reserved_quantity', 12, 3)
                            ->default(0);
                    }

                    if (! Schema::hasColumn('showroom_sweets_request_items', 'reservation_notes')) {
                        $table
                            ->string('reservation_notes', 1000)
                            ->nullable();
                    }
                }
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('special_cake_orders')) {
            Schema::table(
                'special_cake_orders',
                function (Blueprint $table): void {
                    $columns = array_values(
                        array_filter(
                            ['is_urgent', 'urgent_reason'],
                            fn (string $column): bool =>
                                Schema::hasColumn(
                                    'special_cake_orders',
                                    $column
                                )
                        )
                    );

                    if ($columns !== []) {
                        $table->dropColumn($columns);
                    }
                }
            );
        }

        if (Schema::hasTable('showroom_cake_request_items')) {
            Schema::table(
                'showroom_cake_request_items',
                function (Blueprint $table): void {
                    $columns = array_values(
                        array_filter(
                            [
                                'reserved_quantity',
                                'reservation_notes',
                            ],
                            fn (string $column): bool =>
                                Schema::hasColumn(
                                    'showroom_cake_request_items',
                                    $column
                                )
                        )
                    );

                    if ($columns !== []) {
                        $table->dropColumn($columns);
                    }
                }
            );
        }

        if (Schema::hasTable('showroom_sweets_request_items')) {
            Schema::table(
                'showroom_sweets_request_items',
                function (Blueprint $table): void {
                    $columns = array_values(
                        array_filter(
                            [
                                'reserved_quantity',
                                'reservation_notes',
                            ],
                            fn (string $column): bool =>
                                Schema::hasColumn(
                                    'showroom_sweets_request_items',
                                    $column
                                )
                        )
                    );

                    if ($columns !== []) {
                        $table->dropColumn($columns);
                    }
                }
            );
        }
    }
};
