<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->restrictOnDelete();
        });

        Schema::table('payments', fn (Blueprint $table) => $table->index(['location_id', 'paid_at', 'status'], 'payment_cash_day_idx'));
        Schema::table('customer_payments', fn (Blueprint $table) => $table->index(['location_id', 'paid_at', 'status'], 'customer_cash_day_idx'));
        Schema::table('supplier_payments', fn (Blueprint $table) => $table->index(['location_id', 'payment_date', 'status'], 'supplier_cash_day_idx'));
        Schema::table('refunds', fn (Blueprint $table) => $table->index('processed_at', 'refund_cash_day_idx'));

        Schema::create('branch_cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->string('type', 30); // other_income, transfer_in, transfer_out
            $table->decimal('amount', 14, 2);
            $table->timestamp('occurred_at');
            $table->string('reference', 120)->nullable();
            $table->text('description');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['location_id', 'occurred_at', 'type'], 'branch_cash_movement_scope');
        });

        Schema::create('daily_cash_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('business_date');
            $table->decimal('opening_balance', 14, 2);
            foreach (['sales', 'customer_receipts', 'other_income', 'expenses', 'supplier_payments', 'refunds', 'transfer_in', 'transfer_out'] as $field) {
                $table->decimal($field, 14, 2)->default(0);
            }
            $table->decimal('expected_closing', 14, 2);
            $table->decimal('actual_closing', 14, 2);
            $table->decimal('variance', 14, 2);
            $table->text('note')->nullable();
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at');
            $table->timestamps();
            $table->unique(['location_id', 'business_date'], 'daily_cash_location_date_unique');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['financial.cash.view', 'financial.cash.movements.manage', 'financial.cash.close'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Role::query()->where('guard_name', 'web')
            ->whereIn('name', ['Admin', 'super-admin', 'General Manager', 'Accountant'])
            ->get()->each(fn (Role $role) => $role->givePermissionTo([
                'financial.cash.view', 'financial.cash.movements.manage', 'financial.cash.close',
            ]));
        Role::query()->where('guard_name', 'web')->where('name', 'Branch Manager')
            ->get()->each(fn (Role $role) => $role->givePermissionTo('financial.cash.view'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_cash_reconciliations');
        Schema::dropIfExists('branch_cash_movements');
        Schema::table('refunds', fn (Blueprint $table) => $table->dropIndex('refund_cash_day_idx'));
        Schema::table('supplier_payments', fn (Blueprint $table) => $table->dropIndex('supplier_cash_day_idx'));
        Schema::table('customer_payments', fn (Blueprint $table) => $table->dropIndex('customer_cash_day_idx'));
        Schema::table('payments', fn (Blueprint $table) => $table->dropIndex('payment_cash_day_idx'));
        Schema::table('expenses', fn (Blueprint $table) => $table->dropConstrainedForeignId('payment_method_id'));
        // Permission assignments are deliberately preserved for a future re-deploy.
    }
};
