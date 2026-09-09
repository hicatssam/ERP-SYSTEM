<?php

namespace Tests\Feature\Restaurant;

use App\Http\Controllers\Restaurant\CustomerMenuController;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class CustomerMenuArchitectureTest extends TestCase
{
    #[Test]
    public function customer_menu_routes_are_public_and_complete(): void
    {
        foreach ([
            'customer-menu.show', 'customer-menu.tables', 'customer-menu.orders.store',
            'customer-menu.my-orders', 'customer-menu.track', 'customer-menu.status',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing route: {$name}");
            $this->assertNotContains('auth', $route->gatherMiddleware(), "{$name} must remain public.");
        }
    }

    #[Test]
    public function every_customer_menu_action_exists_and_is_public(): void
    {
        foreach (['show', 'tables', 'store', 'myOrders', 'track', 'status'] as $action) {
            $method = new ReflectionMethod(CustomerMenuController::class, $action);
            $this->assertTrue($method->isPublic(), "CustomerMenuController::{$action} must be public.");
        }
    }
}
