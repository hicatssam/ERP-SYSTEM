<?php

namespace Tests\Feature\CustomerOrdering;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CustomerMenuRoutesRegistrationTest extends TestCase
{
    public function test_customer_menu_public_routes_are_registered(): void
    {
        foreach ([
            'customer-menu.show',
            'customer-menu.orders.store',
            'customer-menu.track',
            'customer-menu.status',
            'customer-menu.invoice',
            'customer-menu.my-orders',
            'customer-menu.payment-options',
            'customer-menu.checkout',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Missing route: {$routeName}");
        }
    }

    public function test_customer_menu_public_routes_do_not_require_staff_authentication(): void
    {
        foreach ([
            'customer-menu.show',
            'customer-menu.track',
            'customer-menu.status',
            'customer-menu.invoice',
            'customer-menu.my-orders',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Route not registered: {$routeName}");
            $this->assertNotContains('auth', $route->gatherMiddleware(), "{$routeName} unexpectedly requires auth");
            $this->assertNotContains('location.scope', $route->gatherMiddleware(), "{$routeName} unexpectedly uses location.scope");
        }
    }

    public function test_invoice_route_is_scoped_by_public_token_pattern(): void
    {
        $route = Route::getRoutes()->getByName('customer-menu.invoice');

        $this->assertNotNull($route);
        $this->assertSame('menu/track/{token}/invoice', $route->uri());
        $this->assertSame('[A-Za-z0-9_-]{20,80}', $route->wheres['token'] ?? null);
    }
}
