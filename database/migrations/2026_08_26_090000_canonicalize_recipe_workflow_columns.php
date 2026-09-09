<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recipes')) {
            $addCode = ! Schema::hasColumn('recipes', 'code');

            Schema::table('recipes', function (Blueprint $table): void {
                if (! Schema::hasColumn('recipes', 'code')) {
                    $table->string('code', 80)->nullable();
                }
                if (! Schema::hasColumn('recipes', 'status')) {
                    $table->string('status', 30)->default('draft');
                }
                if (! Schema::hasColumn('recipes', 'is_active')) {
                    $table->boolean('is_active')->default(false);
                }
                if (! Schema::hasColumn('recipes', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable();
                }
                if (! Schema::hasColumn('recipes', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
            });

            if ($addCode) {
                DB::table('recipes')->orderBy('id')->eachById(function ($recipe): void {
                    DB::table('recipes')->where('id', $recipe->id)->update([
                        'code' => sprintf('RCP-%d-V%d', $recipe->product_id, $recipe->version),
                    ]);
                });

                Schema::table('recipes', function (Blueprint $table): void {
                    $table->unique('code', 'recipes_code_uq');
                });
            }
        }

        if (Schema::hasTable('recipe_items')) {
            $copyWaste = ! Schema::hasColumn('recipe_items', 'expected_waste_percent')
                && Schema::hasColumn('recipe_items', 'waste_percent');

            Schema::table('recipe_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('recipe_items', 'expected_waste_percent')) {
                    $table->decimal('expected_waste_percent', 7, 3)->default(0);
                }
                if (! Schema::hasColumn('recipe_items', 'unit_snapshot')) {
                    $table->string('unit_snapshot', 60)->nullable();
                }
                if (! Schema::hasColumn('recipe_items', 'stage')) {
                    $table->string('stage', 120)->nullable();
                }
                if (! Schema::hasColumn('recipe_items', 'sort_order')) {
                    $table->unsignedInteger('sort_order')->default(0);
                }
            });

            if ($copyWaste) {
                DB::table('recipe_items')->update([
                    'expected_waste_percent' => DB::raw('waste_percent'),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Additive compatibility migration: historical installations can have
        // different source schemas, so rollback must not remove shared fields.
    }
};
