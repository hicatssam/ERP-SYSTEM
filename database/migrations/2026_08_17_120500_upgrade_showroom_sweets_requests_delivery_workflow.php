<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | تحديث Enum الحالة
        |--------------------------------------------------------------------------
        |
        | الجدول القديم كان يحتوي فقط:
        |
        | submitted
        | in_progress
        | fulfilled
        | rejected
        | cancelled
        |
        | الآن نضيف مراحل التوصيل والاستلام.
        |
        */

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE showroom_sweets_requests
                MODIFY COLUMN status ENUM(
                    'submitted',
                    'in_progress',
                    'ready_for_dispatch',
                    'out_for_delivery',
                    'received_at_branch',
                    'fulfilled',
                    'rejected',
                    'cancelled'
                )
                NOT NULL
                DEFAULT 'submitted'
            ");
        }


        /*
        |--------------------------------------------------------------------------
        | موظف التوصيل
        |--------------------------------------------------------------------------
        */

        if (
            ! Schema::hasColumn(
                'showroom_sweets_requests',
                'dispatched_by'
            )
        ) {
            Schema::table(
                'showroom_sweets_requests',
                function (Blueprint $table) {

                    $table
                        ->foreignId('dispatched_by')
                        ->nullable()
                        ->after('handled_by')
                        ->constrained('users')
                        ->nullOnDelete();
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | مستلم الفرع
        |--------------------------------------------------------------------------
        */

        if (
            ! Schema::hasColumn(
                'showroom_sweets_requests',
                'received_by'
            )
        ) {
            Schema::table(
                'showroom_sweets_requests',
                function (Blueprint $table) {

                    $table
                        ->foreignId('received_by')
                        ->nullable()
                        ->after('dispatched_by')
                        ->constrained('users')
                        ->nullOnDelete();
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | وقت الإرسال
        |--------------------------------------------------------------------------
        */

        if (
            ! Schema::hasColumn(
                'showroom_sweets_requests',
                'dispatched_at'
            )
        ) {
            Schema::table(
                'showroom_sweets_requests',
                function (Blueprint $table) {

                    $table
                        ->timestamp('dispatched_at')
                        ->nullable()
                        ->after('submitted_at');
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | وقت الاستلام في الفرع
        |--------------------------------------------------------------------------
        */

        if (
            ! Schema::hasColumn(
                'showroom_sweets_requests',
                'received_at'
            )
        ) {
            Schema::table(
                'showroom_sweets_requests',
                function (Blueprint $table) {

                    $table
                        ->timestamp('received_at')
                        ->nullable()
                        ->after('dispatched_at');
                }
            );
        }
    }


    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | إعادة الحالات الجديدة إلى الحالات القديمة
        |--------------------------------------------------------------------------
        */

        DB::table('showroom_sweets_requests')
            ->where(
                'status',
                'ready_for_dispatch'
            )
            ->update([
                'status' => 'in_progress',
            ]);

        DB::table('showroom_sweets_requests')
            ->where(
                'status',
                'out_for_delivery'
            )
            ->update([
                'status' => 'in_progress',
            ]);

        DB::table('showroom_sweets_requests')
            ->where(
                'status',
                'received_at_branch'
            )
            ->update([
                'status' => 'fulfilled',
            ]);


        /*
        |--------------------------------------------------------------------------
        | حذف حقول التوصيل والاستلام
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasColumn(
                'showroom_sweets_requests',
                'received_by'
            )
        ) {
            Schema::table(
                'showroom_sweets_requests',
                function (Blueprint $table) {

                    $table->dropConstrainedForeignId(
                        'received_by'
                    );
                }
            );
        }


        if (
            Schema::hasColumn(
                'showroom_sweets_requests',
                'dispatched_by'
            )
        ) {
            Schema::table(
                'showroom_sweets_requests',
                function (Blueprint $table) {

                    $table->dropConstrainedForeignId(
                        'dispatched_by'
                    );
                }
            );
        }


        if (
            Schema::hasColumn(
                'showroom_sweets_requests',
                'received_at'
            )
        ) {
            Schema::table(
                'showroom_sweets_requests',
                function (Blueprint $table) {

                    $table->dropColumn(
                        'received_at'
                    );
                }
            );
        }


        if (
            Schema::hasColumn(
                'showroom_sweets_requests',
                'dispatched_at'
            )
        ) {
            Schema::table(
                'showroom_sweets_requests',
                function (Blueprint $table) {

                    $table->dropColumn(
                        'dispatched_at'
                    );
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | إعادة Enum القديم
        |--------------------------------------------------------------------------
        */

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE showroom_sweets_requests
                MODIFY COLUMN status ENUM(
                    'submitted',
                    'in_progress',
                    'fulfilled',
                    'rejected',
                    'cancelled'
                )
                NOT NULL
                DEFAULT 'submitted'
            ");
        }
    }
};
