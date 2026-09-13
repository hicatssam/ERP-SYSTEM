<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BannerController extends Controller
{
    public function index(): View
    {
        $banners = Banner::query()
            ->with('location')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $locations = Location::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.banners.index', [
            'banners' => $banners,
            'locations' => $locations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateBanner($request);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('banners', 'public');
        }

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        Banner::create($data);

        return back()->with('success', 'تمت إضافة الإعلان بنجاح.');
    }

    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $data = $this->validateBanner($request);

        if ($request->hasFile('image')) {
            if ($banner->image && !preg_match('#^https?://#i', $banner->image)) {
                Storage::disk('public')->delete($banner->image);
            }

            $data['image'] = $request->file('image')->store('banners', 'public');
        }

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $banner->update($data);

        return back()->with('success', 'تم تحديث الإعلان بنجاح.');
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        if ($banner->image && !preg_match('#^https?://#i', $banner->image)) {
            Storage::disk('public')->delete($banner->image);
        }

        $banner->delete();

        return back()->with('success', 'تم حذف الإعلان.');
    }

    private function validateBanner(Request $request): array
    {
        return $request->validate([
            'location_id' => ['nullable', 'exists:locations,id'],
            'title' => ['required', 'string', 'max:190'],
            'kicker' => ['nullable', 'string', 'max:100'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'cta_text' => ['nullable', 'string', 'max:60'],
            'cta_link' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:4096'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }
}
