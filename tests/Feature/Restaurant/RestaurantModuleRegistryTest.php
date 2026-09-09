<?php

namespace Tests\Feature\Restaurant;

use App\Support\ModuleRegistry;
use Tests\TestCase;

class RestaurantModuleRegistryTest extends TestCase
{
    public function test_restaurant_foundation_modules_are_marked_implemented(): void
    {
        $modules = collect(ModuleRegistry::modules())->keyBy('code');

        $this->assertTrue($modules['restaurant']['implemented']);
        $this->assertTrue($modules['restaurant_pos']['implemented']);
        $this->assertTrue($modules['restaurant_tables']['implemented']);

        $this->assertTrue($modules['kitchen']['implemented']);
        $this->assertTrue($modules['kds']['implemented']);
    }

    public function test_pos_dependencies_keep_existing_erp_authority(): void
    {
        $dependencies = ModuleRegistry::dependencies();

        $this->assertContains('sales', $dependencies['restaurant_pos']);
        $this->assertContains('products', $dependencies['restaurant_pos']);
        $this->assertContains('payments', $dependencies['restaurant_pos']);
        $this->assertContains('payment_methods', $dependencies['restaurant_pos']);
        $this->assertContains('sales_channels', $dependencies['restaurant_pos']);
    }
}
