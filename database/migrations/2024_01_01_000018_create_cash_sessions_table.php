<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->timestamp('opened_at');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->decimal('cash_received', 12, 2)->default(0);
            $table->decimal('cash_refunds', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->default(0); // computed in PHP; SQLite doesn't support storedAs
            $table->decimal('actual_cash', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();
            $table->text('closing_note')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['location_id', 'status']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
