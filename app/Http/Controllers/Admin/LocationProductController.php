<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Product;
use Illuminate\Http\Request;

class LocationProductController extends Controller
{
    public function index(Product $product)
    {
        $locationProducts = $product->locationProducts()->with('location')->get();
        $allLocations = Location::active()->get();
        return view('admin.products.location-products', compact('product', 'locationProducts', 'allLocations'));
    }

    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'location_id'          => ['required', 'exists:locations,id'],
            'is_available'         => ['boolean'],
            'local_selling_price'  => ['nullable', 'numeric', 'min:0'],
            'minimum_stock_level'  => ['nullable', 'numeric', 'min:0'],
        ]);

        LocationProduct::updateOrCreate(
            ['location_id' => $data['location_id'], 'product_id' => $product->id],
            $data + ['product_id' => $product->id, 'is_available' => $request->boolean('is_available', true)]
        );

        return back()->with('success', 'تم تحديث توفر المنتج في الموقع.');
    }

    public function update(Request $request, LocationProduct $locationProduct)
    {
        $data = $request->validate([
            'is_available'        => ['boolean'],
            'local_selling_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock_level' => ['nullable', 'numeric', 'min:0'],
        ]);

        $locationProduct->update($data + ['is_available' => $request->boolean('is_available')]);
        return back()->with('success', 'تم التحديث.');
    }

    public function destroy(LocationProduct $locationProduct)
    {
        $locationProduct->update(['is_available' => false]);
        return back()->with('success', 'تم إخفاء المنتج من الموقع.');
    }
}
