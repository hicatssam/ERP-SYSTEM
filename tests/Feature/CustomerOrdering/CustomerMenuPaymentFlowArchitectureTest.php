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
