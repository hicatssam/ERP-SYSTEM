<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ReceiveStockTransferRequest;
use App\Models\StockTransfer;
use App\Services\Inventory\InternalTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function __construct(private readonly InternalTransferService $transfers)
    {
    }

    public function index(): View
    {
        $user = Auth::user();
        $this->authorize('viewAny', StockTransfer::class);

        $query = StockTransfer::query()->with(['fromLocation', 'toLocation', 'stockRequest']);
        if (! $user->isAdmin()) {
            $locationId = $user->primaryLocation()?->id;
            $query->where(fn ($nested) => $nested
                ->where('from_location_id', $locationId)
                ->orWhere('to_location_id', $locationId));
        }

        return view('inventory.stock-transfers.index', [
            'transfers' => $query->latest()->paginate(20),
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('stock-requests.index')
            ->with('info', 'تُنشأ التحويلات تلقائياً بعد اعتماد طلب المخزون.');
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->create();
    }

    public function show(StockTransfer $stockTransfer): View
    {
        $this->authorize('view', $stockTransfer);

        return view('inventory.stock-transfers.show', [
            'stockTransfer' => $stockTransfer->load([
                'fromLocation', 'toLocation', 'items.product', 'items.currency',
                'discrepancies.product', 'stockRequest', 'dispatcher', 'receiver',
            ]),
        ]);
    }

    public function edit(StockTransfer $stockTransfer): View
    {
        return $this->show($stockTransfer);
    }

    public function update(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        return redirect()->route('stock-transfers.show', $stockTransfer)
            ->with('info', 'استخدم إجراءات الإرسال أو الاستلام فقط؛ سجلات التحويل غير قابلة للتعديل المباشر.');
    }

    public function destroy(StockTransfer $stockTransfer): RedirectResponse
    {
        return redirect()->route('stock-transfers.show', $stockTransfer)
            ->with('error', 'لا يمكن حذف سجلات التحويل حفاظاً على أثر المخزون.');
    }

    public function dispatch(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->authorize('dispatch', $stockTransfer);
        $validated = $request->validate(['dispatch_notes' => ['nullable', 'string', 'max:2000']]);
        $stockTransfer = $this->transfers->dispatch(
            $stockTransfer,
            $request->user(),
            $validated['dispatch_notes'] ?? null,
        );

        return redirect()->route('stock-transfers.show', $stockTransfer)
            ->with('success', 'تم إرسال التحويل وتسجيله قيد النقل.');
    }

    public function receive(ReceiveStockTransferRequest $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->authorize('receive', $stockTransfer);
        $invoice = $this->transfers->receive(
            $stockTransfer,
            $request->validated('items'),
            $request->user(),
            $request->validated('receiving_notes'),
        );

        return redirect()
            ->route('stock-receiving-invoices.show', $invoice)
            ->with('success', 'تم استلام التحويل وتسجيله في مخزون الموقع وسند الاستلام الداخلي.');
    }
}
