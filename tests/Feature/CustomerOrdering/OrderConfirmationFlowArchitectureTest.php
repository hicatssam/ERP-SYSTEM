<?php

namespace Tests\Feature\CustomerOrdering;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OrderConfirmationFlowArchitectureTest extends TestCase
{
    public function test_order_confirm_route_is_post_and_points_to_order_controller(): void
    {
        $route = Route::getRoutes()->getByName('orders.confirm');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertStringContainsString(
            'App\\Http\\Controllers\\Sales\\OrderController@confirm',
            $route->getActionName()
        );
    }

    public function test_admin_gate_bypasses_permission_middleware(): void
    {
        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringContainsString('Gate::before', $provider);
        $this->assertStringContainsString('$user->isAdmin()', $provider);
        $this->assertStringContainsString('return true;', $provider);
    }

    public function test_order_screen_posts_confirm_form_to_named_route(): void
    {
        $view = file_get_contents(resource_path('views/sales/orders/show.blade.php'));

        $this->assertStringContainsString("route('orders.confirm', \$order)", $view);
        $this->assertStringContainsString('id="confirmOrderForm"', $view);
        $this->assertStringContainsString('id="confirmOrderSubmitBtn"', $view);
        $this->assertStringContainsString("openOrderActionModal('confirm')", $view);
    }

    public function test_controller_authorizes_and_calls_confirmation_service(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Sales/OrderController.php'));

        $this->assertStringContainsString("\$this->authorize('confirm', \$order);", $controller);
        $this->assertStringContainsString("\$this->orderService->confirmOrder(", $controller);
        $this->assertStringContainsString('تم تأكيد الطلب وخصم المخزون بنجاح.', $controller);
    }

    public function test_confirmation_service_is_atomic_and_updates_downstream_state(): void
    {
        $service = file_get_contents(app_path('Services/Orders/OrderService.php'));

        $this->assertStringContainsString('public function confirmOrder(Order $order, User $user): Order', $service);
        $this->assertStringContainsString('lockForUpdate()', $service);
        $this->assertStringContainsString("'status' => 'confirmed'", $service);
        $this->assertStringContainsString("'confirmed_at' => now()", $service);
        $this->assertStringContainsString('createFromOrder(', $service);
        $this->assertStringContainsString('dispatchIfEligible(', $service);
        $this->assertStringContainsString('OrderStatusChanged::dispatch(', $service);
    }

    public function test_customer_tracking_reads_fresh_confirmation_state(): void
    {
        $statusService = file_get_contents(app_path('Services/Restaurant/CustomerOrderStatusService.php'));
        $trackView = file_get_contents(resource_path('views/customer-menu/track.blade.php'));

        $this->assertStringContainsString('$order->refresh();', $statusService);
        $this->assertStringContainsString("if (\$orderStatus === 'confirmed')", $statusService);
        $this->assertStringContainsString("'state' => 'accepted'", $statusService);
        $this->assertStringContainsString("setInterval(poll,5000)", $trackView);
        $this->assertStringContainsString("cache:'no-store'", $trackView);
    }
}
