<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Support\MenuIconLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Category::query();

        if ($user->isAdmin()) {
            $query->withCount('products');
        } else {
            $location = $this->managedBranch($user);

            $query
                ->whereHas(
                    'products',
                    fn (Builder $products) => $this
                        ->constrainProductsToLocation(
                            $products,
                            (int) $location->id
                        )
                )
                ->withCount([
                    'products' => fn (Builder $products) => $this
                        ->constrainProductsToLocation(
                            $products,
                            (int) $location->id
                        ),
                ]);
        }

        $categories = $query
            ->orderBy('sort_order')
            ->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create(Request $request)
    {
        $this->ensureUserHasBranchWhenRequired($request->user());

        return view('admin.categories.create');
    }

    public function store(Request $request)
    {
        $this->ensureUserHasBranchWhenRequired($request->user());

        $data = $this->validatedData($request);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')
                ->store('categories', 'public');
        }

        $data['slug'] = Str::slug($data['name']);

        /*
         * Do not require Category::$fillable to be edited for the new
         * UI-only icon fields. Keep the existing business model safe.
         */
        $iconPayload = [
            'icon_key' => $data['icon_key'] ?? 'sparkles',
            'icon_color' => $data['icon_color'] ?? '#111111',
        ];

        unset($data['icon_key'], $data['icon_color']);

        $category = Category::create($data);

        $category->forceFill($iconPayload)->save();

        return redirect()
            ->route('categories.index')
            ->with('success', 'تم إنشاء الفئة بنجاح.');
    }

    public function show(Request $request, Category $category)
    {
        $user = $request->user();
        $this->ensureCategoryAccess($category, $user);

        if ($user->isAdmin()) {
            $category->load('products');
        } else {
            $location = $this->managedBranch($user);

            $category->load([
                'products' => function (Relation $products) use ($location) {
                    $this->constrainProductsToLocation(
                        $products,
                        (int) $location->id
                    )->with([
                        'locationProducts' => fn (Relation $locationProducts) =>
                            $this->constrainLocationProducts(
                                $locationProducts,
                                (int) $location->id
                            ),
                    ]);
                },
            ]);
        }

        return view('admin.categories.show', compact('category'));
    }

    public function edit(Request $request, Category $category)
    {
        $this->ensureCategoryAccess($category, $request->user());

        return view('admin.categories.edit', compact('category'));
    }

    public function update(
        Request $request,
        Category $category
    ) {
        $this->ensureCategoryAccess($category, $request->user());

        $data = $this->validatedData($request);

        if ($request->hasFile('image')) {
            $this->deleteCategoryImage($category);

            $data['image'] = $request->file('image')
                ->store('categories', 'public');
        } elseif ($request->boolean('remove_image')) {
            $this->deleteCategoryImage($category);
            $data['image'] = null;
        } else {
            unset($data['image']);
        }

        $data['slug'] = Str::slug($data['name']);

        $iconPayload = [
            'icon_key' => $data['icon_key'] ?? 'sparkles',
            'icon_color' => $data['icon_color'] ?? '#111111',
        ];

        unset($data['icon_key'], $data['icon_color']);

        $category->update($data);
        $category->forceFill($iconPayload)->save();

        return redirect()
            ->route('categories.index')
            ->with('success', 'تم تحديث الفئة بنجاح.');
    }

    public function destroy(Request $request, Category $category)
    {
        $this->ensureCategoryAccess($category, $request->user());

        if ($category->products()->exists()) {
            return back()->with(
                'error',
                'لا يمكن حذف الفئة لأنها تحتوي على منتجات.'
            );
        }

        $this->deleteCategoryImage($category);
        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('success', 'تم حذف الفئة.');
    }

    public function toggleStatus(
        Request $request,
        Category $category
    ) {
        $this->ensureCategoryAccess($category, $request->user());

        $category->update([
            'is_active' => ! $category->is_active,
        ]);

        return back()->with('success', 'تم تحديث حالة الفئة.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            'icon_key' => [
                'nullable',
                'string',
                Rule::in(MenuIconLibrary::keys()),
            ],

            'icon_color' => [
                'nullable',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpeg,png,webp',
                'max:2048',
            ],
        ]);
    }

    private function constrainProductsToLocation(
        Builder|Relation $products,
        int $locationId
    ): Builder|Relation {
        return $products->whereHas(
            'locationProducts',
            fn (Builder $locationProducts) => $this
                ->constrainLocationProducts(
                    $locationProducts,
                    $locationId
                )
        );
    }

    private function constrainLocationProducts(
        Builder|Relation $locationProducts,
        int $locationId
    ): Builder|Relation {
        return $locationProducts
            ->where('location_id', $locationId)
            ->where('is_available', true);
    }

    private function ensureCategoryAccess(
        Category $category,
        User $user
    ): void {
        if ($user->isAdmin()) {
            return;
        }

        $location = $this->managedBranch($user);

        $canAccess = $category->products()
            ->whereHas(
                'locationProducts',
                fn (Builder $locationProducts) => $this
                    ->constrainLocationProducts(
                        $locationProducts,
                        (int) $location->id
                    )
            )
            ->exists();

        abort_unless(
            $canAccess,
            403,
            'لا يمكنك الوصول إلى فئة لا تخص فرعك.'
        );
    }

    private function ensureUserHasBranchWhenRequired(User $user): void
    {
        if (! $user->isAdmin()) {
            $this->managedBranch($user);
        }
    }

    private function managedBranch(User $user): Location
    {
        $primaryLocation = $user->primaryLocation();

        $isActiveBranch = $primaryLocation
            && $primaryLocation->isBranch()
            && Location::active()
                ->whereKey($primaryLocation->id)
                ->exists();

        abort_unless(
            $isActiveBranch,
            403,
            'لا يوجد فرع رئيسي فعال مرتبط بحسابك.'
        );

        return $primaryLocation;
    }

    private function deleteCategoryImage(Category $category): void
    {
        if (
            $category->image
            && Storage::disk('public')->exists($category->image)
        ) {
            Storage::disk('public')->delete($category->image);
        }
    }
}
