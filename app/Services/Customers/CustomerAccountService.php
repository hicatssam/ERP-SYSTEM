<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Invoices\InvoiceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerAccountService
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function canViewAllTransactions(User $user): bool
    {
        return $user->isAdmin()
            || $user->can('customers.view_all')
            || $user->can('financial.global.view');
    }

    public function resolveLocationId(
        Customer $customer,
        User $user,
        ?int $requestedLocationId = null
    ): ?int {
        if (! $this->canViewAllTransactions($user)) {
            $locationId = $user->primaryLocation()?->id;

            abort_unless(
                $locationId && $customer->isAvailableAt((int) $locationId),
                403,
                'لا يمكنك الوصول إلى حساب هذا العميل من هذا الفرع.'
            );

            return (int) $locationId;
        }

        if ($requestedLocationId === null) {
            return null;
        }

        $location = Location::branches()
            ->active()
            ->find($requestedLocationId);

        abort_unless($location, 404, 'الفرع المحدد غير موجود أو غير فعال.');

        return (int) $location->id;
    }

    /**
     * @return array<string, float|int>
     */
    public function summary(
        Customer $customer,
        User $user,
        ?int $requestedLocationId = null
    ): array {
        $locationId = $this->resolveLocationId(
            $customer,
            $user,
            $requestedLocationId
        );

        $invoiceQuery = $customer->invoices()
            ->where('status', 'active');

        $orderQuery = $customer->orders();

        if ($locationId !== null) {
            $invoiceQuery->where('location_id', $locationId);
            $orderQuery->where('location_id', $locationId);
        }

        $invoiceCount = (clone $invoiceQuery)->count();
        $invoiced = (float) (clone $invoiceQuery)->sum('total_amount');
        $paid = (float) (clone $invoiceQuery)->sum('paid_amount');
        $outstanding = (float) (clone $invoiceQuery)->sum('remaining_amount');
        $overdue = (float) (clone $invoiceQuery)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->where('remaining_amount', '>', 0)
            ->sum('remaining_amount');

        $unallocatedCredit = $this->unallocatedCredit($customer, $locationId);

        return [
            'orders_count' => (clone $orderQuery)->count(),
            'orders_total' => round((float) (clone $orderQuery)->sum('total_amount'), 2),
            'invoice_count' => $invoiceCount,
            'invoiced' => round($invoiced, 2),
            'paid' => round($paid, 2),
            'outstanding' => round($outstanding, 2),
            'unallocated_credit' => round($unallocatedCredit, 2),
            'balance' => round($outstanding - $unallocatedCredit, 2),
            'overdue' => round($overdue, 2),
        ];
    }

    /**
     * Build a financial statement from invoices, order payments, customer-level
     * receipts, and refunds. Orders themselves are exposed separately on the
     * customer page so the statement does not double-count sales.
     *
     * @return array{rows: Collection<int, array<string,mixed>>, opening_balance: float, closing_balance: float, location_id: ?int}
     */
    public function statement(
        Customer $customer,
        User $user,
        array $filters = []
    ): array {
        $locationId = $this->resolveLocationId(
            $customer,
            $user,
            isset($filters['location_id']) && $filters['location_id'] !== ''
                ? (int) $filters['location_id']
                : null
        );

        $dateFrom = ! empty($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : null;

        $dateTo = ! empty($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : null;

        $allRows = $this->financialRows($customer, $locationId);

        $openingBalance = 0.0;
        if ($dateFrom) {
            $openingBalance = round((float) $allRows
                ->filter(fn (array $row): bool => $row['date']->lt($dateFrom))
                ->sum(fn (array $row): float => (float) $row['debit'] - (float) $row['credit']), 2);
        }

        $rows = $allRows
            ->filter(function (array $row) use ($dateFrom, $dateTo): bool {
                if ($dateFrom && $row['date']->lt($dateFrom)) {
                    return false;
                }

                if ($dateTo && $row['date']->gt($dateTo)) {
                    return false;
                }

                return true;
            })
            ->values();

        $runningBalance = $openingBalance;
        $rows = $rows->map(function (array $row) use (&$runningBalance): array {
            $runningBalance = round(
                $runningBalance + (float) $row['debit'] - (float) $row['credit'],
                2
            );

            $row['balance'] = $runningBalance;

            return $row;
        });

        return [
            'rows' => $rows,
            'opening_balance' => $openingBalance,
            'period_debit' => round((float) $rows->sum('debit'), 2),
            'period_credit' => round((float) $rows->sum('credit'), 2),
            'closing_balance' => $runningBalance,
            'location_id' => $locationId,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function recordPayment(
        Customer $customer,
        array $data,
        User $user
    ): CustomerPayment {
        $method = PaymentMethod::query()
            ->active()
            ->findOrFail($data['payment_method_id']);

        $canViewAll = $this->canViewAllTransactions($user);

        $scopeLocationId = $canViewAll
            ? (! empty($data['scope_location_id']) ? (int) $data['scope_location_id'] : null)
            : (int) $user->primaryLocation()?->id;

        if ($scopeLocationId !== null) {
            $scopeLocation = Location::branches()->active()->find($scopeLocationId);

            if (! $scopeLocation) {
                throw ValidationException::withMessages([
                    'scope_location_id' => 'نطاق الفرع المحدد غير صالح.',
                ]);
            }
        }

        if ($scopeLocationId !== null && ! $customer->isAvailableAt($scopeLocationId)) {
            throw ValidationException::withMessages([
                'customer_id' => 'هذا العميل غير متاح في الفرع المحدد.',
            ]);
        }

        $receiptLocationId = ! empty($data['location_id'])
            ? (int) $data['location_id']
            : ($scopeLocationId ?? $user->primaryLocation()?->id);

        if ($receiptLocationId !== null) {
            $receiptLocation = Location::branches()->active()->find($receiptLocationId);

            if (! $receiptLocation) {
                throw ValidationException::withMessages([
                    'location_id' => 'فرع استلام الدفعة غير صالح.',
                ]);
            }
        }

        $invoiceIds = array_values(array_unique(array_map(
            'intval',
            $data['invoice_ids'] ?? []
        )));

        $allocationMode = $data['allocation_mode'] ?? 'automatic';

        $this->validateManualInvoices(
            $customer,
            $invoiceIds,
            $scopeLocationId,
            $allocationMode
        );

        $status = $method->requires_verification
            ? 'pending_verification'
            : 'confirmed';

        return DB::transaction(function () use (
            $customer,
            $data,
            $user,
            $method,
            $receiptLocationId,
            $scopeLocationId,
            $invoiceIds,
            $allocationMode,
            $status
        ): CustomerPayment {
            $payment = CustomerPayment::create([
                'customer_id' => $customer->id,
                'location_id' => $receiptLocationId,
                'payment_method_id' => $method->id,
                'amount' => $data['amount'],
                'status' => $status,
                'reference_number' => $data['reference_number'] ?? null,
                'payment_proof' => $data['payment_proof_path'] ?? null,
                'allocation_mode' => $allocationMode,
                'allocation_payload' => [
                    'invoice_ids' => $invoiceIds,
                    'scope_location_id' => $scopeLocationId,
                ],
                'notes' => $data['notes'] ?? null,
                'received_by' => $user->id,
                'verified_by' => $status === 'confirmed' ? $user->id : null,
                'verified_at' => $status === 'confirmed' ? now() : null,
                'paid_at' => now(),
            ]);

            if ($payment->isConfirmed()) {
                $this->allocatePayment($payment);
            }

            ActivityLogger::log(
                userId: $user->id,
                action: 'customer.payment.recorded',
                module: 'customers',
                recordType: 'customer_payments',
                recordId: $payment->id,
                oldValues: null,
                newValues: $payment->only([
                    'customer_id',
                    'amount',
                    'status',
                    'payment_method_id',
                ]),
                metadata: [
                    'location_id' => $payment->location_id,
                    'allocation_mode' => $allocationMode,
                ],
            );

            return $payment->fresh([
                'paymentMethod',
                'allocations.invoice',
            ]);
        });
    }

    public function verifyPayment(
        CustomerPayment $payment,
        User $user,
        string $action,
        ?string $rejectionReason = null
    ): CustomerPayment {
        if (! $payment->isPendingVerification()) {
            throw ValidationException::withMessages([
                'payment' => 'هذه الدفعة ليست بانتظار التحقق.',
            ]);
        }

        return DB::transaction(function () use (
            $payment,
            $user,
            $action,
            $rejectionReason
        ): CustomerPayment {
            $locked = CustomerPayment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if (! $locked->isPendingVerification()) {
                throw ValidationException::withMessages([
                    'payment' => 'تمت معالجة هذه الدفعة مسبقاً.',
                ]);
            }

            if ($action === 'reject') {
                $locked->update([
                    'status' => 'rejected',
                    'rejection_reason' => $rejectionReason,
                    'verified_by' => $user->id,
                    'verified_at' => now(),
                ]);

                return $locked->fresh();
            }

            $locked->update([
                'status' => 'confirmed',
                'verified_by' => $user->id,
                'verified_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->allocatePayment($locked);

            ActivityLogger::log(
                userId: $user->id,
                action: 'customer.payment.verified',
                module: 'customers',
                recordType: 'customer_payments',
                recordId: $locked->id,
                oldValues: ['status' => 'pending_verification'],
                newValues: ['status' => 'confirmed'],
                metadata: ['customer_id' => $locked->customer_id],
            );

            return $locked->fresh([
                'paymentMethod',
                'allocations.invoice',
            ]);
        });
    }

    private function allocatePayment(CustomerPayment $payment): void
    {
        if (! $payment->isConfirmed()) {
            return;
        }

        $payment->loadMissing('allocations');

        $remaining = round(
            max(0, (float) $payment->amount - (float) $payment->allocations->sum('amount')),
            2
        );

        if ($remaining <= 0) {
            return;
        }

        $payload = $payment->allocation_payload ?? [];
        $scopeLocationId = isset($payload['scope_location_id'])
            ? (int) $payload['scope_location_id']
            : null;
        $invoiceIds = array_values(array_unique(array_map(
            'intval',
            $payload['invoice_ids'] ?? []
        )));

        $query = Invoice::query()
            ->where('customer_id', $payment->customer_id)
            ->where('status', 'active')
            ->where('remaining_amount', '>', 0)
            ->orderByRaw('COALESCE(due_at, issued_at) asc')
            ->orderBy('issued_at')
            ->lockForUpdate();

        if ($scopeLocationId !== null) {
            $query->where('location_id', $scopeLocationId);
        }

        if ($payment->allocation_mode === 'manual') {
            if ($invoiceIds === []) {
                return;
            }

            $query->whereIn('id', $invoiceIds);
        }

        $invoices = $query->get();

        foreach ($invoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            // Sync first in case normal order payments changed since the receipt was recorded.
            $this->invoiceService->syncPaymentAmounts($invoice);
            $invoice->refresh();

            $invoiceRemaining = max(0, (float) $invoice->remaining_amount);

            if ($invoiceRemaining <= 0) {
                continue;
            }

            $allocated = round(min($remaining, $invoiceRemaining), 2);

            CustomerPaymentAllocation::updateOrCreate(
                [
                    'customer_payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                ],
                ['amount' => $allocated]
            );

            $remaining = round($remaining - $allocated, 2);

            $this->invoiceService->syncPaymentAmounts($invoice->fresh());
        }
    }

    private function validateManualInvoices(
        Customer $customer,
        array $invoiceIds,
        ?int $scopeLocationId,
        string $allocationMode
    ): void {
        if ($allocationMode !== 'manual') {
            return;
        }

        if ($invoiceIds === []) {
            throw ValidationException::withMessages([
                'invoice_ids' => 'اختر فاتورة واحدة على الأقل للتوزيع اليدوي.',
            ]);
        }

        $query = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->whereIn('id', $invoiceIds);

        if ($scopeLocationId !== null) {
            $query->where('location_id', $scopeLocationId);
        }

        if ($query->count() !== count($invoiceIds)) {
            throw ValidationException::withMessages([
                'invoice_ids' => 'إحدى الفواتير المحددة غير متاحة لك أو لا تخص هذا العميل.',
            ]);
        }
    }

    private function unallocatedCredit(Customer $customer, ?int $locationId = null): float
    {
        $payments = $customer->customerPayments()
            ->where('status', 'confirmed')
            ->withSum('allocations as allocated_amount', 'amount')
            ->get(['id', 'amount', 'allocation_payload']);

        return round((float) $payments->sum(function (CustomerPayment $payment) use ($locationId): float {
            $scopeLocationId = $payment->allocation_payload['scope_location_id'] ?? null;

            if ($locationId !== null && (int) ($scopeLocationId ?? 0) !== $locationId) {
                return 0.0;
            }

            return max(
                0,
                (float) $payment->amount - (float) ($payment->allocated_amount ?? 0)
            );
        }), 2);
    }

    /**
     * @return Collection<int, array<string,mixed>>
     */
    private function financialRows(Customer $customer, ?int $locationId): Collection
    {
        $rows = collect();

        $invoiceQuery = $customer->invoices()
            ->with('location:id,name')
            ->where('status', 'active');

        if ($locationId !== null) {
            $invoiceQuery->where('location_id', $locationId);
        }

        $invoices = $invoiceQuery->get();

        foreach ($invoices as $invoice) {
            $rows->push([
                'date' => $invoice->issued_at ?? $invoice->created_at,
                'type' => 'invoice',
                'type_label' => 'فاتورة',
                'reference' => $invoice->invoice_number,
                'url' => route('invoices.show', $invoice),
                'location' => $invoice->location?->name ?? '—',
                'description' => 'فاتورة مبيعات',
                'debit' => (float) $invoice->total_amount,
                'credit' => 0.0,
            ]);
        }

        $orderIdsQuery = $customer->orders()->select('id');
        $cakeOrderIdsQuery = $customer->specialCakeOrders()->select('id');

        if ($locationId !== null) {
            $orderIdsQuery->where('location_id', $locationId);
            $cakeOrderIdsQuery->where('origin_branch_id', $locationId);
        }

        $payments = Payment::query()
            ->with(['location:id,name', 'paymentMethod:id,name,name_ar', 'refunds'])
            ->where(function ($query) use ($orderIdsQuery, $cakeOrderIdsQuery): void {
                $query
                    ->where(function ($normalOrderQuery) use ($orderIdsQuery): void {
                        $normalOrderQuery
                            ->where('order_type', 'order')
                            ->whereIn('order_id', $orderIdsQuery);
                    })
                    ->orWhere(function ($cakeOrderQuery) use ($cakeOrderIdsQuery): void {
                        $cakeOrderQuery
                            ->where('order_type', 'special_cake_order')
                            ->whereIn('order_id', $cakeOrderIdsQuery);
                    });
            })
            ->whereIn('status', ['confirmed', 'corrected', 'refunded'])
            ->get();

        foreach ($payments as $payment) {
            $rows->push([
                'date' => $payment->paid_at ?? $payment->created_at,
                'type' => 'payment',
                'type_label' => 'دفعة طلب',
                'reference' => $payment->reference_number ?: 'PAY-' . $payment->id,
                'url' => route('payments.index', ['search' => $payment->id]),
                'location' => $payment->location?->name ?? '—',
                'description' => $payment->paymentMethod?->name_ar
                    ?? $payment->paymentMethod?->name
                    ?? 'دفعة',
                'debit' => 0.0,
                'credit' => (float) $payment->amount,
            ]);

            foreach ($payment->refunds as $refund) {
                $rows->push([
                    'date' => $refund->processed_at ?? $refund->created_at,
                    'type' => 'refund',
                    'type_label' => 'استرداد',
                    'reference' => 'REF-' . $refund->id,
                    'url' => null,
                    'location' => $payment->location?->name ?? '—',
                    'description' => $refund->reason ?: 'استرداد دفعة',
                    'debit' => (float) $refund->amount,
                    'credit' => 0.0,
                ]);
            }
        }

        if ($locationId === null) {
            $customerPayments = $customer->customerPayments()
                ->with(['location:id,name', 'paymentMethod:id,name,name_ar'])
                ->where('status', 'confirmed')
                ->get();

            foreach ($customerPayments as $payment) {
                $rows->push([
                    'date' => $payment->paid_at ?? $payment->created_at,
                    'type' => 'customer_payment',
                    'type_label' => 'دفعة على الحساب',
                    'reference' => $payment->reference_number ?: 'CP-' . $payment->id,
                    'url' => null,
                    'location' => $payment->location?->name ?? 'إدارة',
                    'description' => $payment->paymentMethod?->name_ar
                        ?? $payment->paymentMethod?->name
                        ?? 'دفعة على الحساب',
                    'debit' => 0.0,
                    'credit' => (float) $payment->amount,
                ]);
            }
        } else {
            // A payment recorded by a branch belongs to that branch account in full,
            // including any unallocated credit. A global payment is shown in a branch
            // statement only for the portion allocated to invoices of that branch.
            $scopedPayments = $customer->customerPayments()
                ->with(['location:id,name', 'paymentMethod:id,name,name_ar'])
                ->where('status', 'confirmed')
                ->get()
                ->filter(function (CustomerPayment $payment) use ($locationId): bool {
                    $scopeLocationId = $payment->allocation_payload['scope_location_id'] ?? null;
                    return (int) ($scopeLocationId ?? 0) === (int) $locationId;
                });

            foreach ($scopedPayments as $payment) {
                $rows->push([
                    'date' => $payment->paid_at ?? $payment->created_at,
                    'type' => 'customer_payment',
                    'type_label' => 'دفعة على الحساب',
                    'reference' => $payment->reference_number ?: 'CP-' . $payment->id,
                    'url' => null,
                    'location' => $payment->location?->name ?? 'الفرع',
                    'description' => $payment->paymentMethod?->name_ar
                        ?? $payment->paymentMethod?->name
                        ?? 'دفعة على الحساب',
                    'debit' => 0.0,
                    'credit' => (float) $payment->amount,
                ]);
            }

            $allocations = CustomerPaymentAllocation::query()
                ->with([
                    'customerPayment.paymentMethod:id,name,name_ar',
                    'invoice.location:id,name',
                ])
                ->whereHas('customerPayment', function ($query) use ($customer): void {
                    $query
                        ->where('customer_id', $customer->id)
                        ->where('status', 'confirmed');
                })
                ->whereHas('invoice', function ($query) use ($locationId): void {
                    $query->where('location_id', $locationId);
                })
                ->get();

            foreach ($allocations as $allocation) {
                $payment = $allocation->customerPayment;
                $scopeLocationId = $payment->allocation_payload['scope_location_id'] ?? null;

                // Already represented in full above.
                if ((int) ($scopeLocationId ?? 0) === (int) $locationId) {
                    continue;
                }

                $rows->push([
                    'date' => $payment->paid_at ?? $payment->created_at,
                    'type' => 'customer_payment',
                    'type_label' => 'دفعة على الحساب',
                    'reference' => $payment->reference_number ?: 'CP-' . $payment->id,
                    'url' => null,
                    'location' => $allocation->invoice?->location?->name ?? '—',
                    'description' => 'مخصص للفاتورة ' . ($allocation->invoice?->invoice_number ?? ''),
                    'debit' => 0.0,
                    'credit' => (float) $allocation->amount,
                ]);
            }
        }

        return $rows
            ->sortBy(fn (array $row) => $row['date']?->timestamp ?? 0)
            ->values();
    }
}
