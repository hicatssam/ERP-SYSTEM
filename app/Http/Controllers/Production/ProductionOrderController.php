<?php

namespace App\Http\Controllers\Production;

use App\Enums\ProductionOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\CompleteProductionOrderRequest;
use App\Http\Requests\Production\StoreProductionOrderRequest;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Services\Production\ProductionContextService;
use App\Services\Production\ProductionOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionOrderController extends Controller
{
    public function __construct(
        private readonly ProductionOrderService $service,
        private readonly ProductionContextService $context,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $locationIds = $this->context->accessibleLocationIds($user);

        $query = ProductionOrder::query()
            ->with([
                'product',
                'recipe',
                'location',
            ])
            ->whereIn('location_id', $locationIds)
            ->latest('id');

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('location_id')) {
            $locationId = $request->integer('location_id');

            $this->context->assertLocationAccess(
                $user,
                $locationId
            );

            $query->where('location_id', $locationId);
        }

        if ($request->filled('q')) {
            $search = trim(
                $request->string('q')->toString()
            );

            $query->where(
                function ($filter) use ($search): void {
                    $filter
                        ->where(
                            'production_number',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'product',
                            fn ($product) =>
                                $product
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('name_ar', 'like', "%{$search}%")
                                    ->orWhere('sku', 'like', "%{$search}%")
                        );
                }
            );
        }

        return view('production.orders.index', [
            'orders' => $query
                ->paginate(30)
                ->withQueryString(),
            'locations' => $this->context->locations($user),
            'statuses' => ProductionOrderStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('production.orders.create', [
            'recipes' => Recipe::query()
                ->active()
                ->with([
                    'product.unitDefinition',
                    'outputUnit',
                ])
                ->orderBy('product_id')
                ->orderByDesc('version')
                ->get(),
            'locations' =>
                $this->context->locations(
                    $request->user()
                ),
        ]);
    }

    public function store(
        StoreProductionOrderRequest $request
    ): RedirectResponse {
        $order = $this->service->create(
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route(
                'production.orders.show',
                $order
            )
            ->with(
                'success',
                'تم إنشاء أمر الإنتاج كمسودة. يجب اعتماده قبل صرف المواد.'
            );
    }

    public function show(
        ProductionOrder $productionOrder,
        Request $request
    ): View {
        $this->context->assertLocationAccess(
            $request->user(),
            (int) $productionOrder->location_id
        );

        $productionOrder->load([
            'items.product.unitDefinition',
            'items.unit',
            'recipe.product',
            'product.unitDefinition',
            'outputUnit',
            'location',
            'creator.employee',
            'releaser.employee',
            'starter.employee',
            'completer.employee',
            'canceller.employee',
        ]);

        return view('production.orders.show', [
            'order' => $productionOrder,
        ]);
    }

    public function release(
        ProductionOrder $productionOrder,
        Request $request
    ): RedirectResponse {
        $this->context->assertLocationAccess(
            $request->user(),
            (int) $productionOrder->location_id
        );

        $this->service->release(
            $productionOrder,
            $request->user()
        );

        return back()->with(
            'success',
            'تم اعتماد أمر الإنتاج وتجميد نسخة المواد والتكلفة المعيارية.'
        );
    }

    public function start(
        ProductionOrder $productionOrder,
        Request $request
    ): RedirectResponse {
        $this->context->assertLocationAccess(
            $request->user(),
            (int) $productionOrder->location_id
        );

        $this->service->start(
            $productionOrder,
            $request->user()
        );

        return back()->with(
            'success',
            'بدأ الإنتاج وتم صرف المواد المعتمدة من المخزون.'
        );
    }

    public function complete(
        CompleteProductionOrderRequest $request,
        ProductionOrder $productionOrder
    ): RedirectResponse {
        $this->context->assertLocationAccess(
            $request->user(),
            (int) $productionOrder->location_id
        );

        $validated = $request->validated();
        $validated['items'] = collect($validated['materials'] ?? [])
            ->map(fn (array $item): array => [
                'id' => (int) $item['item_id'],
                'actual_quantity' => (float) $item['actual_quantity'],
                'waste_quantity' => (float) ($item['waste_quantity'] ?? 0),
            ])
            ->values()
            ->all();

        $this->service->submitCompletion(
            $productionOrder,
            $validated,
            $request->user()
        );

        return back()->with(
            'success',
            'تمت تسوية الاستهلاك الفعلي وإضافة الناتج النهائي للمخزون.'
        );
    }

    public function cancel(
        ProductionOrder $productionOrder,
        Request $request
    ): RedirectResponse {
        $this->context->assertLocationAccess(
            $request->user(),
            (int) $productionOrder->location_id
        );

        $this->service->cancel(
            $productionOrder,
            $request->user()
        );

        return back()->with(
            'success',
            'تم إلغاء أمر الإنتاج قبل بدء صرف المواد.'
        );
    }
}
