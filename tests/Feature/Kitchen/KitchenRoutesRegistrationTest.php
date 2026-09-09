<?php

namespace Tests\Feature\Kitchen;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class KitchenRoutesRegistrationTest extends TestCase
{
    public function test_kitchen_and_kds_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('kitchen.tickets.index'));
        $this->assertTrue(Route::has('kitchen.stations.index'));
        $this->assertTrue(Route::has('kds.index'));
        $this->assertTrue(Route::has('kds.feed'));
    }
}
