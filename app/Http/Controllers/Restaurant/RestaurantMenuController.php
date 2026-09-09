<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\StoreRestaurantMenuItemRequest;
use App\Http\Requests\Restaurant\UpdateRestaurantMenuItemRequest;
use App\Models\Category;
use App\Models\LocationProduct;
use App\Models\Product;
use App\Models\RestaurantMenuItem;
use App\Services\Restaurant\RestaurantContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RestaurantMenuController extends Controller
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

        $locations = $this->context->selectableLocations($request->user());

        $query = RestaurantMenuItem::query()
            ->forLocation($location->id)
            ->with([
                'product.category:id,name,name_ar',
                'product.locationProducts' => fn ($locationProducts) =>
                    $locationProducts->where('location_id', $location->id),
            ]);

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));

            $query->where(function (Builder $menu) use ($term) {
                $menu
                    ->where('display_name', 'like', "%{$term}%")
                    ->orWhere('display_name_ar', 'like', "%{$term}%")
                    ->orWhereHas('product', function (Builder $product) use ($term) {
                        $product
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('name_ar', 'like', "%{$term}%")
                            ->orWhere('sku', 'like', "%{$term}%")
                            ->orWhere('barcode', 'like', "%{$term}%");
                    });
            });
        }

        if ($request->filled('category_id')) {
            $query->whereHas(
                'product',
                fn (Builder $product) => $product->where('category_id', $request->integer('category_id'))
            );
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();

            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'available') {
                $query->whereHas('product.locationProducts', fn (Builder $locationProducts) =>
                    $locationProducts
                        ->where('location_id', $location->id)
                        ->where('is_available', true)
                );
            } elseif ($status === 'unavailable') {
                $query->whereHas('product.locationProducts', fn (Builder $locationProducts) =>
                    $locationProducts
                        ->where('location_id', $location->id)
                        ->where('is_available', false)
                );
            }
        }

        $menuItems = $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(24)
            ->withQueryString();

        $categories = Category::query()
            ->active()
            ->whereHas('products.restaurantMenuItems', fn (Builder $menuItems) =>
                $menuItems->where('location_id', $location->id)
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'name_ar']);

        $availableProducts = Product::query()
            ->active()
            ->whereHas('locationProducts', fn (Builder $locationProducts) =>
                $locationProducts->where('location_id', $location->id)
            )
            ->whereDoesntHave('restaurantMenuItems', fn (Builder $menuItems) =>
                $menuItems->where('location_id', $location->id)
            )
            ->with([
                'category:id,name,name_ar',
                'locationProducts' => fn ($locationProducts) =>
                    $locationProducts->where('location_id', $location->id),
            ])
            ->orderBy('name')
            ->get([
                'id',
                'category_id',
                'name',
                'name_ar',
                'sku',
                'image',
                'base_selling_price',
            ]);

        return view('restaurant.menu.index', compact(
            'location',
            'locations',
            'menuItems',
            'categories',
            'availableProducts',
        ));
    }

    public function store(StoreRestaurantMenuItemRequest $request)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $data = $request->validated();

        $product = Product::query()
            ->active()
            ->whereKey($data['product_id'])
            ->whereHas('locationProducts', fn (Builder $locationProducts) =>
                $locationProducts->where('location_id', $location->id)
            )
            ->firstOrFail();

        if ($product->restaurantMenuItems()->where('location_id', $location->id)->exists()) {
            throw ValidationException::withMessages([
                'product_id' => 'هذا المنتج موجود بالفعل في منيو هذا الفرع.',
            ]);
        }

        DB::transaction(function () use ($request, $data, $product, $location) {
            $menuItem = new RestaurantMenuItem();

            $menuItem->fill([
                'location_id' => $location->id,
                'product_id' => $product->id,
                'display_name' => $data['display_name'] ?? null,
                'display_name_ar' => $data['display_name_ar'] ?? null,
                'description' => $data['description'] ?? null,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_active' => $request->boolean('is_active', true),
                'show_in_pos' => $request->boolean('show_in_pos', true),
                'show_in_qr' => $request->boolean('show_in_qr', true),
                'show_in_delivery' => $request->boolean('show_in_delivery', true),
                'inventory_mode' => $data['inventory_mode'] ?? 'auto',
            ]);

            if ($request->hasFile('image')) {
                $menuItem->image = $request->file('image')->store('restaurant-menu', 'public');
            }

            $menuItem->save();

            $locationProduct = LocationProduct::query()
                ->where('location_id', $location->id)
                ->where('product_id', $product->id)
                ->firstOrFail();

            $locationProduct->local_selling_price = $data['selling_price'];
            $locationProduct->is_available = $request->boolean('is_available', true);
            $locationProduct->save();
        });

        return redirect()
            ->route('restaurant.menu.index', ['location_id' => $location->id])
            ->with('success', 'تمت إضافة الصنف إلى منيو هذا الفرع بنجاح.');
    }

    public function update(UpdateRestaurantMenuItemRequest $request, RestaurantMenuItem $menuItem)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $this->ensureMenuItemAtLocation($menuItem, $location->id);
        $data = $request->validated();

        DB::transaction(function () use ($request, $data, $menuItem, $location) {
            $menuItem->fill([
                'display_name' => $data['display_name'] ?? null,
                'display_name_ar' => $data['display_name_ar'] ?? null,
                'description' => $data['description'] ?? null,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_active' => $request->boolean('is_active'),
                'show_in_pos' => $request->boolean('show_in_pos'),
                'show_in_qr' => $request->boolean('show_in_qr'),
                'show_in_delivery' => $request->boolean('show_in_delivery'),
                'inventory_mode' => $data['inventory_mode'] ?? 'auto',
            ]);

            if ($request->hasFile('image')) {
                $this->deleteMenuImage($menuItem);
                $menuItem->image = $request->file('image')->store('restaurant-menu', 'public');
            } elseif ($request->boolean('remove_image')) {
                $this->deleteMenuImage($menuItem);
                $menuItem->image = null;
            }

            $menuItem->save();

            $locationProduct = LocationProduct::query()
                ->where('location_id', $location->id)
                ->where('product_id', $menuItem->product_id)
                ->firstOrFail();

            $locationProduct->local_selling_price = $data['selling_price'];
            $locationProduct->is_available = $request->boolean('is_available');
            $locationProduct->save();
        });

        return redirect()
            ->route('restaurant.menu.index', ['location_id' => $location->id])
            ->with('success', 'تم تحديث صنف المنيو بنجاح.');
    }

    public function toggleAvailability(Request $request, RestaurantMenuItem $menuItem)
    {
        abort_unless($request->user()?->can('restaurant_menu.manage'), 403);

        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        $location = $this->context->resolveLocation(
            $request->user(),
            isset($validated['location_id'])
                ? (int) $validated['location_id']
                : null
        );

        $this->ensureMenuItemAtLocation($menuItem, $location->id);

        $locationProduct = LocationProduct::query()
            ->where('location_id', $location->id)
            ->where('product_id', $menuItem->product_id)
            ->firstOrFail();

        $locationProduct->update([
            'is_available' => ! $locationProduct->is_available,
        ]);

        return back()->with(
            'success',
            $locationProduct->is_available
                ? 'الصنف متاح للبيع الآن.'
                : 'تم إيقاف الصنف مؤقتًا في هذا الفرع.'
        );
    }

    public function toggleStatus(Request $request, RestaurantMenuItem $menuItem)
    {
        abort_unless($request->user()?->can('restaurant_menu.manage'), 403);

        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        $location = $this->context->resolveLocation(
            $request->user(),
            isset($validated['location_id'])
                ? (int) $validated['location_id']
                : null
        );

        $this->ensureMenuItemAtLocation($menuItem, $location->id);

        $menuItem->update([
            'is_active' => ! $menuItem->is_active,
        ]);

        return back()->with('success', 'تم تحديث حالة صنف المنيو لهذا الفرع.');
    }

    private function ensureMenuItemAtLocation(RestaurantMenuItem $menuItem, int $locationId): void
    {
        abort_unless(
            (int) $menuItem->location_id === $locationId,
            403,
            'هذا الصنف لا يخص الفرع الحالي.'
        );

        $allowed = $menuItem->product()
            ->whereHas('locationProducts', fn (Builder $locationProducts) =>
                $locationProducts->where('location_id', $locationId)
            )
            ->exists();

        abort_unless($allowed, 403, 'المنتج غير مرتبط بالفرع الحالي.');
    }

    private function deleteMenuImage(RestaurantMenuItem $menuItem): void
    {
        if ($menuItem->image && Storage::disk('public')->exists($menuItem->image)) {
            Storage::disk('public')->delete($menuItem->image);
        }
    }
}
