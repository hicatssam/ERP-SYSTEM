<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * MySQL limits index names to 64 characters. Keep explicit names here so
     * Laravel does not generate names from the long table names below.
     */
    private const SUPPLIER_PRICE_EFFECTIVE_INDEX = 'spph_product_effective_idx';

    private const SUPPLIER_PRICE_REFERENCE_INDEX = 'spph_reference_idx';

    private const GOODS_RECEIPT_ORDER_ITEM_UNIQUE = 'gri_receipt_order_item_uq';

    private const STOCK_MOVEMENT_PRODUCT_DATE_INDEX = 'stock_movements_product_created_at_index';

    public function up(): void
    {
        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->string('code', 3)->unique();
                $table->string('name', 100);
                $table->string('name_ar', 100)->nullable();
                $table->string('symbol', 12)->nullable();
                $table->unsignedTinyInteger('decimal_places')->default(2);
                $table->boolean('is_base')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'is_base']);
            });
        }

        if (! Schema::hasTable('exchange_rates')) {
            Schema::create('exchange_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
                $table->decimal('rate_to_base', 18, 8);
                $table->date('effective_date');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['currency_id', 'effective_date']);
                $table->index(['currency_id', 'effective_date']);
            });
        }

        if (! Schema::hasTable('document_sequences')) {
            Schema::create('document_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('document_type', 50);
                $table->unsignedSmallInteger('year');
                $table->unsignedInteger('last_number')->default(0);
                $table->timestamps();

                $table->unique(['document_type', 'year']);
            });
        }

        if (! Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('supplier_code', 50)->unique();
                $table->string('name');
                $table->string('company_name')->nullable();
                $table->string('contact_person')->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('whatsapp', 30)->nullable();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->string('city', 100)->nullable();
                $table->string('country', 100)->nullable();
                $table->string('tax_number', 100)->nullable();
                $table->string('commercial_registration', 100)->nullable();
                $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
                $table->string('payment_terms', 100)->nullable();
                $table->decimal('credit_limit', 14, 2)->default(0);
                $table->decimal('opening_balance', 14, 2)->default(0);
                $table->string('status', 30)->default('active');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'currency_id']);
                $table->index('name');
            });
        }

        if (! Schema::hasTable('supplier_contacts')) {
            Schema::create('supplier_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->string('name');
                $table->string('position', 100)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('whatsapp', 30)->nullable();
                $table->string('email')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['supplier_id', 'is_primary']);
            });
        }

        if (! Schema::hasTable('supplier_products')) {
            Schema::create('supplier_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->string('supplier_sku', 100)->nullable();
                $table->string('supplier_product_name')->nullable();
                $table->decimal('purchase_price', 16, 4)->default(0);
                $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
                $table->decimal('minimum_order_quantity', 12, 3)->default(1);
                $table->unsignedInteger('lead_time_days')->nullable();
                $table->boolean('is_preferred')->default(false);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['supplier_id', 'product_id']);
                $table->index(['product_id', 'is_preferred', 'is_active']);
            });
        }

        if (! Schema::hasTable('supplier_product_price_histories')) {
            Schema::create('supplier_product_price_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_product_id')->constrained('supplier_products')->cascadeOnDelete();
                $table->decimal('purchase_price', 16, 4);
                $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
                $table->decimal('exchange_rate', 18, 8)->default(1);
                $table->decimal('base_purchase_price', 16, 4);
                $table->string('reference_type', 80)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->timestamp('effective_at');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(
                    ['supplier_product_id', 'effective_at'],
                    self::SUPPLIER_PRICE_EFFECTIVE_INDEX
                );
                $table->index(
                    ['reference_type', 'reference_id'],
                    self::SUPPLIER_PRICE_REFERENCE_INDEX
                );
            });
        }

        // MySQL does not roll back DDL as a transaction. If a previous run
        // stopped while creating this table, it can exist without its indexes.
        $this->ensureIndex(
            'supplier_product_price_histories',
            ['supplier_product_id', 'effective_at'],
            self::SUPPLIER_PRICE_EFFECTIVE_INDEX
        );
        $this->ensureIndex(
            'supplier_product_price_histories',
            ['reference_type', 'reference_id'],
            self::SUPPLIER_PRICE_REFERENCE_INDEX
        );

        if (! Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->string('purchase_order_number', 50)->unique();
                $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
                $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
                $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
                $table->decimal('exchange_rate', 18, 8)->default(1);
                $table->date('order_date');
                $table->date('expected_delivery_date')->nullable();
                $table->string('status', 40)->default('draft');
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('shipping_cost', 14, 2)->default(0);
                $table->decimal('grand_total', 14, 2)->default(0);
                $table->decimal('base_grand_total', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['supplier_id', 'status']);
                $table->index(['location_id', 'status']);
                $table->index('order_date');
            });
        }

        if (! Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->string('description')->nullable();
                $table->decimal('ordered_quantity', 12, 3);
                $table->decimal('received_quantity', 12, 3)->default(0);
                $table->decimal('unit_price', 16, 4);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('line_total', 14, 2);
                $table->decimal('base_unit_cost', 16, 4)->default(0);
                $table->decimal('base_line_total', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['purchase_order_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $table) {
                $table->id();
                $table->string('receipt_number', 50)->unique();
                $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
                $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
                $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
                $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
                $table->decimal('exchange_rate', 18, 8)->default(1);
                $table->string('status', 30)->default('draft');
                $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('received_at');
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['purchase_order_id', 'status']);
                $table->index(['location_id', 'received_at']);
            });
        }

        if (! Schema::hasTable('goods_receipt_items')) {
            Schema::create('goods_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
                $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->decimal('ordered_quantity', 12, 3);
                $table->decimal('received_quantity', 12, 3);
                $table->decimal('accepted_quantity', 12, 3);
                $table->decimal('rejected_quantity', 12, 3)->default(0);
                $table->decimal('unit_cost', 16, 4);
                $table->decimal('base_unit_cost', 16, 4)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->decimal('base_line_total', 14, 2)->default(0);
                $table->string('batch_number', 100)->nullable();
                $table->date('manufacturing_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(
                    ['goods_receipt_id', 'purchase_order_item_id'],
                    self::GOODS_RECEIPT_ORDER_ITEM_UNIQUE
                );
                $table->index(['product_id', 'expiry_date']);
            });
        }

        $this->ensureIndex(
            'goods_receipt_items',
            ['goods_receipt_id', 'purchase_order_item_id'],
            self::GOODS_RECEIPT_ORDER_ITEM_UNIQUE,
            'unique'
        );

        if (! Schema::hasTable('inventory_batches')) {
            Schema::create('inventory_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('goods_receipt_item_id')->nullable()->constrained('goods_receipt_items')->nullOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
                $table->string('batch_number', 100)->nullable();
                $table->date('manufacturing_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->decimal('received_quantity', 12, 3)->default(0);
                $table->decimal('available_quantity', 12, 3)->default(0);
                $table->decimal('unit_cost', 16, 4)->default(0);
                $table->decimal('base_unit_cost', 16, 4)->default(0);
                $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
                $table->timestamps();

                $table->index(['product_id', 'location_id', 'expiry_date']);
                $table->index('batch_number');
            });
        }

        if (! Schema::hasTable('supplier_invoices')) {
            Schema::create('supplier_invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number', 80)->unique();
                $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
                $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
                $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
                $table->decimal('exchange_rate', 18, 8)->default(1);
                $table->date('invoice_date');
                $table->date('due_date')->nullable();
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('grand_total', 14, 2)->default(0);
                $table->decimal('base_grand_total', 14, 2)->default(0);
                $table->decimal('paid_amount', 14, 2)->default(0);
                $table->decimal('credited_amount', 14, 2)->default(0);
                $table->decimal('remaining_amount', 14, 2)->default(0);
                $table->string('status', 30)->default('unpaid');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['supplier_id', 'status']);
                $table->index(['location_id', 'invoice_date']);
                $table->index('due_date');
            });
        }

        if (! Schema::hasTable('supplier_invoice_items')) {
            Schema::create('supplier_invoice_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->string('description');
                $table->decimal('quantity', 12, 3)->default(1);
                $table->decimal('unit_price', 16, 4);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('line_total', 14, 2);
                $table->timestamps();

                $table->index('supplier_invoice_id');
            });
        }

        if (Schema::hasTable('supplier_invoices') && ! Schema::hasColumn('supplier_invoices', 'credited_amount')) {
            Schema::table('supplier_invoices', function (Blueprint $table) {
                $table->decimal('credited_amount', 14, 2)->default(0)->after('paid_amount');
            });
        }

        if (! Schema::hasTable('supplier_payments')) {
            Schema::create('supplier_payments', function (Blueprint $table) {
                $table->id();
                $table->string('payment_number', 50)->unique();
                $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
                $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->restrictOnDelete();
                $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
                $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
                $table->decimal('exchange_rate', 18, 8)->default(1);
                $table->decimal('amount', 14, 2);
                $table->decimal('base_amount', 14, 2);
                $table->decimal('applied_amount', 14, 2);
                $table->date('payment_date');
                $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
                $table->string('reference_number', 100)->nullable();
                $table->string('status', 30)->default('confirmed');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();

                $table->index(['supplier_id', 'payment_date']);
                $table->index(['supplier_invoice_id', 'status']);
            });
        }

        // A supplier return is a separate business document.  It reverses
        // physical inventory through stock_movements and, when linked, reduces
        // the balance of the supplier invoice without reusing customer invoices.
        if (! Schema::hasTable('purchase_returns')) {
            Schema::create('purchase_returns', function (Blueprint $table) {
                $table->id();
                $table->string('return_number', 50)->unique();
                $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
                $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
                $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();
                $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
                $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
                $table->decimal('exchange_rate', 18, 8)->default(1);
                $table->string('status', 30)->default('draft');
                $table->decimal('grand_total', 14, 2)->default(0);
                $table->decimal('base_grand_total', 14, 2)->default(0);
                $table->foreignId('returned_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('returned_at');
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['supplier_id', 'status']);
                $table->index(['goods_receipt_id', 'status']);
                $table->index(['location_id', 'returned_at']);
            });
        }

        if (! Schema::hasTable('purchase_return_items')) {
            Schema::create('purchase_return_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
                $table->foreignId('goods_receipt_item_id')->constrained('goods_receipt_items')->restrictOnDelete();
                $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->decimal('return_quantity', 12, 3);
                $table->decimal('unit_cost', 16, 4);
                $table->decimal('base_unit_cost', 16, 4);
                $table->decimal('line_total', 14, 2);
                $table->decimal('base_line_total', 14, 2);
                $table->string('reason', 100)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['goods_receipt_item_id', 'product_id']);
                $table->index('inventory_batch_id');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'tracks_batch')) {
                $table->boolean('tracks_batch')->default(false);
            }

            if (! Schema::hasColumn('products', 'tracks_expiry')) {
                $table->boolean('tracks_expiry')->default(false);
            }
        });

        Schema::table('inventories', function (Blueprint $table) {
            if (! Schema::hasColumn('inventories', 'reserved_quantity')) {
                $table->decimal('reserved_quantity', 12, 3)->default(0);
            }

            if (! Schema::hasColumn('inventories', 'damaged_quantity')) {
                $table->decimal('damaged_quantity', 12, 3)->default(0);
            }

            if (! Schema::hasColumn('inventories', 'in_transit_quantity')) {
                $table->decimal('in_transit_quantity', 12, 3)->default(0);
            }

            if (! Schema::hasColumn('inventories', 'unit_cost')) {
                $table->decimal('unit_cost', 16, 4)->default(0);
            }

            if (! Schema::hasColumn('inventories', 'last_movement_at')) {
                $table->timestamp('last_movement_at')->nullable();
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'currency_id')) {
                $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            }

            if (! Schema::hasColumn('stock_movements', 'exchange_rate')) {
                $table->decimal('exchange_rate', 18, 8)->nullable();
            }

            if (! Schema::hasColumn('stock_movements', 'unit_cost')) {
                $table->decimal('unit_cost', 16, 4)->nullable();
            }

            if (! Schema::hasColumn('stock_movements', 'base_unit_cost')) {
                $table->decimal('base_unit_cost', 16, 4)->nullable();
            }

            if (! Schema::hasColumn('stock_movements', 'total_cost')) {
                $table->decimal('total_cost', 14, 2)->nullable();
            }

            if (! Schema::hasColumn('stock_movements', 'base_total_cost')) {
                $table->decimal('base_total_cost', 14, 2)->nullable();
            }

            if (! Schema::hasColumn('stock_movements', 'inventory_batch_id')) {
                $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            }

            if (! Schema::hasColumn('stock_movements', 'idempotency_key')) {
                $table->string('idempotency_key', 150)->nullable()->unique();
            }

        });

        $this->ensureIndex(
            'stock_movements',
            ['product_id', 'created_at'],
            self::STOCK_MOVEMENT_PRODUCT_DATE_INDEX
        );

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_transfer_items', 'unit_cost')) {
                $table->decimal('unit_cost', 16, 4)->default(0);
            }

            if (! Schema::hasColumn('stock_transfer_items', 'currency_id')) {
                $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            }
        });

        // Inventory quantities are decimal throughout the system. Keep the
        // automatically generated internal receiving invoice consistent with
        // that precision instead of silently truncating fractional units.
        if (Schema::hasTable('stock_receiving_invoices')) {
            Schema::table('stock_receiving_invoices', function (Blueprint $table) {
                $table->decimal('total_items_ordered', 12, 3)->default(0)->change();
                $table->decimal('total_items_received', 12, 3)->default(0)->change();
                $table->decimal('total_items_damaged', 12, 3)->default(0)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_receiving_invoices')) {
            Schema::table('stock_receiving_invoices', function (Blueprint $table) {
                $table->integer('total_items_ordered')->default(0)->change();
                $table->integer('total_items_received')->default(0)->change();
                $table->integer('total_items_damaged')->default(0)->change();
            });
        }

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            if (Schema::hasColumn('stock_transfer_items', 'currency_id')) {
                $table->dropForeign(['currency_id']);
                $table->dropColumn('currency_id');
            }

            if (Schema::hasColumn('stock_transfer_items', 'unit_cost')) {
                $table->dropColumn('unit_cost');
            }
        });

        $this->dropIndexIfExists(
            'stock_movements',
            self::STOCK_MOVEMENT_PRODUCT_DATE_INDEX
        );
        $this->dropIndexIfExists(
            'stock_movements',
            'stock_movements_idempotency_key_unique',
            true
        );

        Schema::table('stock_movements', function (Blueprint $table) {
            $foreignColumns = ['currency_id', 'inventory_batch_id'];

            foreach ($foreignColumns as $column) {
                if (Schema::hasColumn('stock_movements', $column)) {
                    $table->dropForeign([$column]);
                }
            }

            $columns = [
                'currency_id', 'exchange_rate', 'unit_cost', 'base_unit_cost',
                'total_cost', 'base_total_cost', 'inventory_batch_id',
                'idempotency_key',
            ];

            $existing = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn('stock_movements', $column)
            ));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });

        Schema::table('inventories', function (Blueprint $table) {
            $columns = [
                'reserved_quantity', 'damaged_quantity', 'in_transit_quantity',
                'unit_cost', 'last_movement_at',
            ];

            $existing = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn('inventories', $column)
            ));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });

        Schema::table('supplier_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_invoices', 'credited_amount')) {
                $table->dropColumn('credited_amount');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['tracks_batch', 'tracks_expiry'],
                fn (string $column): bool => Schema::hasColumn('products', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_invoice_items');
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('supplier_product_price_histories');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('supplier_contacts');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }

    /**
     * Add an index only when it is not already present. This makes a rerun of
     * the migration safe after MySQL has left a partially created table.
     *
     * @param array<int, string> $columns
     */
    private function ensureIndex(string $tableName, array $columns, string $indexName, string $type = 'index'): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        // Check the actual MySQL index name instead of relying on the index
        // "type" argument of Schema::hasIndex(). A previous partial migration
        // may already have created this named index even though Laravel did not
        // record the migration as completed.
        if ($this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName, $type): void {
            if ($type === 'unique') {
                $table->unique($columns, $indexName);

                return;
            }

            $table->index($columns, $indexName);
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return Schema::hasIndex($tableName, $indexName);
        }

        $database = DB::connection()->getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $tableName)
            ->where('index_name', $indexName)
            ->exists();
    }

    private function dropIndexIfExists(string $tableName, string $indexName, bool $unique = false): void
    {
        if (! Schema::hasTable($tableName) || ! $this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName, $unique): void {
            if ($unique) {
                $table->dropUnique($indexName);

                return;
            }

            $table->dropIndex($indexName);
        });
    }
};
