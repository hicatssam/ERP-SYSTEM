<?php

namespace Tests\Feature\Production;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductionRoutesRegistrationTest extends TestCase
{
    public function test_production_routes_exist(): void
    {
        $this->assertTrue(Route::has('production.recipes.index'));
        $this->assertTrue(Route::has('production.orders.index'));
        $this->assertTrue(Route::has('production.orders.start'));
        $this->assertTrue(Route::has('production.orders.complete'));
    }
}
