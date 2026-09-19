<?php

namespace Tests\Feature\CustomerOrdering;

use Tests\TestCase;

class CustomerOrderStateArchitectureTest extends TestCase
{
    public function test_admin_override_is_checked_before_pending_payment_guard(): void
    {
        $policy = file_get_contents(app_path('Policies/OrderPolicy.php'));

        $adminPosition = strpos($policy, 'if ($user->isAdmin())');
        $pendingPosition = strpos($policy, "where('status', 'pending_verification')->exists()");

        $this->assertNotFalse($adminPosition);
        $this->assertNotFalse($pendingPosition);
        $this->assertLessThan($pendingPosition, $adminPosition);
    }

    public function test_customer_status_payload_reads_fresh_order_state(): void
    {
        $service = file_get_contents(app_path('Services/Restaurant/CustomerOrderStatusService.php'));

        $this->assertStringContainsString('$order->refresh();', $service);
        $this->assertStringContainsString("'confirmed'", $service);
        $this->assertStringContainsString("'preparing'", $service);
        $this->assertStringContainsString("'ready'", $service);
        $this->assertStringContainsString("'completed'", $service);
    }

    public function test_tracking_page_polls_live_status_endpoint(): void
    {
        $view = file_get_contents(resource_path('views/customer-menu/track.blade.php'));

        $this->assertStringContainsString("setInterval(poll,5000)", $view);
        $this->assertStringContainsString("cache:'no-store'", $view);
        $this->assertStringContainsString('fingerprint', $view);
    }
}
