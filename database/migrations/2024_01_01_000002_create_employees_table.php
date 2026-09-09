<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number', 30)->unique();
            $table->string('full_name');
            $table->string('profile_image')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('job_title')->nullable();
            $table->date('hire_date')->nullable();
            $table->enum('employment_status', ['active', 'inactive', 'terminated'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
