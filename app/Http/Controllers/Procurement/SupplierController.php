<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\ScopesProcurementLocations;
use App\Http\Requests\Procurement\StoreSupplierRequest;
use App\Http\Requests\Procurement\UpdateSupplierRequest;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\Procurement\SupplierBalanceService;
use App\Services\Procurement\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    use ScopesProcurementLocations;

    public function __construct(
        private readonly SupplierService $suppliers,
        private readonly SupplierBalanceService $balances,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = Supplier::query()
            ->with('currency')
            ->withCount([
                'contacts',
                'supplierProducts',
                'purchaseOrders',
            ])
            ->when(
                $request->filled('status'),
                fn ($query) =>
                    $query->where(
                        'status',
                        $request->input('status')
                    )
            )
            ->when(
                $request->filled('search'),
                function ($query) use ($request): void {
                    $search = trim(
                        (string) $request->input('search')
                    );

                    $query->where(
                        function ($nested) use ($search): void {
                            $nested
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'supplier_code',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'company_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'phone',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view(
            'procurement.suppliers.index',
            compact('suppliers')
        );
    }

    public function create(): View
    {
        $this->authorize(
            'create',
            Supplier::class
        );

        return view(
            'procurement.suppliers.create',
            [
                'currencies' =>
                    Currency::query()
                        ->active()
                        ->orderBy('code')
                        ->get(),

                'products' =>
                    Product::query()
                        ->active()
                        ->orderBy('name_ar')
                        ->orderBy('name')
                        ->get(),

                'units' => Unit::query()
                    ->active()
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(),
            ]
        );
    }

    public function store(
        StoreSupplierRequest $request
    ): RedirectResponse {
        $this->authorize(
            'create',
            Supplier::class
        );

        $supplier = $this->suppliers->create(
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route(
                'suppliers.show',
                $supplier
            )
            ->with(
                'success',
                'تم إنشاء المورد وبياناته بنجاح.'
            );
    }

    public function show(
        Request $request,
        Supplier $supplier
    ): View {
        $this->authorize(
            'view',
            $supplier
        );

        $currencyId =
            $request->integer('currency_id')
            ?: $supplier->currency_id;

        $locationIds =
            $this->permittedLocationIds(
                $request->user()
            );

        $includeOpeningBalance =
            $request->user()->isAdmin();

        $supplier->load([
            'currency',
            'contacts',
            'supplierProducts.product.unitDefinition',
            'supplierProducts.currency',
            'supplierProducts.purchaseUnit',

            'purchaseOrders' =>
                fn ($query) =>
                    $query
                        ->whereIn(
                            'location_id',
                            $locationIds
                        )
                        ->latest('order_date')
                        ->limit(10),

            'goodsReceipts' =>
                fn ($query) =>
                    $query
                        ->whereIn(
                            'location_id',
                            $locationIds
                        )
                        ->latest('received_at')
                        ->limit(10),

            'invoices' =>
                fn ($query) =>
                    $query
                        ->whereIn(
                            'location_id',
                            $locationIds
                        )
                        ->latest('invoice_date')
                        ->limit(10),
        ]);

        return view(
            'procurement.suppliers.show',
            [
                'supplier' => $supplier,

                'summary' =>
                    $this->balances->summary(
                        $supplier,
                        $currencyId,
                        $locationIds,
                        $includeOpeningBalance,
                    ),

                'currencies' =>
                    Currency::query()
                        ->active()
                        ->orderBy('code')
                        ->get(),

                'selectedCurrencyId' =>
                    $currencyId,
            ]
        );
    }

    public function edit(
        Supplier $supplier
    ): View {
        $this->authorize(
            'update',
            $supplier
        );

        $supplier->load([
            'contacts',
            'supplierProducts.product.unitDefinition',
            'supplierProducts.currency',
            'supplierProducts.purchaseUnit',
        ]);

        return view(
            'procurement.suppliers.edit',
            [
                'supplier' =>
                    $supplier,

                'currencies' =>
                    Currency::query()
                        ->active()
                        ->orderBy('code')
                        ->get(),

                'products' =>
                    Product::query()
                        ->active()
                        ->orderBy('name_ar')
                        ->orderBy('name')
                        ->get(),

                'units' => Unit::query()
                    ->active()
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(),
            ]
        );
    }

    public function update(
        UpdateSupplierRequest $request,
        Supplier $supplier
    ): RedirectResponse {
        $this->authorize(
            'update',
            $supplier
        );

        $supplier =
            $this->suppliers->update(
                $supplier,
                $request->validated(),
                $request->user()
            );

        return redirect()
            ->route(
                'suppliers.show',
                $supplier
            )
            ->with(
                'success',
                'تم تحديث بيانات المورد بنجاح.'
            );
    }

    public function destroy(
        Request $request,
        Supplier $supplier
    ): RedirectResponse {
        $this->authorize(
            'delete',
            $supplier
        );

        $this->suppliers->archive(
            $supplier,
            $request->user()
        );

        return redirect()
            ->route('suppliers.index')
            ->with(
                'success',
                'تمت أرشفة المورد. لن يظهر ضمن الموردين النشطين في أوامر الشراء.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Supplier Statement
    |--------------------------------------------------------------------------
    */

    public function statement(
        Request $request,
        Supplier $supplier
    ): View {
        $this->authorize(
            'view',
            $supplier
        );

        $data =
            $this->statementData(
                $request,
                $supplier
            );

        return view(
            'procurement.suppliers.statement',
            $data
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Print Supplier Statement
    |--------------------------------------------------------------------------
    */

    public function printStatement(
        Request $request,
        Supplier $supplier
    ): View {
        $this->authorize(
            'view',
            $supplier
        );

        $data =
            $this->statementData(
                $request,
                $supplier
            );

        return view(
            'procurement.suppliers.statement-print',
            $data
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Shared Statement Data
    |--------------------------------------------------------------------------
    |
    | الشاشة والطباعة تستخدمان نفس البيانات ونفس الحسابات.
    |
    */

    private function statementData(
        Request $request,
        Supplier $supplier
    ): array {
        $currencyId =
            $request->integer('currency_id')
            ?: $supplier->currency_id;

        $locationIds =
            $this->permittedLocationIds(
                $request->user()
            );

        $includeOpeningBalance =
            $request->user()->isAdmin();

        $from =
            $request->date('from')
                ?->toDateString();

        $to =
            $request->date('to')
                ?->toDateString();

        $supplier->load([
            'currency',
            'contacts',
        ]);

        $currencies =
            Currency::query()
                ->active()
                ->orderBy('code')
                ->get();

        $selectedCurrency =
            $currencies->firstWhere(
                'id',
                $currencyId
            );

        /*
        |--------------------------------------------------------------------------
        | Statement Rows
        |--------------------------------------------------------------------------
        */

        $rows =
            $this->balances->statement(
                $supplier,
                $currencyId,
                $from,
                $to,
                $locationIds,
                $includeOpeningBalance,
            );

        /*
        |--------------------------------------------------------------------------
        | Print Totals
        |--------------------------------------------------------------------------
        |
        | نحسب الإجماليات من نفس Rows التي يعرضها كشف الحساب.
        |
        */

        $rowsCollection =
            collect($rows);

        $totalDebit =
            (float) $rowsCollection->sum(
                fn ($row) =>
                    (float) ($row['debit'] ?? 0)
            );

        $totalCredit =
            (float) $rowsCollection->sum(
                fn ($row) =>
                    (float) ($row['credit'] ?? 0)
            );

        $lastRow =
            $rowsCollection->last();

        $finalBalance =
            (float) (
                $lastRow['balance']
                ?? 0
            );

        return [
            'supplier' =>
                $supplier,

            'summary' =>
                $this->balances->summary(
                    $supplier,
                    $currencyId,
                    $locationIds,
                    $includeOpeningBalance,
                ),

            'rows' =>
                $rows,

            'currencies' =>
                $currencies,

            'selectedCurrencyId' =>
                $currencyId,

            'selectedCurrency' =>
                $selectedCurrency,

            'from' =>
                $from,

            'to' =>
                $to,

            'totalDebit' =>
                $totalDebit,

            'totalCredit' =>
                $totalCredit,

            'finalBalance' =>
                $finalBalance,
        ];
    }
}
