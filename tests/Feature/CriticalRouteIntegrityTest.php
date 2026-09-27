<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

class CriticalRouteIntegrityTest extends TestCase
{
    public function test_every_controller_route_targets_an_existing_public_method(): void
    {
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();

            if (! str_contains($action, '@')) {
                continue;
            }

            [$controller, $method] = explode('@', $action, 2);

            $this->assertTrue(
                class_exists($controller),
                "Route {$route->uri()} references missing controller {$controller}."
            );
            $this->assertTrue(
                method_exists($controller, $method),
                "Route {$route->uri()} references missing method {$controller}::{$method}."
            );
            $this->assertTrue(
                (new ReflectionMethod($controller, $method))->isPublic(),
                "Route {$route->uri()} references non-public method {$controller}::{$method}."
            );
        }
    }

    public function test_catalog_routes_separate_view_create_and_update_permissions(): void
    {
        $expectations = [
            'products.index' => 'can:products.view',
            'products.show' => 'can:products.view',
            'products.create' => 'can:products.create',
            'products.store' => 'can:products.create',
            'products.edit' => 'can:products.update',
            'products.update' => 'can:products.update',
            'products.destroy' => 'can:products.update',
            'products.toggle-status' => 'can:products.update',

            'categories.index' => 'can:products.view',
            'categories.show' => 'can:products.view',
            'categories.create' => 'can:products.create',
            'categories.store' => 'can:products.create',
            'categories.edit' => 'can:products.update',
            'categories.update' => 'can:products.update',
            'categories.destroy' => 'can:products.update',
            'categories.toggle-status' => 'can:products.update',
        ];

        foreach ($expectations as $name => $middleware) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull(
                $route,
                "Catalog route [{$name}] is not registered."
            );

            $this->assertContains(
                $middleware,
                $route->gatherMiddleware(),
                "Catalog route [{$name}] must require [{$middleware}]."
            );
        }
    }

    public function test_branch_cake_routes_use_action_specific_permissions(): void
    {
        $expectations = [
            'showroom-cake-requests.index' =>
                'can:showroom_cake_requests.view',
            'showroom-cake-requests.show' =>
                'can:showroom_cake_requests.view',
            'showroom-cake-requests.create' =>
                'can:showroom_cake_requests.create',
            'showroom-cake-requests.store' =>
                'can:showroom_cake_requests.create',
            'showroom-cake-requests.status' =>
                'can:showroom_cake_requests.update_status',
            'showroom-cake-requests.destroy' =>
                'can:showroom_cake_requests.delete',
            'showroom-cake-requests.items.reservation' =>
                'can:showroom_cake_requests.create',
        ];

        foreach ($expectations as $name => $middleware) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull(
                $route,
                "Branch cake route [{$name}] is not registered."
            );

            $this->assertContains(
                $middleware,
                $route->gatherMiddleware(),
                "Branch cake route [{$name}] must require [{$middleware}]."
            );
        }
    }

    public function test_location_payment_account_routes_require_management_permission(): void
    {
        foreach ([
            'locations.payment-accounts.index',
            'locations.payment-accounts.store',
            'locations.payment-accounts.update',
            'locations.payment-accounts.destroy',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull(
                $route,
                "Payment account route [{$name}] is not registered."
            );

            $this->assertContains(
                'can:payment_methods.manage',
                $route->gatherMiddleware(),
                "Payment account route [{$name}] must require payment_methods.manage."
            );
        }
    }

    public function test_critical_workflow_routes_are_registered(): void
    {
        foreach ([
            'inventory.expiry.index',
            'inventory.expiry.print',
            'inventory.expiry.scan',
            'production.recipes.index',
            'production.recipes.approve',
            'production.recipes.new-version',
            'production.orders.complete',
            'purchase-orders.approve',
            'goods-receipts.post',
            'purchase-returns.post',
        ] as $name) {
            $this->assertTrue(Route::has($name), "Critical route [{$name}] is not registered.");
        }
    }
}
