<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('units')) {
            Schema::create('units', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name', 100);
                $table->string('name_ar', 100)->nullable();
                $table->string('symbol', 20)->nullable();
                $table->boolean('allow_decimal')->default(false);
                $table->unsignedTinyInteger('precision')->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_system')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('brands')) {
            Schema::create('brands', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('name', 150);
                $table->string('name_ar', 150)->nullable();
                $table->text('description')->nullable();
                $table->string('logo')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('sizes')) {
            Schema::create('sizes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('name', 100);
                $table->string('name_ar', 100)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('colors')) {
            Schema::create('colors', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('name', 100);
                $table->string('name_ar', 100)->nullable();
                $table->string('hex_code', 7)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('product_attributes')) {
            Schema::create('product_attributes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('name', 120);
                $table->string('name_ar', 120)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('product_attribute_values')) {
            Schema::create('product_attribute_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_attribute_id')->constrained('product_attributes')->cascadeOnDelete();
                $table->string('code', 60);
                $table->string('name', 120);
                $table->string('name_ar', 120)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['product_attribute_id', 'code'], 'pav_attribute_code_unique');
                $table->index(['product_attribute_id', 'is_active']);
            });
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'brand_id')) {
                $table->foreignId('brand_id')->nullable()->after('category_id')->constrained('brands')->nullOnDelete();
            }

            if (! Schema::hasColumn('products', 'unit_id')) {
                $table->foreignId('unit_id')->nullable()->after('unit')->constrained('units')->nullOnDelete();
            }

            if (! Schema::hasColumn('products', 'product_type')) {
                $table->string('product_type', 30)->default('standard')->after('unit_id');
                $table->index('product_type');
            }
        });

        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('size_id')->nullable()->constrained('sizes')->nullOnDelete();
                $table->foreignId('color_id')->nullable()->constrained('colors')->nullOnDelete();
                $table->string('name', 180)->nullable();
                $table->string('name_ar', 180)->nullable();
                $table->string('sku', 80)->unique();
                $table->string('barcode', 100)->nullable()->unique();
                $table->decimal('selling_price', 12, 2)->nullable();
                $table->string('image')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('configuration')->nullable();
                $table->timestamps();
                $table->index(['product_id', 'is_active']);
                $table->index(['product_id', 'size_id', 'color_id'], 'pv_product_size_color_idx');
            });
        }

        if (! Schema::hasTable('product_variant_attribute_values')) {
            Schema::create('product_variant_attribute_values', function (Blueprint $table) {
                $table->unsignedBigInteger('product_variant_id');
                $table->unsignedBigInteger('product_attribute_value_id');

                $table->primary(
                    ['product_variant_id', 'product_attribute_value_id'],
                    'pvav_primary'
                );

                /*
                 * أسماء قصيرة صريحة لأن MySQL يحدد اسم الـ identifier بـ 64 حرفًا.
                 */
                $table->foreign(
                    'product_variant_id',
                    'pvav_variant_fk'
                )
                    ->references('id')
                    ->on('product_variants')
                    ->cascadeOnDelete();

                $table->foreign(
                    'product_attribute_value_id',
                    'pvav_attr_value_fk'
                )
                    ->references('id')
                    ->on('product_attribute_values')
                    ->cascadeOnDelete();
            });
        } else {
            /*
             * إذا فشل الـ migration سابقًا أثناء إنشاء الـ FK الثاني،
             * يكون الجدول قد أُنشئ فعلًا لكن migration لم يُسجل.
             * نصلح الجدول الموجود بدون حذف بيانات أو Drop للجدول.
             */
            $this->repairVariantAttributeValueForeignKeys();
        }

        $this->seedAndBackfillUnits();
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_attribute_values');
        Schema::dropIfExists('product_variants');

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'brand_id')) {
                $table->dropConstrainedForeignId('brand_id');
            }
            if (Schema::hasColumn('products', 'unit_id')) {
                $table->dropConstrainedForeignId('unit_id');
            }
            if (Schema::hasColumn('products', 'product_type')) {
                $table->dropColumn('product_type');
            }
        });

        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('colors');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('units');
    }

    private function repairVariantAttributeValueForeignKeys(): void
    {
        $table = 'product_variant_attribute_values';

        if (! $this->columnHasForeignKey($table, 'product_variant_id')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign(
                    'product_variant_id',
                    'pvav_variant_fk'
                )
                    ->references('id')
                    ->on('product_variants')
                    ->cascadeOnDelete();
            });
        }

        if (! $this->columnHasForeignKey($table, 'product_attribute_value_id')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign(
                    'product_attribute_value_id',
                    'pvav_attr_value_fk'
                )
                    ->references('id')
                    ->on('product_attribute_values')
                    ->cascadeOnDelete();
            });
        }
    }

    private function columnHasForeignKey(
        string $table,
        string $column
    ): bool {
        if (DB::getDriverName() !== 'mysql') {
            return true;
        }

        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }

    private function seedAndBackfillUnits(): void
    {
        $defaults = [
            'piece' => ['Piece', 'قطعة', 'قطعة', false, 0, 10],
            'kg' => ['Kilogram', 'كيلوغرام', 'كغ', true, 3, 20],
            'gram' => ['Gram', 'غرام', 'غ', true, 3, 30],
            'box' => ['Box', 'صندوق', 'صندوق', false, 0, 40],
            'tray' => ['Tray', 'صينية', 'صينية', false, 0, 50],
        ];

        foreach ($defaults as $code => [$name, $nameAr, $symbol, $allowDecimal, $precision, $sortOrder]) {
            DB::table('units')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'name_ar' => $nameAr,
                    'symbol' => $symbol,
                    'allow_decimal' => $allowDecimal,
                    'precision' => $precision,
                    'is_active' => true,
                    'is_system' => true,
                    'sort_order' => $sortOrder,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $legacyUnits = DB::table('products')
            ->whereNotNull('unit')
            ->where('unit', '!=', '')
            ->distinct()
            ->pluck('unit');

        foreach ($legacyUnits as $legacyCode) {
            if (! DB::table('units')->where('code', $legacyCode)->exists()) {
                DB::table('units')->insert([
                    'code' => (string) $legacyCode,
                    'name' => ucfirst((string) $legacyCode),
                    'name_ar' => (string) $legacyCode,
                    'symbol' => null,
                    'allow_decimal' => true,
                    'precision' => 3,
                    'is_active' => true,
                    'is_system' => false,
                    'sort_order' => 900,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $unitId = DB::table('units')->where('code', $legacyCode)->value('id');

            DB::table('products')
                ->where('unit', $legacyCode)
                ->whereNull('unit_id')
                ->update(['unit_id' => $unitId]);
        }
    }
};
