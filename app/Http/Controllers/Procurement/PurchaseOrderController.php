<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\ScopesProcurementLocations;
use App\Http\Requests\Procurement\StorePurchaseOrderRequest;
use App\Http\Requests\Procurement\UpdatePurchaseOrderRequest;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Procurement\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    use ScopesProcurementLocations;

    public function __construct(
        private readonly PurchaseOrderService $orders
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $user = $request->user();

        $locationIds = $this->permittedLocationIds($user);

        $orders = PurchaseOrder::query()
            ->with([
                'supplier',
                'location',
                'currency',
                'creator',
            ])
            ->withCount('items')
            ->whereIn('location_id', $locationIds)

            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->input('status')
                )
            )

            ->when(
                $request->filled('supplier_id'),
                fn ($query) => $query->where(
                    'supplier_id',
                    $request->integer('supplier_id')
                )
            )

            ->when(
                $request->filled('location_id'),
                function ($query) use ($request, $locationIds): void {

                    $locationId =
                        $request->integer('location_id');

                    abort_unless(
                        $locationIds->contains($locationId),
                        403
                    );

                    $query->where(
                        'location_id',
                        $locationId
                    );
                }
            )

            ->latest('order_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view(
            'procurement.purchase-orders.index',
            [
                'orders' => $orders,

                'locations' =>
                    $this->permittedLocations($user),

                'suppliers' => Supplier::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'supplier_code',
                    ]),
            ]
        );
    }

    public function create(Request $request): View
    {
        $this->authorize(
            'create',
            PurchaseOrder::class
        );

        return view(
            'procurement.purchase-orders.create',
            $this->formData($request)
        );
    }

    public function store(
        StorePurchaseOrderRequest $request
    ): RedirectResponse {

        $this->authorize(
            'create',
            PurchaseOrder::class
        );

        $this->ensurePermittedLocation(
            $request->user(),
            $request->integer('location_id')
        );

        $order = $this->orders->create(
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route(
                'purchase-orders.show',
                $order
            )
            ->with(
                'success',
                'تم إنشاء أمر الشراء كمسودة. أرسله للاعتماد بعد المراجعة.'
            );
    }

    public function show(
        PurchaseOrder $purchaseOrder
    ): View {

        $this->authorize(
            'view',
            $purchaseOrder
        );

        return view(
            'procurement.purchase-orders.show',
            [
                'purchaseOrder' =>
                    $purchaseOrder->load([
                        'supplier',
                        'location',
                        'currency',
                        'creator',
                        'approver',
                        'canceller',
                        'items.product',
                        'goodsReceipts.items',
                        'supplierInvoices',
                    ]),
            ]
        );
    }

    public function edit(
        Request $request,
        PurchaseOrder $purchaseOrder
    ): View {

        $this->authorize(
            'update',
            $purchaseOrder
        );

        $purchaseOrder->load([
            'items.product',
        ]);

        return view(
            'procurement.purchase-orders.edit',
            [
                'purchaseOrder' => $purchaseOrder,

                ...$this->formData(
                    $request,
                    $purchaseOrder
                ),
            ]
        );
    }

    public function update(
        UpdatePurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder
    ): RedirectResponse {

        $this->authorize(
            'update',
            $purchaseOrder
        );

        $this->ensurePermittedLocation(
            $request->user(),
            $request->integer('location_id')
        );

        $purchaseOrder =
            $this->orders->update(
                $purchaseOrder,
                $request->validated(),
                $request->user()
            );

        return redirect()
            ->route(
                'purchase-orders.show',
                $purchaseOrder
            )
            ->with(
                'success',
                'تم تحديث أمر الشراء.'
            );
    }

    public function submit(
        Request $request,
        PurchaseOrder $purchaseOrder
    ): RedirectResponse {

        $this->authorize(
            'submit',
            $purchaseOrder
        );

        $purchaseOrder =
            $this->orders->submit(
                $purchaseOrder,
                $request->user()
            );

        return redirect()
            ->route(
                'purchase-orders.show',
                $purchaseOrder
            )
            ->with(
                'success',
                'تم إرسال أمر الشراء للاعتماد.'
            );
    }

    public function approve(
        Request $request,
        PurchaseOrder $purchaseOrder
    ): RedirectResponse {

        $this->authorize(
            'approve',
            $purchaseOrder
        );

        $purchaseOrder =
            $this->orders->approve(
                $purchaseOrder,
                $request->user()
            );

        return redirect()
            ->route(
                'purchase-orders.show',
                $purchaseOrder
            )
            ->with(
                'success',
                'تم اعتماد أمر الشراء وهو جاهز للاستلام.'
            );
    }

    public function cancel(
        Request $request,
        PurchaseOrder $purchaseOrder
    ): RedirectResponse {

        $this->authorize(
            'cancel',
            $purchaseOrder
        );

        $validated = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $purchaseOrder =
            $this->orders->cancel(
                $purchaseOrder,
                $request->user(),
                $validated['reason'] ?? null
            );

        return redirect()
            ->route(
                'purchase-orders.show',
                $purchaseOrder
            )
            ->with(
                'success',
                'تم إلغاء أمر الشراء.'
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(
        Request $request,
        ?PurchaseOrder $purchaseOrder = null
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Supplier Products
        |--------------------------------------------------------------------------
        |
        | هنا المفتاح الأساسي للتعديل.
        |
        | لا نرسل جميع products إلى شاشة أمر الشراء.
        | نرسل فقط العلاقات الموجودة فعلياً في supplier_products.
        |
        */

        $supplierProducts = SupplierProduct::query()
            ->with([
                'product',
                'currency',
                'purchaseUnit',
            ])
            ->where('is_active', true)

            ->whereHas(
                'product',
                fn ($query) =>
                    $query->where(
                        'is_active',
                        true
                    )
            )

            ->orderBy('supplier_id')
            ->orderByDesc('is_preferred')
            ->orderBy('product_id')
            ->get()

            ->map(function (
                SupplierProduct $supplierProduct
            ): array {

                $product =
                    $supplierProduct->product;

                $currency =
                    $supplierProduct->currency;

                return [
                    'id' =>
                        $supplierProduct->id,

                    'supplier_id' =>
                        $supplierProduct->supplier_id,

                    'product_id' =>
                        $supplierProduct->product_id,

                    'product_name' =>
                        $product?->name_ar
                        ?: $product?->name
                        ?: 'منتج',

                    'product_sku' =>
                        $product?->sku,

                    'supplier_sku' =>
                        $supplierProduct->supplier_sku,

                    'supplier_product_name' =>
                        $supplierProduct
                            ->supplier_product_name,

                    'purchase_price' =>
                        (float) $supplierProduct
                            ->purchase_price,

                    'currency_id' =>
                        $supplierProduct->currency_id,

                    'currency_code' =>
                        $currency?->code,

                    'minimum_order_quantity' =>
                        (float) $supplierProduct
                            ->minimum_order_quantity,

                    'purchase_unit_id' =>
                        $supplierProduct->purchase_unit_id,

                    'purchase_unit_name' =>
                        $supplierProduct->purchaseUnit?->displayName(),

                    'conversion_factor' =>
                        (float) $supplierProduct->conversion_factor,

                    'package_description' =>
                        $supplierProduct->package_description,

                    'lead_time_days' =>
                        $supplierProduct
                            ->lead_time_days !== null
                            ? (int) $supplierProduct
                                ->lead_time_days
                            : null,

                    'is_preferred' =>
                        (bool) $supplierProduct
                            ->is_preferred,
                ];
            })
            ->values();

        return [
            'locations' =>
                $this->permittedLocations(
                    $request->user()
                ),

            'suppliers' => Supplier::query()
                ->active()
                ->orderBy('name')
                ->get([
                    'id',
                    'supplier_code',
                    'name',
                    'currency_id',
                ]),

            'currencies' => Currency::query()
                ->active()
                ->orderBy('code')
                ->get(),

            /*
             * بدل products كاملة.
             */
            'supplierProducts' =>
                $supplierProducts,
        ];
    }
}
