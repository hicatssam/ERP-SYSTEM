<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restaurant_areas')) {
            Schema::create('restaurant_areas', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('location_id');
                $table->string('name', 120);
                $table->string('code', 50)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('location_id', 'rest_area_location_fk')
                    ->references('id')->on('locations')->cascadeOnDelete();

                $table->unique(['location_id', 'name'], 'rest_area_location_name_uq');
                $table->index(['location_id', 'sort_order'], 'rest_area_location_sort_idx');
            });
        }

        if (! Schema::hasTable('restaurant_tables')) {
            Schema::create('restaurant_tables', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('location_id');
                $table->unsignedBigInteger('area_id')->nullable();
                $table->string('code', 50);
                $table->string('name', 120)->nullable();
                $table->unsignedSmallInteger('capacity')->default(4);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('location_id', 'rest_table_location_fk')
                    ->references('id')->on('locations')->cascadeOnDelete();

                $table->foreign('area_id', 'rest_table_area_fk')
                    ->references('id')->on('restaurant_areas')->nullOnDelete();

                $table->unique(['location_id', 'code'], 'rest_table_location_code_uq');
                $table->index(['location_id', 'area_id', 'sort_order'], 'rest_table_location_area_sort_idx');
            });
        }

        if (! Schema::hasTable('restaurant_table_sessions')) {
            Schema::create('restaurant_table_sessions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('restaurant_table_id');
                $table->unsignedBigInteger('location_id');
                $table->unsignedBigInteger('opened_by');
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->string('status', 20)->default('open')->index();
                $table->unsignedSmallInteger('guest_count')->default(1);
                $table->timestamp('opened_at');
                $table->timestamp('closed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('restaurant_table_id', 'rest_session_table_fk')
                    ->references('id')->on('restaurant_tables')->restrictOnDelete();

                $table->foreign('location_id', 'rest_session_location_fk')
                    ->references('id')->on('locations')->restrictOnDelete();

                $table->foreign('opened_by', 'rest_session_opened_by_fk')
                    ->references('id')->on('users')->restrictOnDelete();

                $table->foreign('closed_by', 'rest_session_closed_by_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->index(['restaurant_table_id', 'status'], 'rest_session_table_status_idx');
                $table->index(['location_id', 'status', 'opened_at'], 'rest_session_location_status_idx');
            });
        }

        if (Schema::hasTable('orders')) {
            if (! Schema::hasColumn('orders', 'restaurant_service_type')) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->string('restaurant_service_type', 30)
                        ->nullable()->after('sales_channel_id')->index();
                });
            }

            if (! Schema::hasColumn('orders', 'restaurant_table_id')) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->unsignedBigInteger('restaurant_table_id')
                        ->nullable()->after('restaurant_service_type');

                    $table->foreign('restaurant_table_id', 'orders_rest_table_fk')
                        ->references('id')->on('restaurant_tables')->nullOnDelete();
                });
            }

            if (! Schema::hasColumn('orders', 'restaurant_table_session_id')) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->unsignedBigInteger('restaurant_table_session_id')
                        ->nullable()->after('restaurant_table_id');

                    $table->foreign('restaurant_table_session_id', 'orders_rest_session_fk')
                        ->references('id')->on('restaurant_table_sessions')->nullOnDelete();
                });
            }

            if (! Schema::hasColumn('orders', 'waiter_id')) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->unsignedBigInteger('waiter_id')
                        ->nullable()->after('restaurant_table_session_id');

                    $table->foreign('waiter_id', 'orders_waiter_fk')
                        ->references('id')->on('users')->nullOnDelete();
                });
            }

            if (! Schema::hasColumn('orders', 'guest_count')) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->unsignedSmallInteger('guest_count')
                        ->nullable()->after('waiter_id');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            if (Schema::hasColumn('orders', 'restaurant_table_id')) {
                Schema::table('orders', fn (Blueprint $table) => $table->dropForeign('orders_rest_table_fk'));
            }

            if (Schema::hasColumn('orders', 'restaurant_table_session_id')) {
                Schema::table('orders', fn (Blueprint $table) => $table->dropForeign('orders_rest_session_fk'));
            }

            if (Schema::hasColumn('orders', 'waiter_id')) {
                Schema::table('orders', fn (Blueprint $table) => $table->dropForeign('orders_waiter_fk'));
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('orders', 'restaurant_service_type') ? 'restaurant_service_type' : null,
                Schema::hasColumn('orders', 'restaurant_table_id') ? 'restaurant_table_id' : null,
                Schema::hasColumn('orders', 'restaurant_table_session_id') ? 'restaurant_table_session_id' : null,
                Schema::hasColumn('orders', 'waiter_id') ? 'waiter_id' : null,
                Schema::hasColumn('orders', 'guest_count') ? 'guest_count' : null,
            ]));

            if ($columns) {
                Schema::table('orders', fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }

        Schema::dropIfExists('restaurant_table_sessions');
        Schema::dropIfExists('restaurant_tables');
        Schema::dropIfExists('restaurant_areas');
    }
};
