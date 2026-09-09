<?php

namespace App\Http\Controllers\Production;

use App\Enums\ProductionOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\ProductionOrder;
use App\Services\Production\ProductionContextService;
use App\Services\Production\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QualityControlController extends Controller
{
    public function __construct(
        private readonly ProductionService $production,
        private readonly ProductionContextService $context,
    ) {
    }

    public function index(Request $request): View
    {
        $orders = ProductionOrder::query()
            ->with(['location', 'product.unitDefinition'])
            ->whereIn('location_id', $this->context->locationIdsFor($request->user()))
            ->where('status', ProductionOrderStatus::AwaitingQuality->value)
            ->oldest('submitted_quality_at')
            ->paginate(30)
            ->withQueryString();

        return view('production.quality.index', compact('orders'));
    }

    public function show(Request $request, ProductionOrder $productionOrder): View
    {
        $this->context->assertCanAccessLocation(
            $request->user(),
            (int) $productionOrder->location_id
        );

        abort_unless(
            $productionOrder->statusValue() === ProductionOrderStatus::AwaitingQuality->value,
            422,
            'أمر الإنتاج ليس بانتظار فحص الجودة.'
        );

        $productionOrder->load([
            'location',
            'product.unitDefinition',
            'recipe',
            'items.product.unitDefinition',
        ]);

        return view('production.quality.show', [
            'order' => $productionOrder,
        ]);
    }

    public function approve(Request $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $this->context->assertCanAccessLocation($request->user(), (int) $productionOrder->location_id);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
            'measurements' => ['nullable', 'array'],
            'measurements.*' => ['nullable', 'string', 'max:255'],
        ]);

        $this->production->approveQuality($productionOrder, $data, $request->user());

        return redirect()
            ->route('production.orders.show', $productionOrder)
            ->with('success', 'تم اعتماد الجودة وإدخال الناتج النهائي إلى المخزون.');
    }

    public function reject(Request $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $this->context->assertCanAccessLocation($request->user(), (int) $productionOrder->location_id);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'measurements' => ['nullable', 'array'],
            'measurements.*' => ['nullable', 'string', 'max:255'],
        ]);

        $this->production->rejectQuality($productionOrder, $data, $request->user());

        return redirect()
            ->route('production.orders.show', $productionOrder)
            ->with('success', 'تم رفض الناتج في الجودة ولم تتم إضافته إلى المخزون.');
    }
}
