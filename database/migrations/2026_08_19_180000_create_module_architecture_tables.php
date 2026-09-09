<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modules')) {
            Schema::create('modules', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120);
                $table->string('code', 100)->unique();
                $table->text('description')->nullable();
                $table->string('type', 30)->index();
                $table->string('icon', 100)->nullable();
                $table->string('route_prefix', 150)->nullable();
                $table->boolean('is_core')->default(false)->index();
                $table->boolean('is_active')->default(false)->index();
                $table->boolean('is_system')->default(false)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('configuration')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('module_dependencies')) {
            Schema::create('module_dependencies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
                $table->foreignId('required_module_id')->constrained('modules')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['module_id', 'required_module_id'], 'module_dependency_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('module_dependencies');
        Schema::dropIfExists('modules');
    }
};
