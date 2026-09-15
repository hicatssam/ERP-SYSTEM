<?php

namespace Tests\Feature\Restaurant;

use App\Http\Controllers\CustomerOrdering\CustomerMenuController;
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
        foreach ([
            'show', 'products', 'productShow', 'favorites', 'tables',
            'queueStatus', 'paymentOptions', 'store', 'myOrders', 'track', 'status',
        ] as $action) {
            $method = new ReflectionMethod(CustomerMenuController::class, $action);
            $this->assertTrue($method->isPublic(), "CustomerMenuController::{$action} must be public.");
        }
    }

    #[Test]
    public function customer_menu_home_keeps_the_reference_mobile_sections(): void
    {
        $view = file_get_contents(resource_path('views/customer-menu/show.blade.php'));

        foreach ([
            'reference-header',
            'banner-track',
            'categories',
            'featuredGrid',
            'productGrid',
            'customer-menu.partials.bottom-nav',
        ] as $marker) {
            $this->assertStringContainsString($marker, $view, "Missing reference menu section: {$marker}");
        }

        $this->assertStringNotContainsString("view()->exists('')", $view);
    }

    #[Test]
    public function customer_menu_designer_fields_are_saved_by_settings_controller(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/SettingsController.php'));

        foreach ([
            'customer_menu_primary_color',
            'customer_menu_cover_image',
            'customer_menu_intro_image',
            'customer_menu_product_card_radius',
            'customer_menu_card_image_ratio',
            'customer_menu_show_featured',
            'customer_menu_featured_limit',
        ] as $key) {
            $this->assertStringContainsString("'{$key}'", $controller, "Missing persisted menu setting: {$key}");
        }
    }

}
