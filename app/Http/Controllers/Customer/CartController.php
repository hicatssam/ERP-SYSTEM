<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Concerns\ResolvesCustomerMenuBranding;
use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\View\View;

/**
 * The cart has no server-side storage on the public menu (no customer
 * login), so this controller only needs to hand the branded shell plus
 * the live product catalog to the page — the cart contents themselves
 * live in the browser's localStorage under `dahab_cart_{location_code}`,
 * exactly like the main menu page and the checkout page.
 */
class CartController extends Controller
{
    use ResolvesCustomerMenuBranding;

    public function index(Location $location): View
    {
        abort_unless((bool) $location->is_active && $location->isBranch(), 404);

        return view('customer-menu.cart.cart', [
            'location' => $location,
            'menuItems' => $this->menuItemsFor($location),
            'branding' => $this->branding($location),
            'theme' => $this->theme(),
        ]);
    }
}
