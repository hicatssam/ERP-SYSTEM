<?php

namespace Tests\Unit;

use App\Support\ModuleRegistry;
use PHPUnit\Framework\TestCase;

class KitchenModuleRegistryTest extends TestCase
{
    public function test_kitchen_and_kds_are_registered_as_implemented(): void
    {
        $modules = collect(ModuleRegistry::modules())->keyBy('code');

        $this->assertTrue((bool) $modules->get('kitchen')['implemented']);
        $this->assertTrue((bool) $modules->get('kds')['implemented']);
        $this->assertFalse((bool) $modules->get('kitchen')['initial_active']);
        $this->assertFalse((bool) $modules->get('kds')['initial_active']);
    }

    public function test_kitchen_dependencies_are_explicit(): void
    {
        $dependencies = ModuleRegistry::dependencies();

        $this->assertSame(
            ['restaurant', 'sales', 'products', 'locations'],
            $dependencies['kitchen']
        );
        $this->assertSame(['kitchen'], $dependencies['kds']);
    }
}
