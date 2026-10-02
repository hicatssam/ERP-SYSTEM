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
        Schema::create('employee_purchases', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 80)->unique();
            $table->uuid('request_key')->unique();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('payment_plan', 20); // account / installments
            $table->string('status', 20)->default('open');
            $table->decimal('total_amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('outstanding_amount', 14, 2);
            $table->timestamp('purchased_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->index(['location_id', 'status', 'purchased_at']);
            $table->index(['employee_id', 'purchased_at']);
        });

        Schema::create('employee_purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_purchase_id')->constrained('employee_purchases')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
            $table->unique(['employee_purchase_id', 'product_id'], 'employee_purchase_unique_product');
        });

        Schema::create('employee_purchase_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_purchase_id')->constrained('employee_purchases')->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['employee_purchase_id', 'sequence'], 'employee_installment_sequence_unique');
            $table->index(['due_date', 'status']);
        });

        Schema::create('employee_purchase_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('employee_purchase_id')->constrained('employee_purchases')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('status', 30)->default('posted');
            $table->timestamp('received_at');
            $table->timestamp('posted_at')->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('payment_proof')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['location_id', 'posted_at', 'status']);
            $table->index(['employee_purchase_id', 'status']);
        });

        Schema::table('daily_cash_reconciliations', function (Blueprint $table): void {
            $table->decimal('employee_receipts', 14, 2)->default(0);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = [
            'accounting.employee_accounts.view',
            'accounting.employee_accounts.create',
            'accounting.employee_accounts.receive',
            'accounting.employee_accounts.verify',
        ];
        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Role::query()->whereIn('name', ['Admin', 'super-admin', 'General Manager', 'Accountant'])
            ->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));
        Role::query()->where('name', 'Branch Manager')->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo(array_slice($permissions, 0, 3)));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('daily_cash_reconciliations', fn (Blueprint $table) => $table->dropColumn('employee_receipts'));
        Schema::dropIfExists('employee_purchase_receipts');
        Schema::dropIfExists('employee_purchase_installments');
        Schema::dropIfExists('employee_purchase_items');
        Schema::dropIfExists('employee_purchases');
    }
};
