<?php

namespace App\Http\Controllers\Production;

use App\Enums\RecipeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\StoreRecipeRequest;
use App\Http\Requests\Production\UpdateRecipeRequest;
use App\Models\Product;
use App\Models\Recipe;
use App\Services\Production\ProductionContextService;
use App\Services\Production\RecipeCostService;
use App\Services\Production\RecipeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function __construct(
        private RecipeService $recipes,
        private RecipeCostService $costs,
        private ProductionContextService $context
    ) {
    }

    public function index(Request $request): View
    {
        $query = Recipe::query()
            ->with([
                'product',
                'productVariant',
                'approver',
            ]);

        if ($request->filled('q')) {
            $search = trim(
                (string) $request->input('q')
            );

            $query->where(
                function ($builder) use ($search): void {
                    $builder
                        ->where(
                            'code',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'product',
                            fn ($product) =>
                                $product
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'name_ar',
                                        'like',
                                        "%{$search}%"
                                    )
                        );
                }
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')
                    ->toString()
            );
        }

        return view(
            'production.recipes.index',
            [
                'recipes' =>
                    $query
                        ->latest('id')
                        ->paginate(25)
                        ->withQueryString(),

                'statuses' =>
                    RecipeStatus::cases(),
            ]
        );
    }

    public function create(): View
    {
        return view(
            'production.recipes.form',
            [
                'recipe' =>
                    null,

                'products' =>
                    Product::query()
                        ->active()
                        ->with([
                            'unitDefinition',
                            'activeVariants.size',
                            'activeVariants.attributeValues.attribute',
                        ])
                        ->orderByRaw(
                            'COALESCE(name_ar, name)'
                        )
                        ->get(),
            ]
        );
    }

    public function store(
        StoreRecipeRequest $request
    ): RedirectResponse {
        $recipe =
            $this->recipes
                ->create(
                    $request->validated(),
                    $request->user()
                );

        return redirect()
            ->route(
                'production.recipes.show',
                $recipe
            )
            ->with(
                'success',
                'تم إنشاء مسودة الوصفة بنجاح.'
            );
    }

    public function show(
        Request $request,
        Recipe $recipe
    ): View {
        $recipe->load([
            'product.unitDefinition',
            'productVariant.size',
            'productVariant.attributeValues.attribute',
            'items.ingredient.unitDefinition',
            'creator',
            'approver',
        ]);

        $locations =
            collect();

        $selectedLocation =
            null;

        $estimate =
            null;

        if (
            $request->user()
                ->can('recipes.cost.view')
        ) {
            $locations =
                $this->context
                    ->selectableLocations(
                        $request->user()
                    );

            if ($locations->isNotEmpty()) {
                $selectedLocation =
                    $this->context
                        ->resolveLocation(
                            $request->user(),
                            $request->integer(
                                'location_id'
                            )
                            ?: null
                        );

                $estimate =
                    $this->costs
                        ->estimate(
                            $recipe,
                            $selectedLocation->id
                        );
            }
        }

        return view(
            'production.recipes.show',
            compact(
                'recipe',
                'locations',
                'selectedLocation',
                'estimate'
            )
        );
    }

    public function edit(
        Recipe $recipe
    ): View {
        abort_unless(
            $recipe->isEditable(),
            422,
            'الوصفة المعتمدة لا تعدّل مباشرة. أنشئ إصدارًا جديدًا.'
        );

        $recipe->load(
            'items'
        );

        return view(
            'production.recipes.form',
            [
                'recipe' =>
                    $recipe,

                'products' =>
                    Product::query()
                        ->active()
                        ->with([
                            'unitDefinition',
                            'activeVariants.size',
                            'activeVariants.attributeValues.attribute',
                        ])
                        ->orderByRaw(
                            'COALESCE(name_ar, name)'
                        )
                        ->get(),
            ]
        );
    }

    public function update(
        UpdateRecipeRequest $request,
        Recipe $recipe
    ): RedirectResponse {
        $recipe =
            $this->recipes
                ->updateDraft(
                    $recipe,
                    $request->validated(),
                    $request->user()
                );

        return redirect()
            ->route(
                'production.recipes.show',
                $recipe
            )
            ->with(
                'success',
                'تم تحديث مسودة الوصفة.'
            );
    }

    public function approve(
        Request $request,
        Recipe $recipe
    ): RedirectResponse {
        abort_unless(
            $request->user()
                ->can('recipes.approve'),
            403
        );

        $this->recipes
            ->approve(
                $recipe,
                $request->user()
            );

        return back()
            ->with(
                'success',
                'تم اعتماد الوصفة وأصبحت الإصدار الفعال للمنتج.'
            );
    }

    public function newVersion(
        Request $request,
        Recipe $recipe
    ): RedirectResponse {
        abort_unless(
            $request->user()
                ->can('recipes.manage'),
            403
        );

        $copy =
            $this->recipes
                ->createNewVersion(
                    $recipe,
                    $request->user()
                );

        return redirect()
            ->route(
                'production.recipes.edit',
                $copy
            )
            ->with(
                'success',
                'تم إنشاء إصدار جديد كمسودة مع نسخ المكونات.'
            );
    }

    public function archive(
        Request $request,
        Recipe $recipe
    ): RedirectResponse {
        abort_unless(
            $request->user()
                ->can('recipes.approve'),
            403
        );

        $this->recipes
            ->archive(
                $recipe,
                $request->user()
            );

        return back()
            ->with(
                'success',
                'تم أرشفة الوصفة.'
            );
    }

    public function cost(
        Request $request,
        Recipe $recipe
    ): View {
        return $this->show($request, $recipe);
    }
}
