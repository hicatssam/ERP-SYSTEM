<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\ScopesProcurementLocations;
use App\Http\Requests\Procurement\StoreGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Services\Procurement\GoodsReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    use ScopesProcurementLocations;

    public function __construct(
        private readonly GoodsReceiptService $receipts
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        $locationIds = $this->permittedLocationIds($request->user());

        $receipts = GoodsReceipt::query()
            ->with([
                'supplier',
                'purchaseOrder',
                'location',
                'currency',
                'receiver',
                'poster',
            ])
            ->withCount('items')
            ->whereIn('location_id', $locationIds)
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->input('status'))
            )
            ->when(
                $request->filled('purchase_order_id'),
                fn ($query) => $query->where(
                    'purchase_order_id',
                    $request->integer('purchase_order_id')
                )
            )
            ->latest('received_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view(
            'procurement.goods-receipts.index',
            compact('receipts')
        );
    }

    public function create(Request $request): View
    {
        $this->authorize('create', GoodsReceipt::class);

        $locationIds = $this->permittedLocationIds($request->user());

        $selectedOrder = null;

        if ($request->filled('purchase_order_id')) {
            $selectedOrder = PurchaseOrder::query()
                ->with([
                    'supplier',
                    'location',
                    'currency',
                    'items.product',
                ])
                ->whereIn('location_id', $locationIds)
                ->findOrFail($request->integer('purchase_order_id'));

            $this->authorize('view', $selectedOrder);
        }

        $orders = PurchaseOrder::query()
            ->with([
                'supplier',
                'location',
                'currency',
                'items.product',
            ])
            ->whereIn('location_id', $locationIds)
            ->whereIn('status', [
                'approved',
                'partially_received',
            ])
            ->latest('approved_at')
            ->get();

        return view(
            'procurement.goods-receipts.create',
            compact('orders', 'selectedOrder')
        );
    }

    public function store(
        StoreGoodsReceiptRequest $request
    ): RedirectResponse {
        $this->authorize('create', GoodsReceipt::class);

        $order = PurchaseOrder::query()
            ->findOrFail(
                $request->integer('purchase_order_id')
            );

        $this->ensurePermittedLocation(
            $request->user(),
            $order->location_id
        );

        $receipt = $this->receipts->create(
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('goods-receipts.show', $receipt)
            ->with(
                'success',
                'تم حفظ سند الاستلام كمسودة. يجب إدخال رقم الدفعة وتاريخ الإنتاج وتاريخ الانتهاء لكل كمية مقبولة قبل الترحيل إلى المخزون.'
            );
    }

    public function show(
        GoodsReceipt $goodsReceipt
    ): View {
        $this->authorize('view', $goodsReceipt);

        $goodsReceipt->load([
            'supplier',
            'purchaseOrder.items.product',
            'location',
            'currency',
            'receiver',
            'poster',
            'items.product',
            'items.purchaseOrderItem',
            'items.batch',
            'supplierInvoices',
            'purchaseReturns',
        ]);

        return view(
            'procurement.goods-receipts.show',
            ['goodsReceipt' => $goodsReceipt]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Post Goods Receipt — Sprint 14
    |--------------------------------------------------------------------------
    |
    | أي بند بكمية مقبولة سيدخل المخزون يجب أن يملك:
    | - رقم دفعة
    | - تاريخ إنتاج
    | - تاريخ انتهاء
    |
    | ويتم تفعيل tracks_batch و tracks_expiry تلقائياً على المنتج
    | قبل استدعاء GoodsReceiptService حتى يتم إنشاء InventoryBatch.
    |
    */

    public function post(
        Request $request,
        GoodsReceipt $goodsReceipt
    ): RedirectResponse {
        $this->authorize('post', $goodsReceipt);

        $goodsReceipt->load([
            'items.product',
        ]);

        $validated = $request->validate([
            'items' => [
                'nullable',
                'array',
            ],
            'items.*.batch_number' => [
                'nullable',
                'string',
                'max:100',
            ],
            'items.*.manufacturing_date' => [
                'nullable',
                'date',
            ],
            'items.*.expiry_date' => [
                'nullable',
                'date',
            ],
        ]);

        $submittedItems = $validated['items'] ?? [];

        foreach ($goodsReceipt->items as $item) {
            $submitted = $submittedItems[$item->id] ?? [];

            $batchNumber = trim(
                (string) (
                    $submitted['batch_number']
                    ?? $item->batch_number
                    ?? ''
                )
            );

            $manufacturingDate =
                $submitted['manufacturing_date']
                ?? $item->manufacturing_date;

            $expiryDate =
                $submitted['expiry_date']
                ?? $item->expiry_date;

            /*
             * البنود المرفوضة بالكامل لا تدخل المخزون،
             * لذلك لا نفرض عليها بيانات التتبع.
             */
            if ((float) $item->accepted_quantity <= 0) {
                $item->update([
                    'batch_number' =>
                        $batchNumber !== '' ? $batchNumber : null,
                    'manufacturing_date' =>
                        $manufacturingDate ?: null,
                    'expiry_date' =>
                        $expiryDate ?: null,
                ]);

                continue;
            }

            $productName =
                $item->product?->name_ar
                ?: $item->product?->name
                ?: ('#' . $item->product_id);

            if ($batchNumber === '') {
                throw ValidationException::withMessages([
                    "items.{$item->id}.batch_number" =>
                        "يجب إدخال رقم الدفعة للمنتج {$productName} قبل الترحيل إلى المخزون.",
                ]);
            }

            if (empty($manufacturingDate)) {
                throw ValidationException::withMessages([
                    "items.{$item->id}.manufacturing_date" =>
                        "يجب إدخال تاريخ الإنتاج للمنتج {$productName} قبل الترحيل إلى المخزون.",
                ]);
            }

            if (empty($expiryDate)) {
                throw ValidationException::withMessages([
                    "items.{$item->id}.expiry_date" =>
                        "يجب إدخال تاريخ الانتهاء للمنتج {$productName} قبل الترحيل إلى المخزون.",
                ]);
            }

            $manufacturing = Carbon::parse(
                $manufacturingDate
            )->startOfDay();

            $expiry = Carbon::parse(
                $expiryDate
            )->startOfDay();

            $today = Carbon::today();

            if ($manufacturing->gt($today)) {
                throw ValidationException::withMessages([
                    "items.{$item->id}.manufacturing_date" =>
                        "تاريخ إنتاج المنتج {$productName} لا يمكن أن يكون في المستقبل.",
                ]);
            }

            if ($expiry->lte($manufacturing)) {
                throw ValidationException::withMessages([
                    "items.{$item->id}.expiry_date" =>
                        "تاريخ انتهاء المنتج {$productName} يجب أن يكون بعد تاريخ الإنتاج.",
                ]);
            }

            if ($expiry->lte($today)) {
                throw ValidationException::withMessages([
                    "items.{$item->id}.expiry_date" =>
                        "لا يمكن ترحيل المنتج {$productName} إلى المخزون لأن تاريخ انتهاء صلاحيته منتهٍ أو هو تاريخ اليوم.",
                ]);
            }

            /*
             * مهم:
             * الخدمة الحالية تنشئ inventory_batches للمنتجات
             * التي tracks_batch = true، لذلك نضمن تفعيل الخيارين
             * لأي منتج فعلياً سيدخل المخزون.
             */
            if ($item->product) {
                if (
                    ! (bool) $item->product->tracks_batch
                    || ! (bool) $item->product->tracks_expiry
                ) {
                    $item->product->forceFill([
                        'tracks_batch' => true,
                        'tracks_expiry' => true,
                    ])->save();
                }
            }

            $item->update([
                'batch_number' => $batchNumber,
                'manufacturing_date' =>
                    $manufacturing->toDateString(),
                'expiry_date' =>
                    $expiry->toDateString(),
            ]);
        }

        $goodsReceipt = $this->receipts->post(
            $goodsReceipt->fresh([
                'items.product',
            ]),
            $request->user()
        );

        return redirect()
            ->route('goods-receipts.show', $goodsReceipt)
            ->with(
                'success',
                'تم ترحيل سند الاستلام إلى المخزون مع رقم الدفعة وتاريخ الإنتاج وتاريخ الانتهاء لكل منتج مقبول.'
            );
    }
}