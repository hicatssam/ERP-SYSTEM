<?php

namespace Tests\Unit;

use App\Support\ModuleRegistry;
use PHPUnit\Framework\TestCase;

class ModuleRegistryProductionTest extends TestCase
{
    public function test_recipes_and_production_are_implemented(): void
    {
        $modules = collect(ModuleRegistry::modules())->keyBy('code');

        $this->assertTrue((bool)$modules['recipes']['implemented']);
        $this->assertTrue((bool)$modules['production']['implemented']);
        $this->assertContains('recipes', ModuleRegistry::dependencies()['production']);
    }
}
