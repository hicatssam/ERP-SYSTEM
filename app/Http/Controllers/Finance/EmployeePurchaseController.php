<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\EmployeePurchase;
use App\Models\EmployeePurchaseReceipt;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Services\Finance\EmployeePurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeePurchaseController extends Controller
{
    public function __construct(private readonly EmployeePurchaseService $purchases) {}

    public function index(Request $request): View
    {
        $data = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', Rule::in(['open', 'settled'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $locationId = $this->locationId($request, $data['location_id'] ?? null);
        $query = EmployeePurchase::query()->whereIn('location_id', $this->locationIds($request, $locationId));

        if (isset($data['employee_id'])) {
            $query->where('employee_id', $data['employee_id']);
        }
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (filled($data['q'] ?? null)) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($data['q'])).'%';
            $query->where(fn ($q) => $q->where('number', 'like', $term)
                ->orWhereHas('employee', fn ($employee) => $employee->where('full_name', 'like', $term)
                    ->orWhere('employee_number', 'like', $term)));
        }

        $summary = (clone $query)->selectRaw('COUNT(*) AS purchase_count, COALESCE(SUM(outstanding_amount), 0) AS outstanding_total')
            ->first();
        $overdueCount = (clone $query)->whereHas('installments', fn ($installments) => $installments
            ->whereDate('due_date', '<', today()->toDateString())->where('status', '!=', 'paid'))->count();
        $employeeBalances = (clone $query)->selectRaw('employee_id, COUNT(*) AS operations_count, COALESCE(SUM(total_amount), 0) AS purchases_total, COALESCE(SUM(paid_amount), 0) AS paid_total, COALESCE(SUM(outstanding_amount), 0) AS balance_total')
            ->groupBy('employee_id')->orderByDesc('balance_total')->limit(20)->with('employee')->get();

        return view('finance.employee-purchases.index', [
            'purchases' => $query->with(['employee', 'location', 'currency'])
                ->latest('purchased_at')->latest('id')->paginate(25)->withQueryString(),
            'summary' => $summary,
            'overdueCount' => $overdueCount,
            'employeeBalances' => $employeeBalances,
            'locations' => $this->locations($request),
            'locationId' => $locationId,
            'baseCurrency' => Currency::query()->where('is_base', true)->first(),
        ]);
    }

    public function create(Request $request): View
    {
        $request->validate(['location_id' => ['nullable', 'integer', 'exists:locations,id']]);
        $locationId = $this->locationId($request, $request->integer('location_id') ?: null);

        return view('finance.employee-purchases.create', [
            'locations' => $this->locations($request),
            'locationId' => $locationId,
            'currency' => Currency::query()->where('is_base', true)->first(),
        ]);
    }

    public function employees(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'q' => ['required', 'string', 'min:2', 'max:80'],
        ]);
        $locationId = $this->locationId($request, $data['location_id']);
        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $data['q']).'%';
        $matches = Employee::query()->where('employment_status', 'active')
            ->whereHas('employeeLocations', fn ($q) => $q->where('location_id', $locationId)
                ->where('is_primary', true)
                ->where(fn ($dates) => $dates->whereNull('started_at')->orWhereDate('started_at', '<=', now()->toDateString()))
                ->where(fn ($dates) => $dates->whereNull('ended_at')->orWhereDate('ended_at', '>=', now()->toDateString())))
            ->where(fn ($q) => $q->where('full_name', 'like', $term)->orWhere('employee_number', 'like', $term))
            ->orderBy('full_name')->limit(20)->get(['id', 'full_name', 'employee_number']);

        return response()->json($matches);
    }

    public function products(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'q' => ['required', 'string', 'min:2', 'max:80'],
        ]);
        $locationId = $this->locationId($request, $data['location_id']);
        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $data['q']).'%';
        $matches = Product::query()->join('location_products as lp', function ($join) use ($locationId) {
            $join->on('lp.product_id', '=', 'products.id')->where('lp.location_id', $locationId);
        })->join('inventories as inv', function ($join) use ($locationId) {
            $join->on('inv.product_id', '=', 'products.id')->where('inv.location_id', $locationId);
        })->where('products.is_active', true)->where('lp.is_available', true)
            ->whereColumn('inv.quantity', '>', 'inv.reserved_quantity')
            ->whereDoesntHave('activeVariants')
            ->where(fn ($q) => $q->where('products.name', 'like', $term)
                ->orWhere('products.name_ar', 'like', $term)
                ->orWhere('products.sku', 'like', $term)
                ->orWhere('products.barcode', 'like', $term))
            ->orderBy('products.name')->limit(20)
            ->get(['products.id', 'products.name', 'products.name_ar', 'products.sku',
                'products.base_selling_price', 'lp.local_selling_price', 'inv.quantity', 'inv.reserved_quantity'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name_ar ?: $product->name,
                'sku' => $product->sku,
                'price' => $product->local_selling_price ?? $product->base_selling_price,
                'available' => number_format(max(0, (float) $product->quantity - (float) $product->reserved_quantity), 3, '.', ''),
            ]);

        return response()->json($matches);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'payment_plan' => ['required', Rule::in(['account', 'installments'])],
            'installment_count' => ['required_if:payment_plan,installments', 'nullable', 'integer', 'between:2,24'],
            'first_due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1', 'max:10'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999', 'decimal:0,3'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->locationId($request, $data['location_id']);
        $purchase = $this->purchases->create($data, $request->user());

        return redirect()->route('accounting.employee-purchases.show', $purchase)
            ->with('success', 'تم تسجيل المنتجات على حساب الموظف وخصمها من مخزون الفرع.');
    }

    public function show(Request $request, EmployeePurchase $purchase): View
    {
        $this->assertPurchase($request, $purchase);
        return view('finance.employee-purchases.show', [
            'purchase' => $purchase->load(['employee', 'location', 'currency', 'items',
                'installments', 'receipts.paymentMethod', 'receipts.creator', 'receipts.verifier']),
            'paymentMethods' => PaymentMethod::query()->active()->orderBy('sort_order')->get(),
        ]);
    }

    public function receive(Request $request, EmployeePurchase $purchase): RedirectResponse
    {
        $this->assertPurchase($request, $purchase);
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99', 'decimal:0,2'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'reference' => ['nullable', 'string', 'max:120'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $receipt = $this->purchases->receive($purchase, $data, $request->file('payment_proof'), $request->user());

        return redirect()->route('accounting.employee-purchases.show', $purchase)
            ->with('success', $receipt->status === 'posted' ? 'تم ترحيل السداد وتحديث الأقساط.' : 'تم تسجيل الدفعة بانتظار التحقق.');
    }

    public function verify(Request $request, EmployeePurchaseReceipt $receipt): RedirectResponse
    {
        $this->assertPurchase($request, $receipt->purchase);
        $this->purchases->verify($receipt, true, $request->user());

        return back()->with('success', 'تم اعتماد الدفعة وتوزيعها على الأقساط.');
    }

    public function reject(Request $request, EmployeePurchaseReceipt $receipt): RedirectResponse
    {
        $this->assertPurchase($request, $receipt->purchase);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->purchases->verify($receipt, false, $request->user(), $data['reason']);

        return back()->with('success', 'تم رفض الدفعة دون تغيير رصيد الموظف.');
    }

    public function proof(Request $request, EmployeePurchaseReceipt $receipt): StreamedResponse
    {
        $this->assertPurchase($request, $receipt->purchase);
        abort_unless($receipt->payment_proof && Storage::disk('local')->exists($receipt->payment_proof), 404);

        return Storage::disk('local')->download($receipt->payment_proof);
    }

    private function locationId(Request $request, ?int $requested): ?int
    {
        $user = $request->user();
        if ($user->isAdmin() || $user->can('financial.global.view')) {
            if ($requested) {
                abort_unless(Location::query()->branches()->active()->whereKey($requested)->exists(), 403);
            }

            return $requested;
        }
        $own = $user->primaryLocation()?->id;
        abort_unless($own && (! $requested || $requested === $own), 403);

        return $own;
    }

    private function locationIds(Request $request, ?int $locationId)
    {
        return $locationId ? [$locationId] : Location::query()->branches()->active()->pluck('id');
    }

    private function locations(Request $request)
    {
        return Location::query()->branches()->active()
            ->whereIn('id', $this->locationIds($request, $this->locationId($request, null)))
            ->orderBy('name')->get();
    }

    private function assertPurchase(Request $request, EmployeePurchase $purchase): void
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->can('financial.global.view')
            || (int) ($user->primaryLocation()?->id ?? 0) === (int) $purchase->location_id, 403);
    }
}
