<?php

namespace App\Services\Invoices;

use App\Enums\InvoiceType;
use App\Enums\OrderType;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SpecialCakeOrder;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\Finance\FinancialPostingService;
use App\Notifications\InvoiceCreatedNotification;
use App\Services\Notifications\NotificationDispatcher;

class InvoiceService
{
    public function __construct(private readonly FinancialPostingService $posting) {}

    public function createFromOrder(Order $order, User $user): Invoice
    {
        return DB::transaction(function () use ($order, $user) {
            $order->loadMissing(['items.product', 'items.modifiers', 'customer']);

            $totalAmount = $this->normaliseMoney($order->total_amount);
            $paidAmount = $this->effectiveOrderPaidAmount(
                OrderType::Order->value,
                $order->id,
                $totalAmount
            );
            $remainingAmount = $this->remainingAmount($totalAmount, $paidAmount);
            $issuedAt = now();

            $invoice = Invoice::query()
                ->where('order_type', OrderType::Order->value)
                ->where('order_id', $order->id)
                ->first();

            if (! $invoice) {
                $invoice = new Invoice([
                    'invoice_number' => InvoiceNumberService::generate(),
                    'order_type' => OrderType::Order,
                    'order_id' => $order->id,
                    'issued_at' => $issuedAt,
                    'issued_by' => $user->id,
                ]);
            }

            $invoice->fill([
                'invoice_type' => InvoiceType::RegularOrder,
                'location_id' => $order->location_id,
                'customer_id' => $order->customer_id,
                'subtotal' => $order->subtotal,
                'discount_amount' => $order->discount_amount ?? 0,
                'tax_amount' => $order->tax_amount ?? 0,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'status' => 'active',
                'due_at' => $this->resolveDueAt($order->customer, $issuedAt),
            ]);
            $invoice->save();
            $createdNow = $invoice->wasRecentlyCreated;

            // Keep the invoice idempotent if confirmation is retried.
            $invoice->items()->delete();

            foreach ($order->items as $item) {
                $modifierText = $item->modifiers
                    ->pluck('modifier_name_snapshot')
                    ->filter()
                    ->implode('، ');

                $description = $item->product_name
                    ?: $item->product?->name_ar
                    ?: $item->product?->name
                    ?: 'منتج';

                if ($modifierText !== '') {
                    $description .= ' (' . $modifierText . ')';
                }

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'description' => $description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_amount' => $item->discount_amount ?? 0,
                    'line_total' => $item->line_total,
                ]);
            }

            $this->applyAvailableCustomerCredit($invoice);
            $this->posting->sale($invoice, $user);

            if ($createdNow) {
                DB::afterCommit(fn () => NotificationDispatcher::notifyByPermissions(
                    new InvoiceCreatedNotification($invoice),
                    ['invoices.view', 'financial.branch.view', 'financial.collections.view'],
                    $invoice->location_id,
                    ['financial.global.view'],
                    $user->id,
                ));
            }

            return $invoice->fresh()->load('items.product');
        });
    }

    public function createFromCakeOrder(
        SpecialCakeOrder $cakeOrder,
        User $user
    ): Invoice {
        return DB::transaction(function () use ($cakeOrder, $user): Invoice {
        $cakeOrder = SpecialCakeOrder::query()
            ->lockForUpdate()
            ->findOrFail($cakeOrder->id);
        $cakeOrder->loadMissing('customer');

        $totalAmount = $this->normaliseMoney($cakeOrder->net_price ?? $cakeOrder->total_price);
        $paidAmount = $this->effectiveOrderPaidAmount(
            OrderType::SpecialCakeOrder->value,
            $cakeOrder->id,
            $totalAmount
        );

        $issuedAt = now();

        $invoice = Invoice::query()
            ->where('order_type', OrderType::SpecialCakeOrder->value)
            ->where('order_id', $cakeOrder->id)
            ->lockForUpdate()
            ->first();

        if (! $invoice) {
            $invoice = new Invoice([
                'invoice_number' => InvoiceNumberService::generate(),
                'order_type' => OrderType::SpecialCakeOrder,
                'order_id' => $cakeOrder->id,
                'issued_at' => $issuedAt,
                'issued_by' => $user->id,
            ]);
        }

        $invoice->fill([
            'invoice_type' => InvoiceType::SpecialCake,
            'location_id' => $cakeOrder->origin_branch_id,
            'customer_id' => $cakeOrder->customer_id,
            'subtotal' => $totalAmount,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $this->remainingAmount($totalAmount, $paidAmount),
            'status' => 'active',
            'due_at' => $this->resolveDueAt($cakeOrder->customer, $issuedAt),
        ]);
        $invoice->save();
        $createdNow = $invoice->wasRecentlyCreated;

        $this->applyAvailableCustomerCredit($invoice);
        $this->posting->sale($invoice, $user);

        if ($createdNow) {
            DB::afterCommit(fn () => NotificationDispatcher::notifyByPermissions(
                new InvoiceCreatedNotification($invoice),
                ['invoices.view', 'financial.branch.view', 'financial.collections.view'],
                $invoice->location_id,
                ['financial.global.view'],
                $user->id,
            ));
        }

        return $invoice->fresh();
        });
    }

    /**
     * Synchronise an existing invoice with both order payments
     * and customer-account allocations.
     */
    public function syncPaymentAmounts(Invoice $invoice): Invoice
    {
        $orderType = $this->enumValue($invoice->order_type);

        if (! in_array($orderType, [OrderType::Order->value, OrderType::SpecialCakeOrder->value], true)) {
            return $invoice;
        }

        $totalAmount = $this->normaliseMoney($invoice->total_amount);

        $orderPaidAmount = $this->effectiveOrderPaidAmount(
            $orderType,
            (int) $invoice->order_id,
            $totalAmount
        );

        $accountAllocated = $this->normaliseMoney(
            CustomerPaymentAllocation::query()
                ->where('invoice_id', $invoice->id)
                ->whereHas('customerPayment', fn ($query) => $query->where('status', 'confirmed'))
                ->sum('amount')
        );

        $paidAmount = bcadd($orderPaidAmount, $accountAllocated, 2);

        if (bccomp($paidAmount, $totalAmount, 2) === 1) {
            $paidAmount = $totalAmount;
        }

        $remainingAmount = $this->remainingAmount($totalAmount, $paidAmount);

        if (
            bccomp($this->normaliseMoney($invoice->paid_amount), $paidAmount, 2) !== 0
            || bccomp($this->normaliseMoney($invoice->remaining_amount), $remainingAmount, 2) !== 0
        ) {
            $invoice->updateQuietly([
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
            ]);
        }

        return $invoice;
    }

    /**
     * Called whenever a normal Payment is saved or deleted.
     */
    public function syncFromPayment(Payment $payment): ?Invoice
    {
        $orderType = $this->enumValue($payment->order_type);

        if (! in_array($orderType, [OrderType::Order->value, OrderType::SpecialCakeOrder->value], true)) {
            return null;
        }

        $invoice = Invoice::query()
            ->where('order_type', $orderType)
            ->where('order_id', $payment->order_id)
            ->first();

        return $invoice ? $this->syncPaymentAmounts($invoice) : null;
    }

    /**
     * A corrected payment still counts at its corrected amount. A refunded
     * payment counts only for the portion that was not actually refunded.
     */
    private function effectiveOrderPaidAmount(
        string $orderType,
        int $orderId,
        string $totalAmount
    ): string {
        $payments = Payment::query()
            ->withSum('refunds as refunded_amount', 'amount')
            ->where('order_type', $orderType)
            ->where('order_id', $orderId)
            ->whereIn('status', ['confirmed', 'corrected', 'refunded'])
            ->get(['id', 'amount', 'status']);

        $effective = 0.0;

        foreach ($payments as $payment) {
            $effective += max(
                0,
                (float) $payment->amount - (float) ($payment->refunded_amount ?? 0)
            );
        }

        $paidAmount = $this->normaliseMoney($effective);

        if (bccomp($paidAmount, '0.00', 2) === -1) {
            return '0.00';
        }

        if (bccomp($paidAmount, $totalAmount, 2) === 1) {
            return $totalAmount;
        }

        return $paidAmount;
    }

    private function resolveDueAt(
        ?Customer $customer,
        CarbonInterface $issuedAt
    ): ?CarbonInterface {
        if (! $customer?->allow_credit) {
            return null;
        }

        $terms = max(0, (int) $customer->payment_terms_days);
        $base = Carbon::parse($issuedAt->toDateTimeString());

        $base = match ($customer->billing_cycle) {
            'monthly' => $base->endOfMonth(),
            'weekly' => $base->endOfWeek(),
            default => $base,
        };

        return $base->addDays($terms);
    }

    /**
     * Automatically consume any confirmed, unallocated customer credit when a
     * new invoice is created. This keeps advance payments useful across
     * branches without creating duplicate payments.
     */
    private function applyAvailableCustomerCredit(Invoice $invoice): void
    {
        if (! $invoice->customer_id || (float) $invoice->remaining_amount <= 0) {
            return;
        }

        $payments = CustomerPayment::query()
            ->where('customer_id', $invoice->customer_id)
            ->where('status', 'confirmed')
            ->withSum('allocations as allocated_amount', 'amount')
            ->orderBy('paid_at')
            ->lockForUpdate()
            ->get();

        foreach ($payments as $payment) {
            $scopeLocationId = $payment->allocation_payload['scope_location_id'] ?? null;

            if ($scopeLocationId !== null && (int) $scopeLocationId !== (int) $invoice->location_id) {
                continue;
            }

            $available = round(max(
                0,
                (float) $payment->amount - (float) ($payment->allocated_amount ?? 0)
            ), 2);

            if ($available <= 0) {
                continue;
            }

            $this->syncPaymentAmounts($invoice);
            $invoice->refresh();

            $remaining = round(max(0, (float) $invoice->remaining_amount), 2);

            if ($remaining <= 0) {
                break;
            }

            CustomerPaymentAllocation::create([
                'customer_payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => min($available, $remaining),
            ]);

            $this->syncPaymentAmounts($invoice);
        }
    }

    private function remainingAmount(string $totalAmount, string $paidAmount): string
    {
        $remaining = bcsub($totalAmount, $paidAmount, 2);

        return bccomp($remaining, '0.00', 2) === -1 ? '0.00' : $remaining;
    }

    private function normaliseMoney(mixed $amount): string
    {
        return number_format((float) ($amount ?? 0), 2, '.', '');
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }
}
