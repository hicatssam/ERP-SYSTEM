<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Services\ProductCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductArchitectureV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_tables_exist(): void
    {
        foreach ([
            'units','brands','sizes','colors','product_attributes',
            'product_attribute_values','product_variants',
            'product_variant_attribute_values',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table . ' is missing');
        }
    }

    public function test_legacy_product_unit_column_is_preserved(): void
    {
        $this->assertTrue(Schema::hasColumn('products', 'unit'));
        $this->assertTrue(Schema::hasColumn('products', 'unit_id'));
        $this->assertTrue(Unit::query()->where('code', 'piece')->exists());
    }

    public function test_generated_ean13_is_valid_and_unique_across_catalog_layers(): void
    {
        $service = app(ProductCodeService::class);
        $barcode = $service->generateEan13();
        $this->assertTrue($service->isValidEan13($barcode));
        $this->assertTrue($service->barcodeAvailable($barcode));
    }

    public function test_new_catalog_models_are_writeable(): void
    {
        $brand = Brand::create(['code'=>'TEST','name'=>'Test','is_active'=>true]);
        $this->assertDatabaseHas('brands', ['id'=>$brand->id,'code'=>'TEST']);
        $this->assertSame(0, ProductVariant::query()->count());
    }
}
