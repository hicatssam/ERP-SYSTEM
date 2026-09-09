<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_channels')) {
            Schema::create('sales_channels', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->string('type', 40)->default('direct')->index();
                $table->string('logo')->nullable();
                $table->text('description')->nullable();
                $table->string('discount_type', 20)->default('percentage');
                $table->decimal('discount_value', 14, 3)->default(0);
                $table->decimal('discount_funded_by_channel', 5, 2)->default(0);
                $table->string('commission_type', 20)->default('percentage');
                $table->decimal('commission_value', 14, 3)->default(0);
                $table->string('commission_base', 30)->default('net_sales');
                $table->string('delivery_fee_recipient', 30)->default('restaurant');
                $table->string('settlement_cycle', 30)->default('monthly');
                $table->unsignedSmallInteger('settlement_days')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->text('api_key')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            $this->enhanceSalesChannels();
        }

        $this->normaliseExistingSalesChannels();

        $this->enhanceOrders();
        $this->enhanceInvoices();
    }

    private function enhanceSalesChannels(): void
    {
        $columns = [
            'slug' => fn (Blueprint $t) => $t->string('slug')->nullable()->index(),
            'type' => fn (Blueprint $t) => $t->string('type', 40)->default('direct')->index(),
            'logo' => fn (Blueprint $t) => $t->string('logo')->nullable(),
            'description' => fn (Blueprint $t) => $t->text('description')->nullable(),
            'discount_type' => fn (Blueprint $t) => $t->string('discount_type', 20)->default('percentage'),
            'discount_value' => fn (Blueprint $t) => $t->decimal('discount_value', 14, 3)->default(0),
            'discount_funded_by_channel' => fn (Blueprint $t) => $t->decimal('discount_funded_by_channel', 5, 2)->default(0),
            'commission_type' => fn (Blueprint $t) => $t->string('commission_type', 20)->default('percentage'),
            'commission_value' => fn (Blueprint $t) => $t->decimal('commission_value', 14, 3)->default(0),
            'commission_base' => fn (Blueprint $t) => $t->string('commission_base', 30)->default('net_sales'),
            'delivery_fee_recipient' => fn (Blueprint $t) => $t->string('delivery_fee_recipient', 30)->default('restaurant'),
            'settlement_cycle' => fn (Blueprint $t) => $t->string('settlement_cycle', 30)->default('monthly'),
            'settlement_days' => fn (Blueprint $t) => $t->unsignedSmallInteger('settlement_days')->default(0),
            'is_active' => fn (Blueprint $t) => $t->boolean('is_active')->default(true)->index(),
            'api_key' => fn (Blueprint $t) => $t->text('api_key')->nullable(),
            'notes' => fn (Blueprint $t) => $t->text('notes')->nullable(),
            'sort_order' => fn (Blueprint $t) => $t->unsignedInteger('sort_order')->default(0),
            'deleted_at' => fn (Blueprint $t) => $t->softDeletes(),
        ];

        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn('sales_channels', $name)) {
                Schema::table('sales_channels', $definition);
            }
        }
    }

    private function enhanceOrders(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (! Schema::hasColumn('orders', 'sales_channel_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreignId('sales_channel_id')
                    ->nullable()
                    ->constrained('sales_channels')
                    ->nullOnDelete();
            });
        }

        $this->addFinancialSnapshotColumns('orders');
    }

    private function normaliseExistingSalesChannels(): void
    {
        if (! Schema::hasTable('sales_channels')) {
            return;
        }

        $usedSlugs = [];
        DB::table('sales_channels')
            ->select(['id', 'name', 'slug', 'discount_type', 'commission_type'])
            ->orderBy('id')
            ->get()
            ->each(function (object $channel) use (&$usedSlugs): void {
                $baseSlug = Str::slug((string) ($channel->slug ?: $channel->name));
                $baseSlug = $baseSlug !== '' ? $baseSlug : "channel-{$channel->id}";
                $slug = $baseSlug;
                $suffix = 2;

                while (in_array($slug, $usedSlugs, true)) {
                    $slug = "{$baseSlug}-{$suffix}";
                    $suffix++;
                }

                $usedSlugs[] = $slug;
                DB::table('sales_channels')->where('id', $channel->id)->update([
                    'slug' => $slug,
                    'discount_type' => in_array($channel->discount_type, ['percentage', 'fixed'], true)
                        ? $channel->discount_type
                        : 'percentage',
                    'commission_type' => in_array($channel->commission_type, ['percentage', 'fixed'], true)
                        ? $channel->commission_type
                        : 'percentage',
                ]);
            });
    }

    private function enhanceInvoices(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        if (! Schema::hasColumn('invoices', 'sales_channel_id')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->foreignId('sales_channel_id')
                    ->nullable()
                    ->constrained('sales_channels')
                    ->nullOnDelete();
            });
        }

        $this->addFinancialSnapshotColumns('invoices');
    }

    private function addFinancialSnapshotColumns(string $tableName): void
    {
        $columns = [
            'channel_discount_type' => fn (Blueprint $t) => $t->string('channel_discount_type', 20)->nullable(),
            'channel_discount_value' => fn (Blueprint $t) => $t->decimal('channel_discount_value', 14, 3)->default(0),
            'channel_discount_amount' => fn (Blueprint $t) => $t->decimal('channel_discount_amount', 14, 2)->default(0),
            'channel_discount_funding_rate' => fn (Blueprint $t) => $t->decimal('channel_discount_funding_rate', 5, 2)->default(0),
            'channel_discount_funded_by_channel' => fn (Blueprint $t) => $t->decimal('channel_discount_funded_by_channel', 14, 2)->default(0),
            'channel_discount_funded_by_restaurant' => fn (Blueprint $t) => $t->decimal('channel_discount_funded_by_restaurant', 14, 2)->default(0),
            'total_after_channel_discount' => fn (Blueprint $t) => $t->decimal('total_after_channel_discount', 14, 2)->default(0),
            'channel_commission_type' => fn (Blueprint $t) => $t->string('channel_commission_type', 20)->nullable(),
            'channel_commission_value' => fn (Blueprint $t) => $t->decimal('channel_commission_value', 14, 3)->default(0),
            'channel_commission_base' => fn (Blueprint $t) => $t->string('channel_commission_base', 30)->nullable(),
            'channel_commission_amount' => fn (Blueprint $t) => $t->decimal('channel_commission_amount', 14, 2)->default(0),
            'channel_net_revenue' => fn (Blueprint $t) => $t->decimal('channel_net_revenue', 14, 2)->default(0),
        ];

        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn($tableName, $name)) {
                Schema::table($tableName, $definition);
            }
        }
    }

    public function down(): void
    {
        // Financial snapshots and historical links are intentionally preserved.
        // Reverse them only through a reviewed, project-specific migration.
    }
};
