<?php

namespace Tests\Feature\CustomerOrdering;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CustomerMenuRoutesRegistrationTest extends TestCase
{
    public function test_customer_menu_public_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('customer-menu.show'));
        $this->assertTrue(Route::has('customer-menu.orders.store'));
        $this->assertTrue(Route::has('customer-menu.track'));
        $this->assertTrue(Route::has('customer-menu.status'));
    }

    public function test_customer_menu_show_route_is_public(): void
    {
        $route = Route::getRoutes()->getByName('customer-menu.show');

        $this->assertNotNull($route);
        $this->assertNotContains('auth', $route->gatherMiddleware());
        $this->assertNotContains('location.scope', $route->gatherMiddleware());
    }
}
