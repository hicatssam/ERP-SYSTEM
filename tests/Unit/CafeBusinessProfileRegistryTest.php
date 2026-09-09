<?php

namespace Tests\Unit;

use App\Support\BusinessProfileRegistry;
use App\Support\ModuleRegistry;
use PHPUnit\Framework\TestCase;

class CafeBusinessProfileRegistryTest extends TestCase
{
    public function test_cafe_profile_reuses_restaurant_engine_and_enables_cafe_requirements(): void
    {
        $profile = collect(BusinessProfileRegistry::profiles())
            ->firstWhere('code', 'cafe');

        $this->assertNotNull($profile);

        foreach ([
            'restaurant',
            'restaurant_pos',
            'restaurant_tables',
            'product_variants',
            'sizes',
            'kitchen',
            'kds',
            'recipes',
            'costing',
        ] as $module) {
            $this->assertContains($module, $profile['modules']);
        }
    }

    public function test_cafe_module_is_an_implemented_industry_marker_over_restaurant_engine(): void
    {
        $module = collect(ModuleRegistry::modules())
            ->firstWhere('code', 'cafe');

        $this->assertNotNull($module);
        $this->assertTrue($module['implemented']);
        $this->assertSame('restaurant', $module['route_prefix']);
        $this->assertFalse($module['initial_active']);

        $dependencies = ModuleRegistry::dependencies()['cafe'] ?? [];

        foreach ([
            'restaurant',
            'restaurant_pos',
            'restaurant_tables',
            'product_variants',
            'sizes',
            'kitchen',
            'kds',
            'recipes',
            'costing',
        ] as $required) {
            $this->assertContains($required, $dependencies);
        }
    }
}
