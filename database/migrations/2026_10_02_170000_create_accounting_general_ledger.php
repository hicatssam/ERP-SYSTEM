<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 160);
            $table->string('type', 20); // asset, liability, equity, revenue, expense
            $table->string('subtype', 30)->nullable(); // cash or bank for treasury accounts
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->index(['type', 'is_active']);
        });

        Schema::create('accounting_journals', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 60)->unique();
            $table->uuid('request_key')->nullable()->unique();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('financial_period_id')->constrained('financial_periods')->restrictOnDelete();
            $table->string('currency_code', 3);
            $table->date('entry_date');
            $table->string('kind', 30); // manual, receipt, payment, reversal
            $table->string('description', 500);
            $table->decimal('total_debit', 14, 2);
            $table->decimal('total_credit', 14, 2);
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('reverses_journal_id')->nullable()->constrained('accounting_journals')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
            $table->unique('reverses_journal_id');
            $table->index(['location_id', 'entry_date']);
            $table->index(['financial_period_id', 'entry_date']);
        });

        Schema::create('accounting_journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accounting_journal_id')->constrained('accounting_journals')->restrictOnDelete();
            $table->foreignId('accounting_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('memo', 255)->nullable();
            $table->index('accounting_account_id');
        });

        Schema::create('accounting_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->string('number', 60)->unique();
            $table->string('type', 10); // receipt, payment
            $table->string('status', 20)->default('draft');
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->foreignId('treasury_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->foreignId('counter_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->date('voucher_date');
            $table->decimal('amount', 14, 2);
            $table->string('party_name', 160);
            $table->string('external_reference', 120);
            $table->string('description', 500);
            $table->string('payment_proof')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('accounting_journal_id')->nullable()->unique()->constrained('accounting_journals')->restrictOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 500)->nullable();
            $table->foreignId('reversal_journal_id')->nullable()->unique()->constrained('accounting_journals')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['location_id', 'type', 'external_reference'], 'accounting_voucher_external_unique');
            $table->index(['location_id', 'status', 'posted_at']);
        });

        Schema::table('daily_cash_reconciliations', function (Blueprint $table): void {
            $table->decimal('voucher_receipts', 14, 2)->default(0);
            $table->decimal('voucher_payments', 14, 2)->default(0);
        });

        foreach ([
            ['1000', 'الصندوق', 'asset', 'cash'],
            ['1010', 'البنك والحوالات', 'asset', 'bank'],
            ['1100', 'ذمم العملاء', 'asset', null],
            ['1150', 'ذمم مشتريات الموظفين', 'asset', null],
            ['1200', 'المخزون', 'asset', null],
            ['2000', 'ذمم الموردين', 'liability', null],
            ['2100', 'رواتب مستحقة', 'liability', null],
            ['3000', 'رأس المال', 'equity', null],
            ['4000', 'إيرادات المبيعات', 'revenue', null],
            ['4100', 'إيرادات أخرى', 'revenue', null],
            ['5000', 'تكلفة البضاعة المباعة', 'expense', null],
            ['6000', 'مصروفات تشغيلية', 'expense', null],
            ['6100', 'مصروفات الرواتب', 'expense', null],
        ] as [$code, $name, $type, $subtype]) {
            DB::table('accounting_accounts')->insert([
                'code' => $code, 'name' => $name, 'type' => $type, 'subtype' => $subtype,
                'is_active' => true, 'is_system' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $all = [
            'accounting.books.view', 'accounting.accounts.manage',
            'accounting.vouchers.create', 'accounting.vouchers.post', 'accounting.vouchers.reverse',
            'accounting.journals.post', 'accounting.reports.view',
        ];
        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Role::query()->where('guard_name', 'web')
            ->whereIn('name', ['Admin', 'super-admin', 'General Manager', 'Accountant'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($all));
        Role::query()->where('guard_name', 'web')->where('name', 'Branch Manager')->get()
            ->each(fn (Role $role) => $role->givePermissionTo([
                'accounting.books.view', 'accounting.vouchers.create',
            ]));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('daily_cash_reconciliations', fn (Blueprint $table) => $table->dropColumn(['voucher_receipts', 'voucher_payments']));
        Schema::dropIfExists('accounting_vouchers');
        Schema::dropIfExists('accounting_journal_lines');
        Schema::dropIfExists('accounting_journals');
        Schema::dropIfExists('accounting_accounts');
    }
};
