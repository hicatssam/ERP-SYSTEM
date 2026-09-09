<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showroom_sweets_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 50)->unique();
            $table->foreignId('requesting_location_id')
                ->constrained('locations')
                ->restrictOnDelete();
            $table->foreignId('factory_location_id')
                ->constrained('locations')
                ->restrictOnDelete();
            $table->enum('status', [
                'submitted',
                'in_progress',
                'fulfilled',
                'rejected',
                'cancelled',
            ])->default('submitted');
            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->text('factory_notes')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('handled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['requesting_location_id', 'status'], 'ssr_branch_status_idx');
            $table->index(['factory_location_id', 'status'], 'ssr_factory_status_idx');
            $table->index('needed_by');
        });

        Schema::create('showroom_sweets_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('showroom_sweets_request_id')
                ->constrained('showroom_sweets_requests')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->string('product_name_snapshot', 255);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('requested_unit', 50)->default('صدر');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showroom_sweets_request_items');
        Schema::dropIfExists('showroom_sweets_requests');
    }
};
