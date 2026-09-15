<?php

namespace Tests\Feature\CustomerOrdering;

use Tests\TestCase;

class CustomerMenuPaymentFlowArchitectureTest extends TestCase
{
    public function test_tracking_view_renders_payment_and_invoice_state(): void
    {
        $view = file_get_contents(resource_path('views/customer-menu/track.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString("data.payment||{}", $view);
        $this->assertStringContainsString("invoice?.url", $view);
        $this->assertStringContainsString('paymentRemaining', $view);
    }

    public function test_my_orders_refreshes_live_status_from_tracking_endpoint(): void
    {
        $view = file_get_contents(resource_path('views/customer-menu/my-orders.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString("+'/status'", $view);
        $this->assertStringContainsString('payment_status', $view);
        $this->assertStringContainsString('inv?.url', $view);
    }

    public function test_checkout_displays_branch_payment_account_details_and_required_fields(): void
    {
        $view = file_get_contents(resource_path('views/customer-menu/checkout/checkout.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('paymentAccountSelect', $view);
        $this->assertStringContainsString('account_holder_name', $view);
        $this->assertStringContainsString('account_number', $view);
        $this->assertStringContainsString('referenceRequiredStar', $view);
        $this->assertStringContainsString('proofRequiredStar', $view);
    }

    public function test_checkout_explains_ai_receipt_review_without_auto_approval(): void
    {
        $view = file_get_contents(resource_path('views/customer-menu/checkout/checkout.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString("config('services.payment_proof_ai.enabled')", $view);
        $this->assertStringContainsString('استخراج اسم المرسل والحساب والمرجع والمبلغ', $view);
        $this->assertStringContainsString('لا يعتمد النظام أو يرفض الدفع تلقائيًا', $view);
    }

    public function test_checkout_estimate_accounts_for_multiple_units_without_summing_all_items(): void
    {
        $view = file_get_contents(resource_path('views/customer-menu/checkout/checkout.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('base*.35', $view);
        $this->assertStringContainsString('Math.max(defaultPrep,...itemLoads)', $view);
        $this->assertStringContainsString('queue_minutes_per_order', $view);
    }

    public function test_customer_status_service_always_refreshes_order_and_maps_lifecycle_states(): void
    {
        $service = file_get_contents(app_path('Services/Restaurant/CustomerOrderStatusService.php'));

        $this->assertIsString($service);
        $this->assertStringContainsString('$order->refresh();', $service);
        $this->assertStringContainsString("'state' => 'received'", $service);
        $this->assertStringContainsString("'state' => 'accepted'", $service);
        $this->assertStringContainsString("'state' => 'preparing'", $service);
        $this->assertStringContainsString("'state' => 'ready'", $service);
        $this->assertStringContainsString("'state' => 'completed'", $service);
        $this->assertStringContainsString("'state' => 'cancelled'", $service);
    }

    public function test_admin_confirmation_override_is_checked_before_pending_payment_block(): void
    {
        $policy = file_get_contents(app_path('Policies/OrderPolicy.php'));

        $this->assertIsString($policy);
        $adminCheck = strpos($policy, 'if ($user->isAdmin())');
        $pendingCheck = strpos($policy, "where('status', 'pending_verification')");

        $this->assertNotFalse($adminCheck);
        $this->assertNotFalse($pendingCheck);
        $this->assertLessThan($pendingCheck, $adminCheck);
    }

    public function test_public_invoice_has_image_export_button(): void
    {
        $view = file_get_contents(resource_path('views/customer-menu/invoice.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('id="saveImage"', $view);
        $this->assertStringContainsString("canvas.toDataURL('image/png'", $view);
    }

    public function test_menu_banner_controller_is_not_stored_inside_migrations(): void
    {
        $this->assertFileDoesNotExist(database_path('migrations/MenuBannerController.php'));
        $this->assertFileExists(app_path('Http/Controllers/Restaurant/MenuBannerController.php'));
    }
}
