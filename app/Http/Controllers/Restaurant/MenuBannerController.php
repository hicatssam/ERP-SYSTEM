<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\MenuBanner;
use App\Services\Restaurant\RestaurantContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MenuBannerController extends Controller
{
    public function __construct(
        private readonly RestaurantContextService $context,
    ) {
    }

    public function index(Request $request)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $locations = $this->context->selectableLocations(
            $request->user()
        );

        $banners = MenuBanner::query()
            ->where(function ($query) use ($location): void {
                $query
                    ->where('location_id', $location->id)
                    ->orWhereNull('location_id');
            })
            ->orderByRaw('location_id IS NULL DESC')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view(
            'restaurant.menu.banners.index',
            compact('location', 'locations', 'banners')
        );
    }

    public function store(Request $request)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $validated = $this->validateBanner($request);

        $path = $request->file('image')
            ->store('menu-banners', 'public');

        MenuBanner::query()->create([
            'location_id' => $location->id,
            'image' => $path,
            'title' => $validated['title'] ?? null,
            'subtitle' => $validated['subtitle'] ?? null,
            'badge_text' => $validated['badge_text'] ?? null,
            'link_url' => $validated['link_url'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        return redirect()
            ->route(
                'restaurant.menu.banners.index',
                ['location_id' => $location->id]
            )
            ->with('success', 'تمت إضافة بانر المنيو بنجاح.');
    }

    public function update(
        Request $request,
        MenuBanner $banner
    ) {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $this->ensureBannerAtLocation($banner, $location->id);

        $validated = $this->validateBanner(
            $request,
            requireImage: false
        );

        $data = [
            'title' => $validated['title'] ?? null,
            'subtitle' => $validated['subtitle'] ?? null,
            'badge_text' => $validated['badge_text'] ?? null,
            'link_url' => $validated['link_url'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ];

        if ($request->hasFile('image')) {
            $this->deleteImage($banner->image);

            $data['image'] = $request->file('image')
                ->store('menu-banners', 'public');
        }

        $banner->update($data);

        return redirect()
            ->route(
                'restaurant.menu.banners.index',
                ['location_id' => $location->id]
            )
            ->with('success', 'تم تحديث بانر المنيو.');
    }

    public function toggleActive(
        Request $request,
        MenuBanner $banner
    ) {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $this->ensureBannerAtLocation($banner, $location->id);

        $banner->update([
            'is_active' => ! $banner->is_active,
        ]);

        return back()->with(
            'success',
            $banner->is_active
                ? 'تم تفعيل البانر.'
                : 'تم إيقاف البانر.'
        );
    }

    public function destroy(
        Request $request,
        MenuBanner $banner
    ) {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $this->ensureBannerAtLocation($banner, $location->id);

        $this->deleteImage($banner->image);
        $banner->delete();

        return redirect()
            ->route(
                'restaurant.menu.banners.index',
                ['location_id' => $location->id]
            )
            ->with('success', 'تم حذف بانر المنيو.');
    }

    private function validateBanner(
        Request $request,
        bool $requireImage = true
    ): array {
        return $request->validate([
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')
                    ->where('is_active', true),
            ],
            'image' => [
                $requireImage ? 'required' : 'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:8192',
            ],
            'title' => ['nullable', 'string', 'max:150'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'badge_text' => ['nullable', 'string', 'max:80'],
            'link_url' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => [
                'nullable',
                'date',
                'after_or_equal:starts_at',
            ],
        ]);
    }

    private function ensureBannerAtLocation(
        MenuBanner $banner,
        int $locationId
    ): void {
        abort_unless(
            $banner->location_id !== null
            && (int) $banner->location_id === $locationId,
            403,
            'هذا البانر لا يخص الفرع الحالي.'
        );
    }

    private function deleteImage(?string $path): void
    {
        $path = trim((string) $path);

        if ($path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
