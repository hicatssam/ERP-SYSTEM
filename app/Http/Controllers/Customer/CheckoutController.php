<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Concerns\ResolvesCustomerMenuBranding;
use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    use ResolvesCustomerMenuBranding;

    public function index(Location $location): View
    {
        abort_unless((bool) $location->is_active && $location->isBranch(), 404);

        $tables = $this->tableOptions($location);

        return view('customer-menu.checkout.checkout', [
            'location' => $location,
            // The shared cart engine needs the live catalog to rehydrate
            // older localStorage rows and recalculate current prices.
            'menuItems' => $this->menuItemsFor($location),
            'tables' => $tables,
            'branding' => $this->branding($location),
            'theme' => $this->theme(),
            'requestToken' => (string) Str::uuid(),
            'serviceOptions' => $this->serviceOptions(),
        ]);
    }
}
