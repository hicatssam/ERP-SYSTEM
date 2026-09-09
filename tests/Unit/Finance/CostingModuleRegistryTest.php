<?php

namespace Tests\Unit\Finance;

use App\Support\ModuleRegistry;
use PHPUnit\Framework\TestCase;

class CostingModuleRegistryTest extends TestCase
{
    public function test_costing_module_is_registered_with_financial_dependencies(): void
    {
        $module = collect(ModuleRegistry::modules())->firstWhere('code', 'costing');

        $this->assertNotNull($module);
        $this->assertTrue($module['implemented']);
        $this->assertFalse($module['initial_active']);
        $this->assertSame(
            ['finance', 'accounting', 'sales', 'inventory'],
            ModuleRegistry::dependencies()['costing']
        );
    }
}
