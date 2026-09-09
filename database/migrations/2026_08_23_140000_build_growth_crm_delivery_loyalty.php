<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table): void {
                if (! Schema::hasColumn('customers', 'email')) {
                    $table->string('email')->nullable()->after('secondary_phone');
                }
                if (! Schema::hasColumn('customers', 'birthday')) {
                    $table->date('birthday')->nullable();
                }
                if (! Schema::hasColumn('customers', 'preferred_contact_channel')) {
                    $table->string('preferred_contact_channel', 30)->nullable();
                }
                if (! Schema::hasColumn('customers', 'marketing_opt_in')) {
                    $table->boolean('marketing_opt_in')->default(false);
                }
                if (! Schema::hasColumn('customers', 'marketing_opt_in_at')) {
                    $table->timestamp('marketing_opt_in_at')->nullable();
                }
                if (! Schema::hasColumn('customers', 'crm_status')) {
                    $table->string('crm_status', 30)->default('active');
                }
                if (! Schema::hasColumn('customers', 'crm_owner_id')) {
                    $table->foreignId('crm_owner_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('customers', 'last_contacted_at')) {
                    $table->timestamp('last_contacted_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('customer_addresses')) {
            Schema::create('customer_addresses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('label', 80)->default('عنوان');
                $table->string('recipient_name')->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('address_line1');
                $table->string('address_line2')->nullable();
                $table->string('city', 100)->nullable();
                $table->string('area', 100)->nullable();
                $table->string('landmark')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['customer_id', 'is_default'], 'cust_addr_default_idx');
            });
        }

        if (! Schema::hasTable('crm_tags')) {
            Schema::create('crm_tags', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 80)->unique();
                $table->string('color', 20)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_customer_tag')) {
            Schema::create('crm_customer_tag', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('crm_tag_id')->constrained('crm_tags')->cascadeOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['customer_id', 'crm_tag_id'], 'crm_customer_tag_uq');
            });
        }

        if (! Schema::hasTable('customer_interactions')) {
            Schema::create('customer_interactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 40);
                $table->string('subject')->nullable();
                $table->text('notes');
                $table->timestamp('next_follow_up_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['customer_id', 'created_at'], 'cust_interaction_date_idx');
                $table->index(['next_follow_up_at', 'completed_at'], 'crm_followup_idx');
            });
        }

        if (! Schema::hasTable('loyalty_programs')) {
            Schema::create('loyalty_programs', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->default('برنامج الولاء');
                $table->decimal('points_per_currency_unit', 12, 4)->default(1);
                $table->decimal('minimum_order_amount', 14, 2)->default(0);
                $table->decimal('redemption_value_per_point', 12, 4)->default(0);
                $table->unsignedInteger('minimum_redeem_points')->default(0);
                $table->boolean('is_active')->default(false);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
                $table->index(['is_active', 'starts_at', 'ends_at'], 'loyalty_program_active_idx');
            });
        }

        if (! Schema::hasTable('loyalty_accounts')) {
            Schema::create('loyalty_accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
                $table->bigInteger('points_balance')->default(0);
                $table->unsignedBigInteger('lifetime_earned')->default(0);
                $table->unsignedBigInteger('lifetime_redeemed')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loyalty_transactions')) {
            Schema::create('loyalty_transactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('loyalty_account_id')->constrained('loyalty_accounts')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->string('type', 30);
                $table->bigInteger('points');
                $table->bigInteger('balance_after');
                $table->string('idempotency_key', 150)->unique();
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['customer_id', 'created_at'], 'loyalty_customer_date_idx');
                $table->index(['order_id', 'type'], 'loyalty_order_type_idx');
            });
        }

        if (! Schema::hasTable('delivery_zones')) {
            Schema::create('delivery_zones', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('code', 50)->nullable();
                $table->decimal('fee', 14, 2)->default(0);
                $table->decimal('minimum_order_amount', 14, 2)->default(0);
                $table->unsignedSmallInteger('estimated_minutes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['location_id', 'name'], 'delivery_zone_location_name_uq');
                $table->index(['location_id', 'is_active'], 'delivery_zone_active_idx');
            });
        }

        if (! Schema::hasTable('delivery_tasks')) {
            Schema::create('delivery_tasks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
                $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('customer_address_id')->nullable()->constrained('customer_addresses')->nullOnDelete();
                $table->foreignId('delivery_zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
                $table->foreignId('assigned_driver_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 40)->default('pending');
                $table->string('recipient_name')->nullable();
                $table->string('recipient_phone', 30)->nullable();
                $table->text('address_snapshot');
                $table->decimal('fee_snapshot', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('picked_up_at')->nullable();
                $table->timestamp('out_for_delivery_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['location_id', 'status'], 'delivery_location_status_idx');
                $table->index(['assigned_driver_id', 'status'], 'delivery_driver_status_idx');
            });
        }

        if (! Schema::hasTable('delivery_task_histories')) {
            Schema::create('delivery_task_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('delivery_task_id')->constrained('delivery_tasks')->cascadeOnDelete();
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40);
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['delivery_task_id', 'created_at'], 'delivery_history_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_task_histories');
        Schema::dropIfExists('delivery_tasks');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_accounts');
        Schema::dropIfExists('loyalty_programs');
        Schema::dropIfExists('customer_interactions');
        Schema::dropIfExists('crm_customer_tag');
        Schema::dropIfExists('crm_tags');
        Schema::dropIfExists('customer_addresses');

        // Stability-first rollback: customer columns are intentionally retained.
        // Some installations may have had one or more of these fields before Sprint 08,
        // and dropping them blindly could destroy existing CRM data.
    }
};
