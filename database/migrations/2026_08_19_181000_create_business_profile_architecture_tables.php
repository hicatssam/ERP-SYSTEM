<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_profiles')) {
            Schema::create('business_profiles', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120);
                $table->string('code', 100)->unique();
                $table->text('description')->nullable();
                $table->string('icon', 100)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_system')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('configuration')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('business_profile_modules')) {
            Schema::create('business_profile_modules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_profile_id')->constrained('business_profiles')->cascadeOnDelete();
                $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
                $table->boolean('is_required')->default(false);
                $table->boolean('is_default')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['business_profile_id', 'module_id'], 'business_profile_module_unique');
            });
        }

        if (! Schema::hasTable('module_bundles')) {
            Schema::create('module_bundles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_profile_id')->nullable()->constrained('business_profiles')->nullOnDelete();
                $table->string('name', 120);
                $table->string('code', 100)->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('configuration')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('module_bundle_modules')) {
            Schema::create('module_bundle_modules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('module_bundle_id')->constrained('module_bundles')->cascadeOnDelete();
                $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['module_bundle_id', 'module_id'], 'module_bundle_module_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('module_bundle_modules');
        Schema::dropIfExists('module_bundles');
        Schema::dropIfExists('business_profile_modules');
        Schema::dropIfExists('business_profiles');
    }
};
