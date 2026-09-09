<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showroom_cake_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 50)->unique();
            $table->foreignId('requesting_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('factory_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->enum('status', [
                'draft', 'submitted', 'in_progress', 'fulfilled', 'rejected', 'cancelled'
            ])->default('draft');
            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->text('factory_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('requesting_location_id');
            $table->index('status');
        });

        Schema::create('showroom_cake_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('showroom_cake_request_id')
                  ->constrained('showroom_cake_requests')
                  ->cascadeOnDelete();
            $table->string('cake_type')->nullable();
            $table->string('cake_size')->nullable();
            $table->string('flavor')->nullable();
            $table->string('shape')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('showroom_cake_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showroom_cake_request_items');
        Schema::dropIfExists('showroom_cake_requests');
    }
};
