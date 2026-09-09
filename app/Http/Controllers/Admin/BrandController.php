<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::query()->withCount('products')->orderBy('sort_order')->orderBy('name')->get();
        return view('admin.catalog.brands', compact('brands'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($request->hasFile('logo')) $data['logo'] = $request->file('logo')->store('brands', 'public');
        $data['is_active'] = true;
        $brand = Brand::create($data);
        ActivityLogger::log($request->user()->id, 'brand.created', 'products', Brand::class, $brand->id, null, $brand->toArray());
        return back()->with('success', 'تمت إضافة العلامة التجارية.');
    }

    public function update(Request $request, Brand $brand)
    {
        $before = $brand->toArray();
        $data = $this->validated($request, $brand);
        if ($request->hasFile('logo')) {
            if ($brand->logo && Storage::disk('public')->exists($brand->logo)) Storage::disk('public')->delete($brand->logo);
            $data['logo'] = $request->file('logo')->store('brands', 'public');
        }
        $brand->update($data);
        ActivityLogger::logChange($request->user()->id, 'brand.updated', 'products', Brand::class, $brand->id, $before, $brand->fresh()->toArray());
        return back()->with('success', 'تم تحديث العلامة التجارية.');
    }

    public function toggle(Request $request, Brand $brand)
    {
        $before = $brand->is_active;
        $brand->update(['is_active' => ! $brand->is_active]);
        ActivityLogger::log($request->user()->id, 'brand.toggled', 'products', Brand::class, $brand->id, ['is_active'=>$before], ['is_active'=>$brand->is_active]);
        return back()->with('success', 'تم تحديث حالة العلامة التجارية.');
    }

    private function validated(Request $request, ?Brand $brand = null): array
    {
        return $request->validate([
            'code' => ['required','string','max:60','regex:/^[A-Za-z0-9_\\-]+$/', Rule::unique('brands','code')->ignore($brand?->id)],
            'name' => ['required','string','max:150'],
            'name_ar' => ['nullable','string','max:150'],
            'description' => ['nullable','string'],
            'logo' => ['nullable','image','mimes:jpeg,jpg,png,webp','max:2048'],
            'sort_order' => ['nullable','integer','min:0'],
        ]) + ['sort_order' => (int) $request->input('sort_order', 0)];
    }
}
