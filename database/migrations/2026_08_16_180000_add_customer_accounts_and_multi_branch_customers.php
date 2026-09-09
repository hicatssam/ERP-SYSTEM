<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'customer_type')) {
                $table->string('customer_type', 30)->default('individual')->after('location_id');
            }

            if (! Schema::hasColumn('customers', 'scope')) {
                $table->string('scope', 20)->default('branch')->after('customer_type');
            }

            if (! Schema::hasColumn('customers', 'allow_credit')) {
                $table->boolean('allow_credit')->default(false)->after('secondary_phone');
            }

            if (! Schema::hasColumn('customers', 'credit_limit')) {
                $table->decimal('credit_limit', 14, 2)->nullable()->after('allow_credit');
            }

            if (! Schema::hasColumn('customers', 'billing_cycle')) {
                $table->string('billing_cycle', 20)->default('immediate')->after('credit_limit');
            }

            if (! Schema::hasColumn('customers', 'payment_terms_days')) {
                $table->unsignedSmallInteger('payment_terms_days')->default(0)->after('billing_cycle');
            }

            if (! Schema::hasColumn('customers', 'tax_number')) {
                $table->string('tax_number', 100)->nullable()->after('payment_terms_days');
            }

            if (! Schema::hasColumn('customers', 'contact_person')) {
                $table->string('contact_person', 150)->nullable()->after('tax_number');
            }

            if (! Schema::hasColumn('customers', 'address')) {
                $table->string('address', 500)->nullable()->after('contact_person');
            }
        });

        if (! Schema::hasTable('customer_locations')) {
            Schema::create('customer_locations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['customer_id', 'location_id']);
                $table->index(['location_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('customer_payments')) {
            Schema::create('customer_payments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
                $table->decimal('amount', 14, 2);
                $table->string('status', 30)->default('confirmed');
                $table->string('reference_number', 100)->nullable();
                $table->string('payment_proof')->nullable();
                $table->string('allocation_mode', 20)->default('automatic');
                $table->json('allocation_payload')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('paid_at');
                $table->timestamps();

                $table->index(['customer_id', 'status']);
                $table->index(['customer_id', 'paid_at']);
                $table->index('location_id');
            });
        }

        if (! Schema::hasTable('customer_payment_allocations')) {
            Schema::create('customer_payment_allocations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_payment_id')->constrained('customer_payments')->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
                $table->decimal('amount', 14, 2);
                $table->timestamps();

                $table->unique(['customer_payment_id', 'invoice_id'], 'customer_payment_invoice_unique');
                $table->index('invoice_id');
            });
        }

        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'due_at')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->timestamp('due_at')->nullable()->after('issued_at');
                $table->index('due_at');
            });
        }

        $this->addOnAccountPaymentArrangement();
    }

    public function down(): void
    {
        $this->removeOnAccountPaymentArrangement();

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'due_at')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropColumn('due_at');
            });
        }

        Schema::dropIfExists('customer_payment_allocations');
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('customer_locations');

        Schema::table('customers', function (Blueprint $table): void {
            $columns = [
                'customer_type',
                'scope',
                'allow_credit',
                'credit_limit',
                'billing_cycle',
                'payment_terms_days',
                'tax_number',
                'contact_person',
                'address',
            ];

            $existing = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn('customers', $column)
            ));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }

    private function addOnAccountPaymentArrangement(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'payment_arrangement')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE orders MODIFY payment_arrangement ENUM('pay_now','deposit','partial_payment','pay_on_pickup','pending_verification','on_account') NOT NULL DEFAULT 'pay_now'"
            );
        }
    }

    private function removeOnAccountPaymentArrangement(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'payment_arrangement')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::table('orders')
                ->where('payment_arrangement', 'on_account')
                ->update(['payment_arrangement' => 'pay_on_pickup']);

            DB::statement(
                "ALTER TABLE orders MODIFY payment_arrangement ENUM('pay_now','deposit','partial_payment','pay_on_pickup','pending_verification') NOT NULL DEFAULT 'pay_now'"
            );
        }
    }
};
