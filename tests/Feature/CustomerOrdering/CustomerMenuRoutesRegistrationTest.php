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
        $this->assertTrue(Route::has('customer-menu.invoice'));
    }

    public function test_customer_menu_show_route_is_public(): void
    {
        foreach (['customer-menu.show', 'customer-menu.track', 'customer-menu.invoice'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route);
            $this->assertNotContains('auth', $route->gatherMiddleware());
            $this->assertNotContains('location.scope', $route->gatherMiddleware());
        }
    }
}
