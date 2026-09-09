<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('special_cake_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('origin_branch_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('factory_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->date('required_date');
            $table->time('required_time')->nullable();
            $table->string('cake_type')->nullable();
            $table->string('cake_size')->nullable();
            $table->decimal('cake_weight', 6, 2)->nullable();
            $table->unsignedInteger('persons_count')->nullable();
            $table->string('flavor')->nullable();
            $table->string('filling')->nullable();
            $table->string('shape')->nullable();
            $table->string('color')->nullable();
            $table->string('cake_text')->nullable();
            $table->string('theme')->nullable();
            $table->text('special_instructions')->nullable();
            $table->decimal('total_price', 12, 2)->default(0);
            $table->enum('status', [
                'draft', 'pending_factory_review', 'accepted', 'modification_requested',
                'rejected', 'scheduled', 'in_preparation', 'decorating',
                'quality_check', 'ready', 'sent_to_branch', 'received_by_branch',
                'ready_for_customer', 'completed', 'cancelled', 'delayed', 'issue_open'
            ])->default('draft');
            $table->enum('payment_status', [
                'payment_pending', 'partially_paid', 'paid',
                'pending_payment_verification', 'partially_refunded', 'refunded'
            ])->default('payment_pending');
            $table->enum('payment_arrangement', [
                'pay_now', 'deposit', 'partial_payment', 'pay_on_pickup', 'pending_verification'
            ])->default('deposit');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('origin_branch_id');
            $table->index('status');
            $table->index('required_date');
        });

        Schema::create('cake_order_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('special_cake_order_id')->constrained('special_cake_orders')->cascadeOnDelete();
            $table->enum('attachment_type', ['reference_image', 'customer_design', 'final_cake_image', 'other']);
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('cake_order_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('special_cake_order_id')->constrained('special_cake_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('comment');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();

            $table->index('special_cake_order_id');
        });

        Schema::create('cake_order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('special_cake_order_id')->constrained('special_cake_orders')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('special_cake_order_id');
        });

        Schema::create('cake_receiving_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('special_cake_order_id')->constrained('special_cake_orders')->cascadeOnDelete();
            $table->enum('issue_type', ['damaged', 'wrong_design', 'wrong_text', 'wrong_size', 'other']);
            $table->text('description');
            $table->string('image')->nullable();
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cake_receiving_issues');
        Schema::dropIfExists('cake_order_status_histories');
        Schema::dropIfExists('cake_order_comments');
        Schema::dropIfExists('cake_order_attachments');
        Schema::dropIfExists('special_cake_orders');
    }
};
