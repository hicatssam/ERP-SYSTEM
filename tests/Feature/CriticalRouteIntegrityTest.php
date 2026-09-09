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
