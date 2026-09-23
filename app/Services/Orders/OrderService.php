<?php

namespace App\Services\Orders;

use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PaymentMethod;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Invoices\InvoiceService;
use App\Services\Finance\CostingService;
use App\Services\Finance\FinancialPostingService;
use App\Services\Kitchen\KitchenTicketService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Restaurant\RestaurantOrderInventoryService;
use App\Services\Restaurant\RestaurantOrderCustomizationService;
use App\Services\Procurement\DocumentNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


class OrderService
{
    public function __construct(
        private InvoiceService $invoiceService,
        private \App\Services\SalesChannels\OrderSalesChannelService $salesChannelService,
        private KitchenTicketService $kitchenTicketService,
        private CostingService $costingService,
        private RestaurantOrderInventoryService $restaurantOrderInventoryService,
        private RestaurantOrderCustomizationService $restaurantOrderCustomizationService,
        private DocumentNumberService $documentNumbers,
        private FinancialPostingService $financialPosting,
    ) {}

  public function createOrder(array $data, User $user, bool $autoConfirm = false): Order
{
    $order = DB::transaction(function () use ($data, $user, $autoConfirm) {
        $prefix   = SystemSetting::get('order_number_prefix', 'ORD');
        $orderNum = $this->documentNumbers->next('sales_order', $prefix);

        $locationId = (int) (
            $data['location_id']
            ?? $user->primaryLocation()?->id
            ?? 0
        );

        if ($locationId <= 0) {
            throw ValidationException::withMessages([
                'location_id' => 'لا يوجد فرع صالح مرتبط بالطلب.',
            ]);
        }

        $location = \App\Models\Location::query()
            ->branches()
            ->active()
            ->findOrFail($locationId);
$order = Order::create([
    'order_number' => $orderNum,

    // Important:
    // restaurant/admin flows may explicitly choose a branch.
    'location_id' => (int) (
        $data['location_id']
        ?? $location?->id
    ),

    'customer_id' => $data['customer_id'] ?? null,

    // Guest / public ordering
    'guest_name' => $data['guest_name'] ?? null,
    'guest_phone' => $data['guest_phone'] ?? null,
    'delivery_address' => $data['delivery_address'] ?? null,

    'payment_arrangement' => $data['payment_arrangement'],

    'status' => OrderStatus::Draft,

    'notes' => $data['notes'] ?? null,
    'created_by' => $user->id,

    // -------------------------------------------------
    // Restaurant engine
    // -------------------------------------------------
    'restaurant_service_type' =>
        $data['restaurant_service_type'] ?? null,

    'restaurant_table_id' =>
        $data['restaurant_table_id'] ?? null,

    'restaurant_table_session_id' =>
        $data['restaurant_table_session_id'] ?? null,

    'waiter_id' =>
        $data['waiter_id'] ?? null,

    'guest_count' =>
        $data['guest_count'] ?? null,

    // -------------------------------------------------
    // Customer ordering
    // -------------------------------------------------
    'order_source' =>
        $data['order_source'] ?? null,

    'public_token' =>
        $data['public_token'] ?? null,

    'public_request_token' =>
        $data['public_request_token'] ?? null,

    'public_request_id' =>
        $data['public_request_id'] ?? null,

    'public_order_meta' =>
        $data['public_order_meta'] ?? null,
]);

        $subtotal = '0';

        $isRestaurantOrder = ! empty($data['restaurant_service_type']);

        foreach ($data['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);

            $resolved = $isRestaurantOrder
                ? $this->restaurantOrderCustomizationService->resolve(
                    $product,
                    $item,
                    (int) $location->id
                )
                : [
                    'variant' => null,
                    'variant_name' => null,
                    'unit_price' => (float) $product->getEffectivePriceForLocation(
                        (int) $location->id
                    ),
                    'modifiers' => [],
                ];

            $unitPrice = number_format(
                (float) $resolved['unit_price'],
                3,
                '.',
                ''
            );

            $lineTotal = bcmul(
                (string) $item['quantity'],
                $unitPrice,
                3
            );

            $subtotal = bcadd($subtotal, $lineTotal, 3);

            $baseProductName = $product->name_ar ?: $product->name;
            $variantName = $resolved['variant_name'] ?? null;
            $lineName = $variantName
                ? $baseProductName . ' — ' . $variantName
                : $baseProductName;

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_variant_id' => $resolved['variant']?->id,
                'product_name' => $lineName,
                'variant_name_snapshot' => $variantName,
                'quantity' => (float) $item['quantity'],
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'kitchen_notes' => isset($item['kitchen_notes'])
                    ? trim((string) $item['kitchen_notes']) ?: null
                    : null,
            ]);

            foreach ($resolved['modifiers'] as $modifier) {
                $modifierQuantity = max(1, (int) $modifier['quantity']);
                $totalDelta = bcmul(
                    bcmul(
                        (string) $modifier['price_delta'],
                        (string) $modifierQuantity,
                        3
                    ),
                    (string) $item['quantity'],
                    3
                );

                $orderItem->modifiers()->create([
                    'modifier_group_id' => $modifier['modifier_group_id'],
                    'modifier_id' => $modifier['modifier_id'],
                    'group_name_snapshot' => $modifier['group_name'],
                    'modifier_name_snapshot' => $modifier['modifier_name'],
                    'price_delta_snapshot' => $modifier['price_delta'],
                    'quantity' => $modifierQuantity,
                    'total_delta_snapshot' => $totalDelta,
                    'configuration_snapshot' => $modifier['configuration'],
                ]);
            }
        }

        $order->update([
            'subtotal'     => $subtotal,
            'total_amount' => $subtotal,
        ]);
        
$this->salesChannelService->apply(
    $order,
    (int) $data['sales_channel_id'],
    $data['discount_type'] ?? 'none',
    $data['discount_value'] ?? 0,
);

        $this->assertCreditAllowed($order, $data);

        $this->createPaymentIfNeeded($order, $data, $location?->id, $user);

        ActivityLogger::log(
            userId:     $user->id,
            action:     'order.created',
            module:     'orders',
            recordType: 'orders',
            recordId:   $order->id,
            oldValues:  null,
            newValues:  $order->only(['order_number', 'status', 'total_amount', 'payment_arrangement']),
            metadata:   [
                'location_id' => $location->id,
                'items_count' => count($data['items']),
                'restaurant_service_type' => $data['restaurant_service_type'] ?? null,
                'restaurant_table_id' => $data['restaurant_table_id'] ?? null,
            ],
        );

        if ($autoConfirm) {
            // Freeze and consume physical stock first. The consumption rows preserve
            // the exact weighted-average ingredient costs before each deduction.
            $this->restaurantOrderInventoryService
                ->consumeForConfirmation(
                    $order->fresh(['items.product', 'items.productVariant', 'items.modifiers']),
                    $user
                );

            // Build COGS from the frozen consumption snapshot (recipe + modifiers),
            // with the legacy direct-product fallback kept inside CostingService.
            $this->costingService->snapshotAtConfirmation(
                $order->fresh(['items.product']),
                $user
            );

            $order->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);

            $this->invoiceService->createFromOrder($order->fresh(['items', 'customer']), $user);

            // Restaurant orders are routed to kitchen atomically with confirmation.
            $this->kitchenTicketService->dispatchIfEligible(
                $order->fresh(['items.product.category']),
                $user
            );

            ActivityLogger::log(
                userId: $user->id,
                action: 'order.confirmed',
                module: 'orders',
                recordType: 'orders',
                recordId: $order->id,
                oldValues: ['status' => 'draft'],
                newValues: ['status' => 'confirmed'],
                metadata: [
                    'location_id' => $order->location_id,
                    'total_amount' => $order->total_amount,
                    'quick_sale' => true,
                ],
            );
        }

        return $order->fresh(['items', 'invoice']);
    });

   

    OrderCreated::dispatch($order->fresh(), $user);

    if ($autoConfirm) {
        OrderStatusChanged::dispatch($order->fresh(), 'draft', 'confirmed', $user);
    }

    return $order;
}

private function createPaymentIfNeeded(Order $order, array $data, ?int $locationId, User $user): void
{
    $arrangement = $data['payment_arrangement'];

    if (in_array($arrangement, ['pay_on_pickup', 'on_account'], true)) {
        return;
    }

    if (empty($data['payment_method_id'])) {
        return;
    }

    $method = PaymentMethod::query()->active()->findOrFail($data['payment_method_id']);

    $isVerification = $arrangement === 'pending_verification'
        || (bool) $method?->requires_verification;

    $orderTotal = (float) $order->total_amount;

    $amount = match ($arrangement) {
        'pay_now', 'pending_verification' => $orderTotal,
        default => (float) ($data['paid_amount'] ?? 0),
    };

    if ($amount <= 0) {
        throw ValidationException::withMessages([
            'paid_amount' => 'يجب أن يكون المبلغ المدفوع أكبر من صفر.',
        ]);
    }

    if ($amount > $orderTotal) {
        throw ValidationException::withMessages([
            'paid_amount' => 'المبلغ المدفوع لا يمكن أن يتجاوز إجمالي الطلب.',
        ]);
    }

    $proofPath = null;
    if (! empty($data['payment_proof']) && $data['payment_proof'] instanceof \Illuminate\Http\UploadedFile) {
        $proofPath = $data['payment_proof']->store('payment-proofs', 'public');
    }

    \App\Models\Payment::create([
        'order_type'        => 'order',
        'order_id'          => $order->id,
        'payment_method_id' => $data['payment_method_id'],
        'location_id'       => $locationId,
        'amount'            => $amount,
        'status'            => $isVerification ? 'pending_verification' : 'confirmed',
        'reference_number'  => $data['reference_number'] ?? null,
        'payment_proof'     => $proofPath,
        'sender_name'       => $data['sender_name'] ?? null,
        'sender_phone'      => $data['sender_phone'] ?? null,
        'sender_account_number' => $data['sender_account_number'] ?? null,
        'received_by'       => $user->id,
        'verified_by'       => $isVerification ? null : $user->id,
        'verified_at'       => $isVerification ? null : now(),
        'paid_at'           => now(),
    ]);

    $paymentStatus = $isVerification
        ? 'pending_payment_verification'
        : ($amount >= (float) $order->total_amount
            ? 'paid'
            : ($amount > 0 ? 'partially_paid' : 'payment_pending'));

    $order->update(['payment_status' => $paymentStatus]);
}

    private function assertCreditAllowed(Order $order, array $data): void
    {
        if (($data['payment_arrangement'] ?? null) !== 'on_account') {
            return;
        }

        if (! $order->customer_id) {
            throw ValidationException::withMessages([
                'customer_id' => 'يجب اختيار عميل مسجل للبيع على الحساب.',
            ]);
        }

        $customer = Customer::query()->findOrFail($order->customer_id);

        if (! $customer->allow_credit) {
            throw ValidationException::withMessages([
                'payment_arrangement' => 'هذا العميل غير مسموح له بالشراء على الحساب.',
            ]);
        }

        if ($customer->credit_limit === null) {
            return;
        }

        $currentBalance = max(0, $customer->accountBalance());
        $newBalance = round($currentBalance + (float) $order->total_amount, 2);

        if ($newBalance > (float) $customer->credit_limit) {
            throw ValidationException::withMessages([
                'payment_arrangement' => sprintf(
                    'يتجاوز هذا الطلب الحد الائتماني للعميل. الرصيد الحالي: ₪%.2f، الحد: ₪%.2f، بعد الطلب: ₪%.2f.',
                    $currentBalance,
                    (float) $customer->credit_limit,
                    $newBalance
                ),
            ]);
        }
    }
public function confirmOrder(Order $order, User $user): Order
{
    $oldStatus = $order->status?->value ?? $order->status;

    $order = DB::transaction(function () use ($order, $user): Order {
        $locked = Order::query()
            ->whereKey($order->id)
            ->lockForUpdate()
            ->firstOrFail();

        $oldStatus = $locked->status?->value ?? $locked->status;

        if ($oldStatus !== 'draft') {
            throw ValidationException::withMessages([
                'order' => 'لا يمكن تأكيد الطلب لأن حالته الحالية ليست مسودة.',
            ]);
        }

        $locked->load([
            'items.product',
        ]);

        $this->restaurantOrderInventoryService
            ->consumeForConfirmation(
                $locked,
                $user
            );

        $this->costingService->snapshotAtConfirmation(
            $locked->fresh(['items.product']),
            $user
        );

        $locked->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $this->invoiceService->createFromOrder(
            $locked->fresh(['items', 'customer']),
            $user
        );

        $this->kitchenTicketService->dispatchIfEligible(
            $locked->fresh(['items.product.category']),
            $user
        );

        ActivityLogger::log(
            userId: $user->id,
            action: 'order.confirmed',
            module: 'orders',
            recordType: 'orders',
            recordId: $locked->id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'confirmed'],
            metadata: [
                'location_id' => $locked->location_id,
                'total_amount' => $locked->total_amount,
            ],
        );

        $fresh = $locked->fresh();

        OrderStatusChanged::dispatch(
            $fresh,
            (string) $oldStatus,
            'confirmed',
            $user
        );

        return $fresh;
    });

    NotificationDispatcher::orderStatusChanged(
        $order,
        $oldStatus
    );

    return $order;
}

    public function updateOrder(Order $order, array $data, User $user): Order
    {
        $order->load(['items.product', 'items.modifiers']);
        $isConfirmed = in_array($order->status?->value ?? $order->status, ['confirmed']);

        // Build a map of new quantities keyed by order_item id
        $newQtyMap = [];
        foreach ($data['items'] ?? [] as $row) {
            $newQtyMap[(int) $row['id']] = (float) $row['quantity'];
        }

        $this->kitchenTicketService->assertOrderItemChangesAllowed(
            $order,
            $data['items'] ?? []
        );

        return DB::transaction(function () use ($order, $data, $user, $isConfirmed, $newQtyMap) {
            // ── Inventory sync for confirmed orders ───────────────────────────
            // Runs before OrderItem quantities change, using the frozen stock plan.
            if ($isConfirmed && ! empty($newQtyMap)) {
                $this->restaurantOrderInventoryService
                    ->syncConfirmedQuantities(
                        $order,
                        $newQtyMap,
                        $user
                    );
            }

            // ── Header fields ─────────────────────────────────────────────────────
            $oldValues = $order->only(['customer_id', 'payment_arrangement', 'notes']);

            $order->update([
                'customer_id'         => $data['customer_id'] ?? null,
                'payment_arrangement' => $data['payment_arrangement'],
                'notes'               => $data['notes'] ?? null,
            ]);

            // ── Item quantities ───────────────────────────────────────────────────
            if (! empty($newQtyMap)) {
                $subtotal = '0';

                foreach ($order->items as $item) {
                    if (! isset($newQtyMap[$item->id])) {
                        $subtotal = bcadd($subtotal, (string) $item->line_total, 3);
                        continue;
                    }

                    $newQty    = $newQtyMap[$item->id];
                    $oldQty    = (float) $item->quantity;
                    $delta     = $newQty - $oldQty;
                    $lineTotal = bcmul((string) $newQty, (string) $item->unit_price, 3);

                    $itemUpdate = [
                        'quantity'   => $newQty,
                        'line_total' => $lineTotal,
                    ];

                    $incomingRow = collect($data['items'])
                        ->first(
                            fn ($row) => (int) ($row['id'] ?? 0) === (int) $item->id
                        );

                    if (
                        is_array($incomingRow)
                        && array_key_exists('kitchen_notes', $incomingRow)
                    ) {
                        $itemUpdate['kitchen_notes'] =
                            trim((string) ($incomingRow['kitchen_notes'] ?? '')) ?: null;
                    }

                    $item->update($itemUpdate);

                    // Keep modifier monetary snapshots consistent when only the
                    // sold line quantity changes after confirmation.
                    foreach ($item->modifiers as $selectedModifier) {
                        $selectedModifier->update([
                            'total_delta_snapshot' => bcmul(
                                bcmul(
                                    (string) $selectedModifier->price_delta_snapshot,
                                    (string) max(1, (int) $selectedModifier->quantity),
                                    3
                                ),
                                (string) $newQty,
                                3
                            ),
                        ]);
                    }

                    $subtotal = bcadd($subtotal, $lineTotal, 3);
                }

                $order->update([
                    'subtotal'     => $subtotal,
                    'total_amount' => $subtotal,
                ]);


            }

            if ($isConfirmed) {
                // Keep the original confirmation unit-cost snapshot immutable.
                // Quantity changes only recalculate total cost/revenue allocation.
                $this->costingService->recalculateConfirmedOrder(
                    $order->fresh(['items'])
                );
            }

            // ── Invoice sync (confirmed orders only) ─────────────────────────────
            // A confirmed order already has a linked invoice. Keep its customer,
            // subtotal, total, remaining balance, and line items in sync so
            // financial records stay consistent after an edit.
            if ($isConfirmed) {
                $order->load('invoice.items');
                $invoice = $order->invoice;

                if ($invoice) {
                    $freshOrder   = $order->fresh(['items']);
                    $paidAmount   = (float) ($invoice->paid_amount ?? 0);
                    $newSubtotal  = (float) $freshOrder->subtotal;
                    $newTotal     = (float) $freshOrder->total_amount;
                    $newRemaining = max(0, $newTotal - $paidAmount);

                    $invoice->update([
                        'customer_id'     => $freshOrder->customer_id,
                        'subtotal'        => $newSubtotal,
                        'total_amount'    => $newTotal,
                        'remaining_amount'=> $newRemaining,
                    ]);

                    // New invoices carry order_item_id so two Latte lines with
                    // different sizes/modifiers never overwrite each other.
                    $invoiceItemsByOrderItem = $invoice->items
                        ->whereNotNull('order_item_id')
                        ->keyBy('order_item_id');

                    $legacyByProduct = $invoice->items
                        ->whereNull('order_item_id')
                        ->groupBy('product_id');

                    foreach ($freshOrder->items as $orderItem) {
                        $invItem = $invoiceItemsByOrderItem->get($orderItem->id);

                        // Backward compatibility for invoices created before the
                        // order_item_id migration.
                        if (! $invItem) {
                            $legacyGroup = $legacyByProduct->get($orderItem->product_id);
                            $invItem = $legacyGroup?->shift();
                        }

                        if ($invItem) {
                            $invItem->update([
                                'quantity'   => $orderItem->quantity,
                                'line_total' => $orderItem->line_total,
                            ]);
                        }
                    }
                }
            }

            // ── Activity log ──────────────────────────────────────────────────────
            ActivityLogger::log(
                userId:     $user->id,
                action:     'order.updated',
                module:     'orders',
                recordType: 'orders',
                recordId:   $order->id,
                oldValues:  $oldValues,
                newValues:  $order->fresh()->only(['customer_id', 'payment_arrangement', 'notes']),
                metadata:   ['location_id' => $order->location_id],
            );

            return $order->fresh();
        });
    }

    public function cancelOrder(Order $order, string $reason, User $user): Order
    {
        $order = DB::transaction(function () use ($order, $reason, $user) {
            $order = Order::query()
                ->with(['items.product', 'invoice.customerPaymentAllocations'])
                ->lockForUpdate()
                ->findOrFail($order->id);
            $oldStatus = $order->statusValue();

            if (! in_array($oldStatus, ['draft', 'confirmed'], true)) {
                throw ValidationException::withMessages([
                    'order' => 'لا يمكن إلغاء الطلب من حالته الحالية.',
                ]);
            }

            $netCollected = $order->payments()
                ->withSum('refunds as refunded_amount', 'amount')
                ->whereIn('status', ['confirmed', 'corrected', 'refunded'])
                ->get()
                ->sum(fn ($payment): float => max(
                    0,
                    (float) $payment->amount - (float) ($payment->refunded_amount ?? 0)
                ));

            if ($netCollected > 0.004) {
                throw ValidationException::withMessages([
                    'order' => sprintf(
                        'لا يمكن إلغاء الطلب قبل استرداد صافي التحصيل المرتبط به بالكامل: ₪%.2f.',
                        $netCollected
                    ),
                ]);
            }

            // Restore exactly the frozen physical stock consumption.
            if ($oldStatus === 'confirmed') {
                $this->restaurantOrderInventoryService
                    ->restoreForCancellation(
                        $order,
                        $user
                    );
            }

            $order->payments()
                ->where('status', 'pending_verification')
                ->update([
                    'status' => 'rejected',
                    'rejection_reason' => 'أُلغي الطلب قبل التحقق من الدفعة.',
                    'verified_by' => $user->id,
                    'verified_at' => now(),
                ]);

            if ($order->invoice?->isActive()) {
                $order->invoice->customerPaymentAllocations()->delete();
                $order->invoice->update([
                    'status' => 'cancelled',
                    'cancelled_by' => $user->id,
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'إلغاء الطلب: '.trim($reason),
                ]);
                $this->financialPosting->saleCancellation($order->invoice, $user);
            }

            $order->update([
                'status'              => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_at'        => now(),
                'cancelled_by'        => $user->id,
                'payment_status'      => 'payment_pending',
            ]);

            // Cancel active kitchen tickets even if Kitchen module was later disabled.
            $this->kitchenTicketService->cancelForOrder(
                $order,
                $user
            );

            ActivityLogger::log(
                userId:     $user->id,
                action:     'order.cancelled',
                module:     'orders',
                recordType: 'orders',
                recordId:   $order->id,
                oldValues:  ['status' => $oldStatus],
                newValues:  ['status' => 'cancelled'],
                metadata:   ['location_id' => $order->location_id, 'reason' => $reason],
            );

            $fresh = $order->fresh();
            OrderStatusChanged::dispatch($fresh, (string) $oldStatus, 'cancelled', $user);

            return $fresh;
        });


        return $order;
    }

    public function completeOrder(Order $order, User $user): Order
    {
        $oldStatus = $order->status?->value ?? $order->status;

        if ($oldStatus !== 'confirmed') {
            throw ValidationException::withMessages([
                'order' => 'لا يمكن إكمال الطلب إلا بعد تأكيده.',
            ]);
        }

        $order = DB::transaction(function () use ($order, $user): Order {
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedStatus = $locked->status?->value ?? $locked->status;

            if ($lockedStatus !== 'confirmed') {
                throw ValidationException::withMessages([
                    'order' => 'تغيرت حالة الطلب ولا يمكن إكماله الآن.',
                ]);
            }

            $this->kitchenTicketService->assertOrderCanComplete(
                $locked
            );

            $locked->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            ActivityLogger::log(
                userId: $user->id,
                action: 'order.completed',
                module: 'orders',
                recordType: 'orders',
                recordId: $locked->id,
                oldValues: [
                    'status' => 'confirmed',
                ],
                newValues: [
                    'status' => 'completed',
                ],
                metadata: [
                    'location_id' => $locked->location_id,
                    'restaurant_order' => $locked->isRestaurantOrder(),
                ],
            );

            $fresh = $locked->fresh();

            OrderStatusChanged::dispatch(
                $fresh,
                'confirmed',
                'completed',
                $user
            );

            return $fresh;
        });

        NotificationDispatcher::orderStatusChanged(
            $order,
            $oldStatus
        );

        return $order;
    }
}
