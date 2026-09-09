<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->extendOrderItemCostSnapshots();
        $this->buildExpenseCategories();
        $this->buildExpenses();
        $this->extendFinancialLedger();
    }

    private function extendOrderItemCostSnapshots(): void
    {
        if (! Schema::hasColumn('order_items', 'unit_cost_snapshot')) {
            Schema::table('order_items', fn (Blueprint $table) =>
                $table->decimal('unit_cost_snapshot', 16, 4)->nullable()->after('line_total')
            );
        }

        if (! Schema::hasColumn('order_items', 'cost_total_snapshot')) {
            Schema::table('order_items', fn (Blueprint $table) =>
                $table->decimal('cost_total_snapshot', 16, 2)->nullable()->after('unit_cost_snapshot')
            );
        }

        if (! Schema::hasColumn('order_items', 'net_revenue_snapshot')) {
            Schema::table('order_items', fn (Blueprint $table) =>
                $table->decimal('net_revenue_snapshot', 16, 2)->nullable()->after('cost_total_snapshot')
            );
        }

        if (! Schema::hasColumn('order_items', 'gross_profit_snapshot')) {
            Schema::table('order_items', fn (Blueprint $table) =>
                $table->decimal('gross_profit_snapshot', 16, 2)->nullable()->after('net_revenue_snapshot')
            );
        }

        if (! Schema::hasColumn('order_items', 'cost_source')) {
            Schema::table('order_items', fn (Blueprint $table) =>
                $table->string('cost_source', 40)->nullable()->after('gross_profit_snapshot')
            );
        }

        if (! Schema::hasColumn('order_items', 'cost_snapshotted_at')) {
            Schema::table('order_items', fn (Blueprint $table) =>
                $table->timestamp('cost_snapshotted_at')->nullable()->after('cost_source')
            );
        }
    }

    private function buildExpenseCategories(): void
    {
        if (! Schema::hasTable('expense_categories')) {
            Schema::create('expense_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 70)->unique();
                $table->string('name', 140);
                $table->string('classification', 40)->default('operating');
                $table->boolean('is_active')->default(true);
                $table->boolean('is_system')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });

            return;
        }

        if (! Schema::hasColumn('expense_categories', 'classification')) {
            Schema::table('expense_categories', fn (Blueprint $table) =>
                $table->string('classification', 40)->default('operating')->after('name')
            );
        }
    }

    private function buildExpenses(): void
    {
        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table): void {
                $table->id();
                $table->string('expense_number', 70)->nullable()->unique();
                $table->unsignedBigInteger('expense_category_id');
                $table->unsignedBigInteger('location_id');
                $table->unsignedBigInteger('financial_period_id')->nullable();
                $table->decimal('amount', 16, 2);
                $table->date('expense_date');
                $table->string('payee', 180)->nullable();
                $table->string('reference_number', 120)->nullable();
                $table->text('description');
                $table->string('status', 30)->default('draft');

                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('rejected_by')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->unsignedBigInteger('posted_by')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->unsignedBigInteger('voided_by')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->text('void_reason')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->foreign('expense_category_id', 'expense_category_fk')->references('id')->on('expense_categories')->restrictOnDelete();
                $table->foreign('location_id', 'expense_location_fk')->references('id')->on('locations')->restrictOnDelete();
                $table->foreign('financial_period_id', 'expense_period_fk')->references('id')->on('financial_periods')->nullOnDelete();
                $table->foreign('created_by', 'expense_created_by_fk')->references('id')->on('users')->restrictOnDelete();
                $table->foreign('submitted_by', 'expense_submitted_by_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('approved_by', 'expense_approved_by_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('rejected_by', 'expense_rejected_by_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('posted_by', 'expense_posted_by_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('voided_by', 'expense_voided_by_fk')->references('id')->on('users')->nullOnDelete();

                $table->index(['location_id', 'expense_date', 'status'], 'expense_scope_idx');
                $table->index(['financial_period_id', 'status'], 'expense_period_status_idx');
            });

            return;
        }

        // Safe upgrade path if an earlier basic Sprint 07 draft was installed.
        // The professional workflow assigns the readable number immediately after
        // the insert using the database ID, so the column must allow the initial NULL.
        Schema::table('expenses', fn (Blueprint $table) =>
            $table->string('expense_number', 70)->nullable()->change()
        );

        $columns = [
            'financial_period_id', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at',
            'rejected_by', 'rejected_at', 'rejection_reason', 'posted_by', 'posted_at', 'deleted_at',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('expenses', $column)) {
                continue;
            }

            Schema::table('expenses', function (Blueprint $table) use ($column): void {
                match ($column) {
                    'financial_period_id' => $table->unsignedBigInteger($column)->nullable(),
                    'submitted_by', 'approved_by', 'rejected_by', 'posted_by' => $table->unsignedBigInteger($column)->nullable(),
                    'submitted_at', 'approved_at', 'rejected_at', 'posted_at' => $table->timestamp($column)->nullable(),
                    'rejection_reason' => $table->text($column)->nullable(),
                    'deleted_at' => $table->softDeletes(),
                };
            });
        }

        if (Schema::hasColumn('expenses', 'expense_number')) {
            DB::table('expenses')->whereNull('expense_number')->orderBy('id')->get(['id', 'created_at'])->each(function ($row): void {
                $year = $row->created_at ? substr((string) $row->created_at, 0, 4) : now()->format('Y');
                DB::table('expenses')->where('id', $row->id)->update([
                    'expense_number' => sprintf('EXP-%s-%06d', $year, $row->id),
                ]);
            });
        }
    }

    private function extendFinancialLedger(): void
    {
        if (! Schema::hasTable('sales_ledger_entries')) {
            return;
        }

        // The original schema used ENUM while the architecture is already
        // evolving beyond sales-only entry types. VARCHAR prevents future
        // migrations for every new accounting event while preserving all values.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `sales_ledger_entries` MODIFY `entry_type` VARCHAR(50) NOT NULL");
        } else {
            Schema::table('sales_ledger_entries', fn (Blueprint $table) =>
                $table->string('entry_type', 50)->change()
            );
        }

        if (! Schema::hasColumn('sales_ledger_entries', 'financial_period_id')) {
            Schema::table('sales_ledger_entries', function (Blueprint $table): void {
                $table->unsignedBigInteger('financial_period_id')->nullable()->after('location_id');
                $table->foreign('financial_period_id', 'sle_period_fk')
                    ->references('id')->on('financial_periods')->nullOnDelete();
                $table->index(['financial_period_id', 'entry_date'], 'sle_period_date_idx');
            });
        }

        if (! Schema::hasColumn('sales_ledger_entries', 'idempotency_key')) {
            Schema::table('sales_ledger_entries', function (Blueprint $table): void {
                $table->string('idempotency_key', 150)->nullable()->after('reference_id');
                $table->unique('idempotency_key', 'sle_idempotency_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');

        // Intentionally do not narrow sales_ledger_entries.entry_type back to ENUM.
        // Doing so could destroy newer ledger values during rollback.

        if (Schema::hasTable('sales_ledger_entries')) {
            if (Schema::hasColumn('sales_ledger_entries', 'idempotency_key')) {
                Schema::table('sales_ledger_entries', function (Blueprint $table): void {
                    $table->dropUnique('sle_idempotency_unique');
                    $table->dropColumn('idempotency_key');
                });
            }

            if (Schema::hasColumn('sales_ledger_entries', 'financial_period_id')) {
                Schema::table('sales_ledger_entries', function (Blueprint $table): void {
                    $table->dropForeign('sle_period_fk');
                    $table->dropIndex('sle_period_date_idx');
                    $table->dropColumn('financial_period_id');
                });
            }
        }

        foreach ([
            'cost_snapshotted_at', 'cost_source', 'gross_profit_snapshot',
            'net_revenue_snapshot', 'cost_total_snapshot', 'unit_cost_snapshot',
        ] as $column) {
            if (Schema::hasColumn('order_items', $column)) {
                Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
