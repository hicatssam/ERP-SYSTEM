<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\ModuleService;
use App\Services\ProductCodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Notifications\ProductCreatedNotification;
use App\Services\Notifications\NotificationDispatcher;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductCodeService $codes,
        private readonly ModuleService $modules,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Product::query()
            ->with(['category', 'brand', 'unitDefinition'])
            ->withCount('variants');

        if (! $this->isSystemAdmin($user)) {
            $location = $this->managedBranch($user);
            $query
                ->whereHas('locationProducts', fn (Builder $locationProducts) => $this->constrainLocationProducts($locationProducts, (int) $location->id))
                ->with([
                    'locationProducts' => fn (Relation $locationProducts) =>
                        $this->constrainLocationProducts($locationProducts, (int) $location->id)->with('location'),
                ]);
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function (Builder $filter) use ($term) {
                $filter->where('name', 'like', "%{$term}%")
                    ->orWhere('name_ar', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%");
            });
        }

        if ($request->filled('category_id')) $query->where('category_id', $request->integer('category_id'));
        if ($request->filled('brand_id')) $query->where('brand_id', $request->integer('brand_id'));
        if ($request->filled('product_type')) $query->where('product_type', $request->string('product_type')->toString());

        $products = $query->orderBy('name')->paginate(25)->withQueryString();
        $categories = Category::query()->active()->orderBy('sort_order')->get();
        $brands = $this->modules->isEnabled('brands') ? Brand::query()->active()->orderBy('sort_order')->get() : collect();
        $variantsEnabled = $this->modules->isEnabled('product_variants');

        return view('admin.products.index', compact('products', 'categories', 'brands', 'variantsEnabled'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        [$categories, $locations, $managedLocation, $canManageLocations] = $this->formLocationData($user);
        $units = Unit::query()->active()->orderBy('sort_order')->orderBy('name')->get();
        $brands = $this->modules->isEnabled('brands') ? Brand::query()->active()->orderBy('sort_order')->get() : collect();
        $variantsEnabled = $this->modules->isEnabled('product_variants');

        return view('admin.products.create', compact(
            'categories', 'locations', 'managedLocation', 'canManageLocations',
            'units', 'brands', 'variantsEnabled'
        ));
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();
        $isAdmin = $this->isSystemAdmin($user);
        $locationIds = $isAdmin
            ? $this->validatedAdminLocationIds(
                $request->input('location_ids', [])
            )
            : [(int) $this->managedBranch($user)->id];

        $this->ensureCategoryAvailableToUser((int) $data['category_id'], $user);
        $this->normalizeCatalogFields($data, $request);
        unset($data['location_id']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = DB::transaction(function () use ($data, $locationIds) {
            $product = new Product();
            $product->fill($data);
            $product->sku = $this->codes->generateProductSku();
            $product->barcode = $this->codes->generateEan13();
            $product->save();
            $this->syncProductLocations($product, $locationIds, false);
            return $product;
        });

        NotificationDispatcher::notifyByPermissions(
            new ProductCreatedNotification($product),
            ['products.view', 'products.update', 'inventory.view'],
            $locationIds,
            ['products.view_all_locations', 'inventory.view_all_locations'],
            $user->id,
        );

        return redirect()->route('products.show', $product)->with(
            'success',
            "تم إنشاء المنتج بنجاح. SKU: {$product->sku} — الباركود: {$product->barcode}"
        );
    }

    public function show(Request $request, Product $product)
    {
        $user = $request->user();
        $this->ensureProductAccess($product, $user);

        $relations = [
            'category', 'brand', 'unitDefinition',
            'variants.size', 'variants.color', 'variants.attributeValues.attribute',
        ];

        $canViewSupplierData = $this->isSystemAdmin($user)
            || $user->can('suppliers.view')
            || $user->can('purchase_orders.create');

        if ($canViewSupplierData) {
            $relations[] = 'supplierProducts.supplier';
            $relations[] = 'supplierProducts.currency';
            $relations[] = 'supplierProducts.purchaseUnit';
        }

        if ($this->isSystemAdmin($user)) {
            $relations[] = 'locationProducts.location';
            $product->load($relations);
        } else {
            $location = $this->managedBranch($user);
            $product->load(array_merge($relations, [
                'locationProducts' => fn (Relation $locationProducts) =>
                    $this->constrainLocationProducts($locationProducts, (int) $location->id)->with('location'),
            ]));
        }

        $variantsEnabled = $this->modules->isEnabled('product_variants');
        return view('admin.products.show', compact(
            'product',
            'variantsEnabled',
            'canViewSupplierData'
        ));
    }

    public function edit(Request $request, Product $product)
    {
        $user = $request->user();
        $this->ensureProductAccess($product, $user);

        $categories = Category::query()->active();
        $locations = collect();
        $managedLocation = null;
        $selectedLocationIds = [];
        $canManageLocations = $this->isSystemAdmin($user);

        if ($canManageLocations) {
    $locations = $this->activeProductLocations();

    $selectedLocationIds = $product
        ->locationProducts()
        ->where('is_available', true)
        ->pluck('location_id')
        ->map(fn ($id) => (int) $id)
        ->all();
}



        $categories = $categories->orderBy('sort_order')->get();
        $units = Unit::query()->active()->orderBy('sort_order')->orderBy('name')->get();
        $brands = $this->modules->isEnabled('brands') ? Brand::query()->active()->orderBy('sort_order')->get() : collect();
        $variantsEnabled = $this->modules->isEnabled('product_variants');

        return view('admin.products.edit', compact(
            'product', 'categories', 'locations', 'managedLocation', 'selectedLocationIds',
            'canManageLocations', 'units', 'brands', 'variantsEnabled'
        ));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $user = $request->user();
        $this->ensureProductAccess($product, $user);
        $data = $request->validated();
        $isAdmin = $this->isSystemAdmin($user);
        $locationIds = $isAdmin
            ? $this->validatedAdminLocationIds(
                $request->input('location_ids', [])
            )
            : null;

        $this->ensureCategoryAvailableToUser((int) $data['category_id'], $user);

        if (
            ($data['product_type'] ?? 'standard') === ProductType::STANDARD->value
            && $product->variants()->exists()
        ) {
            throw ValidationException::withMessages([
                'product_type' => 'لا يمكن تحويل المنتج إلى عادي قبل معالجة المتغيرات الموجودة.',
            ]);
        }

        $this->normalizeCatalogFields($data, $request);
        unset($data['remove_image'], $data['location_id']);

        if ($request->hasFile('image')) {
            $this->deleteProductImage($product);
            $data['image'] = $request->file('image')->store('products', 'public');
        } elseif ($request->boolean('remove_image')) {
            $this->deleteProductImage($product);
            $data['image'] = null;
        } else {
            unset($data['image']);
        }

        DB::transaction(function () use ($product, $data, $isAdmin, $locationIds) {
            $product->fill($data);
            if (blank($product->sku)) $product->sku = $this->codes->generateProductSku();
            if (blank($product->barcode)) $product->barcode = $this->codes->generateEan13();
            $product->save();
            if ($isAdmin) $this->syncProductLocations($product, $locationIds ?? [], true);
        });

        return redirect()->route('products.show', $product)->with('success', 'تم تحديث المنتج بنجاح.');
    }

    public function destroy(Request $request, Product $product)
    {
        $this->ensureProductAccess($product, $request->user());
        return back()->with('error', 'لا يمكن حذف المنتجات. استخدم التعطيل للحفاظ على السجلات المالية والمخزنية.');
    }

    public function toggleStatus(Request $request, Product $product)
    {
        $this->ensureProductAccess($product, $request->user());
        $product->update(['is_active' => ! $product->is_active]);
        return back()->with('success', 'تم تحديث حالة المنتج.');
    }

    private function normalizeCatalogFields(array &$data, Request $request): void
    {
        $unit = Unit::query()->active()->findOrFail((int) $data['unit_id']);
        $data['unit'] = $unit->code; // backward compatibility for every existing workflow.
        $data['tracks_batch'] = $request->boolean('tracks_batch') || $request->boolean('tracks_expiry');
        $data['tracks_expiry'] = $request->boolean('tracks_expiry');

        $type = ProductType::from((string) $data['product_type']);
        if ($type === ProductType::VARIANT && ! $this->modules->isEnabled('product_variants')) {
            throw ValidationException::withMessages([
                'product_type' => 'فعّل وحدة متغيرات المنتجات أولًا قبل إنشاء منتج بمتغيرات.',
            ]);
        }

        if (! empty($data['brand_id']) && ! $this->modules->isEnabled('brands')) {
            throw ValidationException::withMessages([
                'brand_id' => 'فعّل وحدة العلامات التجارية أولًا.',
            ]);
        }
    }

    private function formLocationData(User $user): array
    {
        $categories = Category::query()->active();
        $locations = collect();
        $managedLocation = null;
        $canManageLocations = $this->isSystemAdmin($user);

        if ($canManageLocations) {
            $locations = $this->activeProductLocations();
        } else {
            $managedLocation = $this->managedBranch($user);
            $categories->whereHas('products', fn (Builder $products) => $products->whereHas(
                'locationProducts',
                fn (Builder $locationProducts) => $this->constrainLocationProducts($locationProducts, (int) $managedLocation->id)
            ));
        }

        return [$categories->orderBy('sort_order')->get(), $locations, $managedLocation, $canManageLocations];
    }

    private function constrainLocationProducts(Builder|Relation $locationProducts, int $locationId): Builder|Relation
    {
        return $locationProducts->where('location_id', $locationId)->where('is_available', true);
    }

    private function isSystemAdmin(User $user): bool
    {
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) return true;
        if ((bool) ($user->is_admin ?? false)) return true;
        if ($user->can('roles.manage')) return true;
        if (method_exists($user, 'getRoleNames')) {
            if ($user->getRoleNames()->contains(fn ($roleName) => $this->isAdminRoleName($roleName))) return true;
        }
        return $this->isAdminRoleName($user->role ?? null);
    }

    private function isAdminRoleName(mixed $role): bool
    {
        if ($role instanceof \BackedEnum) $role = $role->value;
        elseif ($role instanceof \UnitEnum) $role = $role->name;
        elseif (is_object($role)) $role = $role->name ?? $role->slug ?? $role->value ?? null;
        $role = Str::lower(trim((string) $role));
        $role = str_replace(['_', ' '], '-', $role);
        return in_array($role, ['admin','super-admin','administrator','system-admin','system-administrator','مدير-النظام','مسؤول-النظام'], true);
    }

    private function activeProductLocations(): Collection
{
    return Location::query()
        ->active()
        ->whereIn('type', [
            'branch',
            'factory',
        ])
        ->orderByRaw("
            CASE
                WHEN type = 'factory' THEN 0
                WHEN type = 'branch' THEN 1
                ELSE 2
            END
        ")
        ->orderBy('name')
        ->get();
}

    private function validatedAdminLocationIds(array|string|null $locationSelection): array
    {
        /*
         * The product is one record in products, while availability is many-to-many
         * through location_products. The admin may therefore select any subset
         * of active branches/factories.
         */
        $requestedIds = collect(
            is_array($locationSelection)
                ? $locationSelection
                : ($locationSelection === 'all' ? ['all'] : [$locationSelection])
        )
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->values();

        $activeLocationIds = $this->activeProductLocations()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($requestedIds->contains('all')) {
            if ($activeLocationIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'location_ids' => 'لا توجد فروع أو مصانع فعالة.',
                ]);
            }

            return $activeLocationIds->all();
        }

        $locationIds = $requestedIds
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($locationIds->isEmpty()) {
            throw ValidationException::withMessages([
                'location_ids' => 'يجب اختيار فرع أو مصنع واحد على الأقل.',
            ]);
        }

        $invalidIds = $locationIds->diff($activeLocationIds);

        if ($invalidIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'location_ids' => 'أحد الفروع أو المصانع المحددة غير صالح أو غير فعال.',
            ]);
        }

        return $locationIds->all();
    }

    private function syncProductLocations(Product $product, array $locationIds, bool $disableUnselected): void
    {
        $locationIds = collect($locationIds)->map(fn ($id) => (int) $id)->unique()->values();
        foreach ($locationIds as $locationId) {
            $locationProduct = $product->locationProducts()->where('location_id', $locationId)->first();
            if (! $locationProduct) {
                $product->locationProducts()->create([
                    'location_id' => $locationId,
                    'is_available' => true,
                    'local_selling_price' => $product->base_selling_price,
                    'minimum_stock_level' => 0,
                ]);
                continue;
            }
            $locationProduct->is_available = true;
            if ($locationProduct->local_selling_price === null) $locationProduct->local_selling_price = $product->base_selling_price;
            if ($locationProduct->minimum_stock_level === null) $locationProduct->minimum_stock_level = 0;
            $locationProduct->save();
        }
        if (! $disableUnselected) return;
        $unselected = $product->locationProducts();
        if ($locationIds->isNotEmpty()) $unselected->whereNotIn('location_id', $locationIds->all());
        $unselected->update(['is_available' => false]);
    }

    private function ensureProductAccess(Product $product, User $user): void
    {
        if ($this->isSystemAdmin($user)) return;
        $location = $this->managedBranch($user);
        $canAccess = $product->locationProducts()->where('location_id', $location->id)->where('is_available', true)->exists();
        abort_unless($canAccess, 403, 'لا يمكنك الوصول إلى منتج لا يخص فرعك.');
    }

    private function ensureCategoryAvailableToUser(int $categoryId, User $user): void
    {
        if ($this->isSystemAdmin($user)) return;
        $location = $this->managedBranch($user);
        $canUse = Category::query()->whereKey($categoryId)->whereHas('products', fn (Builder $products) => $products->whereHas(
            'locationProducts',
            fn (Builder $locationProducts) => $this->constrainLocationProducts($locationProducts, (int) $location->id)
        ))->exists();
        abort_unless($canUse, 403, 'لا يمكنك اختيار فئة لا تخص فرعك.');
    }

    private function managedBranch(User $user): Location
    {
        $primaryLocation = $user->primaryLocation();
        $isActiveBranch = $primaryLocation && $primaryLocation->isBranch() && Location::active()->whereKey($primaryLocation->id)->exists();
        abort_unless($isActiveBranch, 403, 'لا يوجد فرع رئيسي فعال مرتبط بحسابك.');
        return $primaryLocation;
    }

    private function deleteProductImage(Product $product): void
    {
        $image = trim((string) $product->image);

        if (
            $image === ''
            || str_starts_with($image, 'http://')
            || str_starts_with($image, 'https://')
            || str_starts_with($image, '//')
        ) {
            return;
        }

        if (Storage::disk('public')->exists($image)) {
            Storage::disk('public')->delete($image);
        }
    }
}
