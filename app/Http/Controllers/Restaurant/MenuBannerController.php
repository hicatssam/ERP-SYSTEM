<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\MenuBanner;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MenuBannerController extends Controller
{
    public function index(): View
    {
        $banners = MenuBanner::query()
            ->with(['location:id,name', 'product:id,name,name_ar'])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $locations = Location::query()
            ->where('is_active', true)
            ->branches()
            ->orderBy('name')
            ->get(['id', 'name']);

        $products = Product::query()
            ->active()
            ->orderByRaw("COALESCE(NULLIF(name_ar, ''), name)")
            ->get(['id', 'name', 'name_ar'])
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'name' => (string) ($product->name_ar ?: $product->name),
            ]);

        return view('restaurant.menu-banners.index', [
            'banners' => $banners,
            'locations' => $locations,
            'products' => $products,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, imageRequired: true);
        $data['location_id'] = $this->normalizedLocationId($request);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['image'] = $request->file('image')->store('menu-banners', 'public');

        MenuBanner::create($data);

        return back()->with('success', 'تمت إضافة البانر بنجاح.');
    }

    public function update(Request $request, MenuBanner $banner): RedirectResponse
    {
        $data = $this->validated($request, imageRequired: false);
        $data['location_id'] = $this->normalizedLocationId($request);
        $data['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('image')) {
            if ($banner->image && Storage::disk('public')->exists($banner->image)) {
                Storage::disk('public')->delete($banner->image);
            }

            $data['image'] = $request->file('image')->store('menu-banners', 'public');
        }

        $banner->update($data);

        return back()->with('success', 'تم تحديث البانر بنجاح.');
    }

    public function toggleActive(MenuBanner $banner): RedirectResponse
    {
        $banner->update(['is_active' => ! $banner->is_active]);

        return back()->with('success', $banner->is_active ? 'تم تفعيل البانر.' : 'تم إيقاف البانر.');
    }

    public function destroy(MenuBanner $banner): RedirectResponse
    {
        if ($banner->image && Storage::disk('public')->exists($banner->image)) {
            Storage::disk('public')->delete($banner->image);
        }

        $banner->delete();

        return back()->with('success', 'تم حذف البانر.');
    }

    private function validated(Request $request, bool $imageRequired): array
    {
        return $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'title' => ['nullable', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'badge_text' => ['nullable', 'string', 'max:30'],
            'link_url' => ['nullable', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }

    private function normalizedLocationId(Request $request): ?int
    {
        $value = $request->input('location_id');

        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }
}
