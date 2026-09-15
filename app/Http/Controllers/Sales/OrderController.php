<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Enums\PaymentArrangement;
use App\Http\Requests\Sales\CancelOrderRequest;
use App\Http\Requests\Sales\StoreOrderRequest;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\Orders\OrderService;
use App\Services\Restaurant\RestaurantTableService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService, private RestaurantTableService $restaurantTables) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::query()->with(['location', 'customer', 'creator', 'restaurantTable.area', 'waiter.employee']);
        if (! $user->isAdmin()) {
            $locationId = $user->primaryLocation()?->id;
            abort_unless($locationId, 403, 'لا يوجد موقع رئيسي مرتبط بحسابك.');
            $query->where('location_id', $locationId);
        }
        if ($request->filled('status')) $query->where('status', $request->string('status')->toString());
        if ($request->filled('location_id') && $user->isAdmin()) $query->where('location_id', (int) $request->location_id);
        if ($request->filled('q')) {
            $q = trim((string) $request->q);
            $query->where(fn ($b) => $b->where('order_number', 'like', "%{$q}%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")));
        }
        $orders = $query->latest('id')->paginate(25)->withQueryString();
        $locations = $user->isAdmin() ? Location::query()->branches()->active()->orderBy('name')->get() : collect();
        return view('sales.orders.index', compact('orders', 'locations'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $locationId = $user->isAdmin() ? (int) ($request->location_id ?: 0) : (int) ($user->primaryLocation()?->id ?: 0);
        $locations = $user->isAdmin() ? Location::query()->branches()->active()->orderBy('name')->get() : collect();
        $customers = $locationId ? Customer::query()->availableAt($locationId)->orderBy('name')->get() : collect();
        $paymentMethods = PaymentMethod::query()->active()->ordered()->get();
        $salesChannels = SalesChannel::query()->where('is_active', true)->orderBy('sort_order')->get();
        return view('sales.orders.create', compact('locations', 'customers', 'paymentMethods', 'salesChannels', 'locationId'));
    }

    public function store(StoreOrderRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();
        $this->ensureCustomerAvailableAtLocation($data['customer_id'] ?? null, (int) ($data['location_id'] ?? $user->primaryLocation()?->id));
        $paymentMethod = ! empty($data['payment_method_id']) ? PaymentMethod::query()->find($data['payment_method_id']) : null;
        $quickSale = (bool) ($data['quick_sale'] ?? false);
        $requiresVerification = $quickSale && $paymentMethod && (bool) $paymentMethod->requires_verification;
        $autoConfirm = $quickSale && ! $requiresVerification;
        $order = $this->orderService->createOrder($data, $user, $autoConfirm);
        if ($quickSale && $requiresVerification) return redirect()->route('orders.show', $order)->with('success', 'تم تسجيل عملية الدفع بنجاح وهي بانتظار التحقق. لم يتم خصم المخزون أو إصدار الفاتورة بعد.');
        if ($quickSale && $order->invoice) return redirect()->route('invoices.print', $order->invoice)->with('success', 'تم تنفيذ البيع السريع وخصم المخزون وإنشاء الفاتورة.');
        return redirect()->route('orders.show', $order)->with('success', 'تم إنشاء الطلب بنجاح.');
    }

    public function show(Request $request, Order $order)
    {
        $this->ensureOrderAccess($order, $request->user());
        $order->load(['location', 'customer', 'creator', 'items.product', 'invoice', 'payments.paymentMethod', 'payments.receivedBy', 'payments.latestProofAnalysis', 'restaurantTable.area', 'restaurantTableSession', 'waiter.employee', 'salesChannel', 'kitchenTickets.station', 'kitchenTickets.items']);
        return view('sales.orders.show', compact('order'));
    }

    public function edit(Request $request, Order $order)
    {
        $this->authorize('update', $order);
        $this->ensureOrderAccess($order, $request->user());
        $order->load(['location', 'items.product']);
        $customers = Customer::query()->availableAt((int) $order->location_id)->orderBy('name')->get(['id', 'location_id', 'name', 'phone']);
        return view('sales.orders.edit', compact('order', 'customers'));
    }

    public function update(Request $request, Order $order)
    {
        $this->authorize('update', $order);
        $this->ensureOrderAccess($order, $request->user());
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'payment_arrangement' => ['required', Rule::in(array_map(fn (PaymentArrangement $a): string => $a->value, PaymentArrangement::cases()))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required_with:items', 'integer', Rule::exists('order_items', 'id')->where(fn ($q) => $q->where('order_id', $order->id))],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.001'],
            'items.*.kitchen_notes' => ['nullable', 'string', 'max:500'],
        ]);
        $this->ensureCustomerAvailableAtLocation($data['customer_id'] ?? null, (int) $order->location_id);
        $this->orderService->updateOrder($order, $data, $request->user());
        return redirect()->route('orders.show', $order)->with('success', 'تم تحديث الطلب بنجاح.');
    }

    public function destroy(Request $request, Order $order)
    {
        $this->ensureOrderAccess($order, $request->user());
        return back()->with('error', 'لا يمكن حذف الطلبات.');
    }

    public function confirm(Request $request, Order $order)
    {
        $this->authorize('confirm', $order);
        $this->ensureOrderAccess($order, $request->user());

        try {
            $this->orderService->confirmOrder($order, $request->user());
        } catch (ValidationException $e) {
            $messages = collect($e->errors())->flatten()->filter()->map(fn ($message) => (string) $message)->values()->all();
            if ($messages === []) {
                $messages = ['تعذر تأكيد الطلب بسبب نقص أو تعارض في المخزون. راجع كميات المنتجات ثم حاول مرة أخرى.'];
            }

            return redirect()
                ->route('orders.show', $order)
                ->withErrors(['stock' => $messages])
                ->with('order_confirm_failed', true)
                ->with('order_confirm_error_title', 'تعذر تأكيد الطلب — راجع المخزون');
        } catch (\Throwable $e) {
            report($e);

            $message = trim((string) $e->getMessage());
            if ($message === '') {
                $message = 'تعذر تأكيد الطلب. راجع المخزون وبيانات الطلب ثم حاول مرة أخرى.';
            }

            return redirect()
                ->route('orders.show', $order)
                ->withErrors(['stock' => [$message]])
                ->with('order_confirm_failed', true)
                ->with('order_confirm_error_title', 'تعذر تأكيد الطلب');
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'تم تأكيد الطلب وخصم المخزون بنجاح.');
    }

    public function cancel(CancelOrderRequest $request, Order $order)
    {
        $this->authorize('cancel', $order);
        $this->ensureOrderAccess($order, $request->user());
        $updatedOrder = $this->orderService->cancelOrder($order, $request->validated('cancellation_reason'), $request->user());
        $this->releaseCustomerMenuTableIfIdle($updatedOrder, $request->user());
        return back()->with('success', 'تم إلغاء الطلب.');
    }

    public function complete(Request $request, Order $order)
    {
        $this->authorize('update', $order);
        $this->ensureOrderAccess($order, $request->user());
        $updatedOrder = $this->orderService->completeOrder($order, $request->user());
        $this->releaseCustomerMenuTableIfIdle($updatedOrder, $request->user());
        return back()->with('success', 'تم إكمال الطلب.');
    }

    public function updateCustomerMessage(Request $request, Order $order)
    {
        $this->authorize('update', $order);
        $this->ensureOrderAccess($order, $request->user());
        abort_unless($order->isCustomerMenuOrder(), 404);
        $data = $request->validate(['customer_status_message' => ['nullable', 'string', 'max:500']]);
        $message = trim((string) ($data['customer_status_message'] ?? ''));
        $order->forceFill([
            'customer_status_message' => $message !== '' ? $message : null,
            'customer_status_message_updated_at' => now(),
            'customer_status_message_by' => $request->user()->id,
        ])->save();
        return back()->with('success', $message !== '' ? 'تم تحديث رسالة العميل وستظهر في شاشة التتبع.' : 'تم مسح رسالة العميل.');
    }

    private function releaseCustomerMenuTableIfIdle(Order $order, User $user): void
    {
        if (! $order->isCustomerMenuOrder() || ! $order->restaurant_table_id || ! $order->restaurant_table_session_id) return;
        $session = $order->restaurantTableSession()->first();
        if (! $session || ! $session->isOpen()) return;
        if ($session->orders()->whereNotIn('status', ['completed', 'cancelled'])->exists()) return;
        $table = $order->restaurantTable()->first();
        if ($table) $this->restaurantTables->closeSession($table, $user);
    }

    private function ensureOrderAccess(Order $order, User $user): void
    {
        if ($user->isAdmin()) return;
        abort_unless((int) $order->location_id === (int) $user->primaryLocation()?->id, 403, 'لا يمكنك الوصول إلى طلب تابع لفرع آخر.');
    }

    private function ensureCustomerAvailableAtLocation(?int $customerId, int $locationId): void
    {
        if (! $customerId) return;
        $exists = Customer::query()->availableAt($locationId)->whereKey($customerId)->exists();
        if (! $exists) throw ValidationException::withMessages(['customer_id' => 'العميل المحدد غير متاح في هذا الفرع.']);
    }
}
