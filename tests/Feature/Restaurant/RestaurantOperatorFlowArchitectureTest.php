<?php

namespace Tests\Feature\Restaurant;

use Tests\TestCase;

class RestaurantOperatorFlowArchitectureTest extends TestCase
{
    public function test_pos_routes_require_restaurant_and_order_permissions(): void
    {
        $routes = file_get_contents(base_path('routes/restaurant.php'));

        $this->assertIsString($routes);
        $this->assertStringContainsString("'can:restaurant.view'", $routes);
        $this->assertStringContainsString("'can:restaurant_pos.use'", $routes);
        $this->assertStringContainsString("'can:orders.create'", $routes);
    }

    public function test_cross_branch_restaurant_access_uses_explicit_global_permission(): void
    {
        $context = file_get_contents(app_path('Services/Restaurant/RestaurantContextService.php'));

        $this->assertIsString($context);
        $this->assertStringContainsString("restaurant.view_all_locations", $context);
        $this->assertStringNotContainsString("roles.manage", $context);
    }

    public function test_pos_order_service_keeps_operator_waiter_and_cash_session_semantics_separate(): void
    {
        $service = file_get_contents(app_path('Services/Restaurant/RestaurantOrderService.php'));

        $this->assertIsString($service);
        $this->assertStringContainsString("'order_source'", $service);
        $this->assertStringContainsString("'restaurant_pos'", $service);
        $this->assertStringContainsString("hasRole('Waiter')", $service);
        $this->assertStringContainsString('CashSession::query()', $service);
        $this->assertStringContainsString('pos_require_open_cash_session', $service);
        $this->assertStringContainsString('isAvailableAt($locationId)', $service);
    }

    public function test_payment_layer_rejects_cross_branch_methods_and_accounts_and_posts_collections(): void
    {
        $method = file_get_contents(app_path('Models/PaymentMethod.php'));
        $payment = file_get_contents(app_path('Models/Payment.php'));

        $this->assertIsString($method);
        $this->assertIsString($payment);
        $this->assertStringContainsString('function isAvailableAt(', $method);
        $this->assertStringContainsString('location_payment_account_id', $payment);
        $this->assertStringContainsString('locationPaymentMethod', $payment);
        $this->assertStringContainsString('FinancialPostingService::class', $payment);
        $this->assertStringContainsString('verified_by ?: $payment->received_by', $payment);
    }

    public function test_waiter_and_cashier_permission_repair_is_present(): void
    {
        $migration = file_get_contents(database_path(
            'migrations/2026_09_13_183000_fix_restaurant_operator_permissions.php'
        ));

        $this->assertIsString($migration);
        $this->assertStringContainsString("'Cashier'", $migration);
        $this->assertStringContainsString("'Waiter'", $migration);
        $this->assertStringContainsString("'restaurant_pos.use'", $migration);
        $this->assertStringContainsString("'cash_sessions.manage'", $migration);
        $this->assertStringContainsString("'kitchen.ticket.serve'", $migration);
        $this->assertStringContainsString("'orders.complete'", $migration);
    }

    public function test_completion_only_operator_cannot_gain_generic_edit_permission(): void
    {
        $policy = file_get_contents(app_path('Policies/OrderPolicy.php'));

        $this->assertIsString($policy);
        $this->assertStringContainsString('function complete(', $policy);
        $this->assertStringContainsString("routeIs('orders.show', 'orders.complete')", $policy);
        $this->assertStringContainsString("hasPermissionTo('orders.update')", $policy);
        $this->assertStringContainsString("hasPermissionTo('orders.complete')", $policy);
    }
}
