<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('setup_wizard_runs')) {
            return;
        }

        Schema::create('setup_wizard_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedTinyInteger('current_step')->default(1);

            $table->unsignedBigInteger('business_profile_id')->nullable()->index();
            $table->string('business_profile_code', 100)->nullable()->index();

            $table->json('business_data')->nullable();
            $table->json('branding_data')->nullable();
            $table->json('operational_data')->nullable();
            $table->json('module_plan')->nullable();
            $table->json('temporary_files')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('completed_by')->nullable()->index();

            $table->timestamp('last_saved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('applied_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_wizard_runs');
    }
};
