<?php

namespace Tests\Feature\Finance;

use Tests\TestCase;

class PaymentFlowArchitectureTest extends TestCase
{
    public function test_location_payment_accounts_have_canonical_parent_relation(): void
    {
        $account = file_get_contents(app_path('Models/LocationPaymentAccount.php'));
        $method = file_get_contents(app_path('Models/LocationPaymentMethod.php'));
        $migration = file_get_contents(database_path('migrations/2026_09_13_175000_normalize_location_payment_accounts.php'));

        $this->assertIsString($account);
        $this->assertIsString($method);
        $this->assertIsString($migration);

        $this->assertStringContainsString('location_payment_method_id', $account);
        $this->assertStringContainsString('function locationPaymentMethod()', $account);
        $this->assertStringContainsString('function activeAccounts()', $method);
        $this->assertStringContainsString("whereNull('location_payment_method_id')", $migration);
    }

    public function test_customer_menu_payment_options_use_branch_method_accounts(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CustomerOrdering/CustomerMenuController.php'));

        $this->assertIsString($controller);
        $this->assertStringContainsString('LocationPaymentMethod::query()', $controller);
        $this->assertStringContainsString("'activeAccounts'", $controller);
        $this->assertStringContainsString("'wallet_number'", $controller);
        $this->assertStringContainsString("'location_payment_method_id'", $controller);
    }

    public function test_payment_and_refund_models_use_central_status_synchronizer(): void
    {
        $payment = file_get_contents(app_path('Models/Payment.php'));
        $refund = file_get_contents(app_path('Models/Refund.php'));
        $sync = file_get_contents(app_path('Services/Payments/OrderPaymentStatusSynchronizer.php'));

        $this->assertIsString($payment);
        $this->assertIsString($refund);
        $this->assertIsString($sync);

        $this->assertStringContainsString('OrderPaymentStatusSynchronizer', $payment);
        $this->assertStringContainsString('OrderPaymentStatusSynchronizer', $refund);
        $this->assertStringContainsString("'pending_payment_verification'", $sync);
        $this->assertStringContainsString("'partially_refunded'", $sync);
        $this->assertStringContainsString("'refunded'", $sync);
    }
}
