<?php

namespace App\Services\Procurement;

use App\Enums\SupplierInvoiceStatus;
use App\Enums\PurchaseReturnStatus;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Illuminate\Support\Collection;

class SupplierBalanceService
{
    /** @return array{opening_balance: float, purchases: float, returns: float, payments: float, outstanding_balance: float, credit_limit: float} */
    public function summary(
        Supplier $supplier,
        ?int $currencyId = null,
        array|Collection|null $locationIds = null,
        bool $includeOpeningBalance = true,
    ): array
    {
        $currencyId ??= $supplier->currency_id;
        $locationIds = $this->normaliseLocationIds($locationIds);

        $invoices = SupplierInvoice::query()
            ->where('supplier_id', $supplier->id)
            ->where('currency_id', $currencyId)
            ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
            ->where('status', '!=', SupplierInvoiceStatus::Cancelled->value);

        $purchases = (float) (clone $invoices)->sum('grand_total');
        $returns = (float) PurchaseReturn::query()
            ->where('supplier_id', $supplier->id)
            ->where('currency_id', $currencyId)
            ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
            ->where('status', PurchaseReturnStatus::Posted->value)
            ->sum('grand_total');
        $payments = (float) SupplierPayment::query()
            ->where('supplier_id', $supplier->id)
            ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
            ->whereNotNull('supplier_invoice_id')
            ->where('status', 'confirmed')
            ->whereHas('supplierInvoice', fn ($query) => $query->where('currency_id', $currencyId))
            ->sum('applied_amount');
        $openingBalance = $includeOpeningBalance && (int) $supplier->currency_id === (int) $currencyId
            ? (float) $supplier->opening_balance
            : 0.0;

        return [
            'opening_balance' => $openingBalance,
            'purchases' => $purchases,
            'returns' => $returns,
            'payments' => $payments,
            'outstanding_balance' => $openingBalance + $purchases - $returns - $payments,
            'credit_limit' => (float) $supplier->credit_limit,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function statement(
        Supplier $supplier,
        ?int $currencyId = null,
        ?string $from = null,
        ?string $to = null,
        array|Collection|null $locationIds = null,
        bool $includeOpeningBalance = true,
    ): Collection
    {
        $currencyId ??= $supplier->currency_id;
        $locationIds = $this->normaliseLocationIds($locationIds);
        $rows = collect();
        $openingBalance = $includeOpeningBalance && (int) $supplier->currency_id === (int) $currencyId
            ? (float) $supplier->opening_balance
            : 0.0;

        if ($from) {
            $openingBalance += $this->netBeforeDate($supplier, $currencyId, $from, $locationIds);
        }

        if ($openingBalance != 0.0) {
            $rows->push([
                'date' => null,
                'reference' => $from ? 'BEFORE-PERIOD' : 'OPENING',
                'description' => $from ? 'رصيد قبل الفترة' : 'رصيد افتتاحي',
                'debit' => $openingBalance > 0 ? $openingBalance : 0,
                'credit' => $openingBalance < 0 ? abs($openingBalance) : 0,
                'currency_id' => $currencyId,
            ]);
        }

        $balance = 0.0;

        $invoices = SupplierInvoice::query()
            ->where('supplier_id', $supplier->id)
            ->where('currency_id', $currencyId)
            ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
            ->where('status', '!=', SupplierInvoiceStatus::Cancelled->value)
            ->when($from, fn ($query) => $query->whereDate('invoice_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('invoice_date', '<=', $to))
            ->get()
            ->map(fn (SupplierInvoice $invoice) => [
                'date' => $invoice->invoice_date,
                'sort_date' => $invoice->invoice_date?->toDateString(),
                'reference' => $invoice->invoice_number,
                'description' => 'فاتورة مورد',
                'debit' => (float) $invoice->grand_total,
                'credit' => 0.0,
                'currency_id' => $currencyId,
            ]);

        $payments = SupplierPayment::query()
            ->where('supplier_id', $supplier->id)
            ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
            ->where('status', 'confirmed')
            ->whereHas('supplierInvoice', fn ($query) => $query->where('currency_id', $currencyId))
            ->when($from, fn ($query) => $query->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('payment_date', '<=', $to))
            ->get()
            ->map(fn (SupplierPayment $payment) => [
                'date' => $payment->payment_date,
                'sort_date' => $payment->payment_date?->toDateString(),
                'reference' => $payment->payment_number,
                'description' => 'دفعة للمورد',
                'debit' => 0.0,
                'credit' => (float) $payment->applied_amount,
                'currency_id' => $currencyId,
            ]);

        $returns = PurchaseReturn::query()
            ->where('supplier_id', $supplier->id)
            ->where('currency_id', $currencyId)
            ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
            ->where('status', PurchaseReturnStatus::Posted->value)
            ->when($from, fn ($query) => $query->whereDate('returned_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('returned_at', '<=', $to))
            ->get()
            ->map(fn (PurchaseReturn $return) => [
                'date' => $return->returned_at,
                'sort_date' => $return->returned_at?->toDateString(),
                'reference' => $return->return_number,
                'description' => 'مرتجع للمورد',
                'debit' => 0.0,
                'credit' => (float) $return->grand_total,
                'currency_id' => $currencyId,
            ]);

        return $rows
            ->concat($invoices)
            ->concat($returns)
            ->concat($payments)
            ->sortBy(fn (array $row) => $row['sort_date'] ?? '0000-00-00')
            ->values()
            ->map(function (array $row) use (&$balance): array {
                $balance += (float) $row['debit'] - (float) $row['credit'];
                $row['balance'] = $balance;
                unset($row['sort_date']);

                return $row;
            });
    }

    /** @param array<int, int>|null $locationIds */
    private function netBeforeDate(Supplier $supplier, int $currencyId, string $from, ?array $locationIds): float
    {
        $purchases = (float) SupplierInvoice::query()
            ->where('supplier_id', $supplier->id)
            ->where('currency_id', $currencyId)
            ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
            ->where('status', '!=', SupplierInvoiceStatus::Cancelled->value)
            ->whereDate('invoice_date', '<', $from)
            ->sum('grand_total');
        $returns = (float) PurchaseReturn::query()
            ->where('supplier_id', $supplier->id)
            ->where('currency_id', $currencyId)
            ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
            ->where('status', PurchaseReturnStatus::Posted->value)
            ->whereDate('returned_at', '<', $from)
            ->sum('grand_total');
        $payments = (float) SupplierPayment::query()
            ->where('supplier_id', $supplier->id)
            ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
            ->where('status', 'confirmed')
            ->whereHas('supplierInvoice', fn ($query) => $query->where('currency_id', $currencyId))
            ->whereDate('payment_date', '<', $from)
            ->sum('applied_amount');

        return $purchases - $returns - $payments;
    }

    /** @return array<int, int>|null */
    private function normaliseLocationIds(array|Collection|null $locationIds): ?array
    {
        if ($locationIds === null) {
            return null;
        }

        return collect($locationIds)
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
