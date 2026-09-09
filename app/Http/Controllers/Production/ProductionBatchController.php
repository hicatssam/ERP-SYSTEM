<?php

namespace App\Http\Controllers\Production;

use App\Enums\ProductionBatchStatus;
use App\Enums\RecipeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\FinishProductionBatchRequest;
use App\Http\Requests\Production\StoreProductionBatchRequest;
use App\Models\ProductionBatch;
use App\Models\Recipe;
use App\Services\Production\ProductionContextService;
use App\Services\Production\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionBatchController extends Controller
{
    public function __construct(
        private ProductionService $production,
        private ProductionContextService $context
    ) {
    }

    public function index(Request $request): View
    {
        $location =
            $this->context
                ->resolveLocation(
                    $request->user(),
                    $request->integer(
                        'location_id'
                    )
                    ?: null
                );

        $query =
            ProductionBatch::query()
                ->with([
                    'product',
                    'recipe',
                    'location',
                ])
                ->where(
                    'location_id',
                    $location->id
                );

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')
                    ->toString()
            );
        }

        return view(
            'production.batches.index',
            [
                'batches' =>
                    $query
                        ->latest('id')
                        ->paginate(25)
                        ->withQueryString(),

                'statuses' =>
                    ProductionBatchStatus::cases(),

                'locations' =>
                    $this->context
                        ->selectableLocations(
                            $request->user()
                        ),

                'selectedLocation' =>
                    $location,
            ]
        );
    }

    public function create(Request $request): View
    {
        $location =
            $this->context
                ->resolveLocation(
                    $request->user(),
                    $request->integer(
                        'location_id'
                    )
                    ?: null
                );

        return view(
            'production.batches.create',
            [
                'recipes' =>
                    Recipe::query()
                        ->where(
                            'status',
                            RecipeStatus::Approved->value
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->with('product')
                        ->orderBy('name')
                        ->get(),

                'locations' =>
                    $this->context
                        ->selectableLocations(
                            $request->user()
                        ),

                'selectedLocation' =>
                    $location,
            ]
        );
    }

    public function store(
        StoreProductionBatchRequest $request
    ): RedirectResponse {
        $data =
            $request->validated();

        $location =
            $this->context
                ->resolveLocation(
                    $request->user(),
                    (int)
                    $data['location_id']
                );

        $recipe =
            Recipe::query()
                ->findOrFail(
                    $data['recipe_id']
                );

        $batch =
            $this->production
                ->create(
                    $recipe,
                    $location->id,
                    (float)
                    $data[
                        'planned_output_quantity'
                    ],
                    $data[
                        'planned_date'
                    ]
                    ?? null,
                    $data['notes']
                    ?? null,
                    $request->user()
                );

        return redirect()
            ->route(
                'production.batches.show',
                $batch
            )
            ->with(
                'success',
                'تم إنشاء دفعة الإنتاج كمسودة.'
            );
    }

    public function show(
        Request $request,
        ProductionBatch $batch
    ): View {
        $this->context
            ->authorizeBatch(
                $request->user(),
                $batch
            );

        $batch->load([
            'product.unitDefinition',
            'recipe',
            'location',
            'items.ingredient',
            'items.allocations.inventoryBatch',
            'qualityCheck.items',
            'creator',
            'releaser',
            'starter',
            'completer',
        ]);

        return view(
            'production.batches.show',
            compact('batch')
        );
    }

    public function release(
        Request $request,
        ProductionBatch $batch
    ): RedirectResponse {
        $this->context
            ->authorizeBatch(
                $request->user(),
                $batch
            );

        $this->production
            ->release(
                $batch,
                $request->user()
            );

        return back()
            ->with(
                'success',
                'تم حجز المواد والإفراج عن الدفعة للإنتاج.'
            );
    }

    public function start(
        Request $request,
        ProductionBatch $batch
    ): RedirectResponse {
        $this->context
            ->authorizeBatch(
                $request->user(),
                $batch
            );

        $this->production
            ->start(
                $batch,
                $request->user()
            );

        return back()
            ->with(
                'success',
                'تم صرف المواد وبدء دفعة الإنتاج.'
            );
    }

    public function finish(
        FinishProductionBatchRequest $request,
        ProductionBatch $batch
    ): RedirectResponse {
        $this->context
            ->authorizeBatch(
                $request->user(),
                $batch
            );

        $data =
            $request->validated();

        $this->production
            ->finish(
                $batch,
                (float)
                $data[
                    'actual_output_quantity'
                ],
                $data[
                    'output_expiry_date'
                ]
                ?? null,
                $data['items'],
                $request->user()
            );

        return back()
            ->with(
                'success',
                $batch->quality_required
                    ? 'تم تسجيل الكميات وإرسال الدفعة لفحص الجودة.'
                    : 'تم إكمال الإنتاج وإضافة الناتج للمخزون.'
            );
    }

    public function cancel(
        Request $request,
        ProductionBatch $batch
    ): RedirectResponse {
        $this->context
            ->authorizeBatch(
                $request->user(),
                $batch
            );

        $data =
            $request->validate([
                'reason' => [
                    'required',
                    'string',
                    'min:3',
                    'max:2000',
                ],
            ]);

        $this->production
            ->cancel(
                $batch,
                $data['reason'],
                $request->user()
            );

        return back()
            ->with(
                'success',
                'تم إلغاء دفعة الإنتاج بأمان.'
            );
    }
}
