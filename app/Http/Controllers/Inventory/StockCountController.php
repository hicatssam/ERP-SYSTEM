<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\StockCountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockCountRequest;
use App\Http\Requests\Inventory\UpdateStockCountRequest;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Services\ActivityLogger;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockCountController extends Controller
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    public function index(): View
    {
        $user = Auth::user();
        $this->authorize('viewAny', StockCount::class);
        $query = StockCount::query()->with(['location', 'creator']);

        if (! $user->isAdmin()) {
            $query->where('location_id', $user->primaryLocation()?->id);
        }

        return view('inventory.stock-counts.index', ['stockCounts' => $query->latest()->paginate(20)]);
    }

    public function create(): View
    {
        $this->authorize('create', StockCount::class);
        $user = Auth::user();
        $locations = $user->isAdmin()
            ? Location::query()->active()->orderBy('name')->get()
            : collect([$user->primaryLocation()])->filter();

        return view('inventory.stock-counts.create', compact('locations'));
    }

    public function store(StoreStockCountRequest $request): RedirectResponse
    {
        $this->authorize('create', StockCount::class);
        $user = $request->user();
        $locationId = $request->integer('location_id');
        abort_unless($user->isAdmin() || $user->primaryLocation()?->id === $locationId, 403);

        $stockCount = DB::transaction(function () use ($request, $user, $locationId) {
            $stockCount = StockCount::query()->create([
                'location_id' => $locationId,
                'status' => StockCountStatus::InProgress,
                'created_by' => $user->id,
                'notes' => $request->validated('notes'),
            ]);

            $inventories = Inventory::query()
                ->where('location_id', $locationId)
                ->get()
                ->keyBy('product_id');
            $productIds = LocationProduct::query()
                ->where('location_id', $locationId)
                ->pluck('product_id')
                ->merge($inventories->keys())
                ->unique()
                ->values();

            foreach ($productIds as $productId) {
                $stockCount->items()->create([
                    'product_id' => $productId,
                    'system_quantity' => $inventories->get($productId)?->quantity ?? 0,
                ]);
            }

            ActivityLogger::log(
                userId: $user->id,
                action: 'stock_count.created',
                module: 'inventory',
                recordType: 'stock_counts',
                recordId: $stockCount->id,
                oldValues: null,
                newValues: ['location_id' => $stockCount->location_id, 'status' => StockCountStatus::InProgress->value],
                metadata: ['items_prepopulated' => $productIds->count()],
            );

            return $stockCount;
        });

        return redirect()->route('stock-counts.show', $stockCount)
            ->with('success', 'تم بدء جرد المخزون.');
    }

    public function show(StockCount $stockCount): View
    {
        $this->authorize('view', $stockCount);

        return view('inventory.stock-counts.show', [
            'stockCount' => $stockCount->load(['location', 'items.product.category', 'creator', 'approvedBy']),
        ]);
    }

    public function edit(StockCount $stockCount): View|RedirectResponse
    {
        $this->authorize('update', $stockCount);

        if ($this->statusValue($stockCount) !== StockCountStatus::InProgress->value) {
            return back()->with('error', 'لا يمكن تعديل هذا الجرد.');
        }

        return view('inventory.stock-counts.edit', [
            'stockCount' => $stockCount->load(['location', 'items.product']),
        ]);
    }

    public function update(UpdateStockCountRequest $request, StockCount $stockCount): RedirectResponse
    {
        $this->authorize('update', $stockCount);

        if ($this->statusValue($stockCount) !== StockCountStatus::InProgress->value) {
            return back()->with('error', 'لا يمكن تعديل هذا الجرد.');
        }

        DB::transaction(function () use ($request, $stockCount) {
            foreach ($request->validated('items', []) as $itemId => $data) {
                StockCountItem::query()
                    ->whereKey($itemId)
                    ->where('stock_count_id', $stockCount->id)
                    ->update(['actual_quantity' => $data['actual_quantity'] ?? null]);
            }
        });

        return back()->with('success', 'تم حفظ الكميات الفعلية.');
    }

    public function approve(Request $request, StockCount $stockCount): RedirectResponse
    {
        $this->authorize('approve', $stockCount);

        DB::transaction(function () use ($stockCount, $request) {
            $stockCount = StockCount::query()->with('items')->lockForUpdate()->findOrFail($stockCount->id);
            if ($this->statusValue($stockCount) !== StockCountStatus::InProgress->value) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن اعتماد هذا الجرد.',
                ]);
            }

            if ($stockCount->items->contains(fn (StockCountItem $item): bool => $item->actual_quantity === null)) {
                throw ValidationException::withMessages([
                    'items' => 'أدخل الكمية الفعلية لكل الأصناف قبل اعتماد الجرد.',
                ]);
            }

            $adjustments = 0;
            foreach ($stockCount->items as $item) {
                if ((float) $item->actual_quantity === (float) $item->system_quantity) {
                    continue;
                }

                $this->inventory->setOnHandQuantity(
                    locationId: $stockCount->location_id,
                    productId: $item->product_id,
                    targetQuantity: (float) $item->actual_quantity,
                    userId: $request->user()->id,
                    referenceType: 'stock_counts',
                    referenceId: $stockCount->id,
                    idempotencyKey: "stock-count:{$stockCount->id}:{$item->id}",
                    note: "اعتماد جرد رقم {$stockCount->id}",
                );
                $adjustments++;
            }

            $stockCount->update([
                'status' => StockCountStatus::Approved,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            ActivityLogger::log(
                userId: $request->user()->id,
                action: 'stock_count.approved',
                module: 'inventory',
                recordType: 'stock_counts',
                recordId: $stockCount->id,
                oldValues: ['status' => StockCountStatus::InProgress->value],
                newValues: ['status' => StockCountStatus::Approved->value],
                metadata: ['location_id' => $stockCount->location_id, 'adjustments_made' => $adjustments],
            );
        });

        return redirect()->route('stock-counts.show', $stockCount)
            ->with('success', 'تم اعتماد الجرد وتسجيل كل فروق المخزون في السجل.');
    }

    public function destroy(StockCount $stockCount): RedirectResponse
    {
        $this->authorize('delete', $stockCount);

        if ($this->statusValue($stockCount) === StockCountStatus::Approved->value) {
            return back()->with('error', 'لا يمكن حذف جرد معتمد.');
        }

        $stockCount->delete();

        return redirect()->route('stock-counts.index')->with('success', 'تم حذف مسودة الجرد.');
    }

    private function statusValue(StockCount $stockCount): string
    {
        return $stockCount->status instanceof \BackedEnum
            ? $stockCount->status->value
            : (string) $stockCount->status;
    }
}
