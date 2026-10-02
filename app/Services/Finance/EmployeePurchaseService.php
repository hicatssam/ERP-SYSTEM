<?php

namespace App\Services\Finance;

use App\Enums\MovementReason;
use App\Models\Currency;
use App\Models\DailyCashReconciliation;
use App\Models\Employee;
use App\Models\EmployeePurchase;
use App\Models\EmployeePurchaseReceipt;
use App\Models\InventoryBatch;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeePurchaseService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly FinancialPostingService $posting,
    ) {}

    public function create(array $data, User $actor): EmployeePurchase
    {
        return DB::transaction(function () use ($data, $actor): EmployeePurchase {
            // Lock the employee so retried requests cannot issue stock twice.
            $employee = Employee::query()->lockForUpdate()->findOrFail($data['employee_id']);
            $existing = EmployeePurchase::query()->where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless((int) $existing->employee_id === $employee->id
                    && (int) $existing->location_id === (int) $data['location_id'], 409);

                return $existing;
            }

            $location = Location::query()->branches()->active()->findOrFail($data['location_id']);
            if (! $employee->isActive() || (int) ($employee->primaryLocation()?->id ?? 0) !== $location->id) {
                throw ValidationException::withMessages([
                    'employee_id' => 'يجب اختيار موظف فعّال تابع للفرع المحدد.',
                ]);
            }

            $currency = Currency::query()->where('is_base', true)->where('is_active', true)->first();
            if (! $currency) {
                throw ValidationException::withMessages(['currency' => 'حدد العملة الأساسية في إعدادات النظام أولًا.']);
            }

            $productIds = array_column($data['items'], 'product_id');
            if (count($productIds) !== count(array_unique($productIds))) {
                throw ValidationException::withMessages(['items' => 'لا تكرر المنتج؛ عدل كمية السطر بدلًا من ذلك.']);
            }

            $lines = [];
            $totalCents = 0;
            foreach ($data['items'] as $row) {
                $product = Product::query()->active()->findOrFail($row['product_id']);
                $link = LocationProduct::query()->where('location_id', $location->id)
                    ->where('product_id', $product->id)->where('is_available', true)->first();
                if (! $link) {
                    throw ValidationException::withMessages(['items' => "المنتج {$product->name} غير متاح في هذا الفرع."]);
                }
                if ($product->isVariantProduct() && $product->activeVariants()->exists()) {
                    throw ValidationException::withMessages(['items' => 'لا يمكن صرف منتج ذي مقاسات/خيارات قبل ربط مخزونه بكل متغير.']);
                }

                $quantity = round((float) $row['quantity'], 3);
                if ($quantity <= 0 || abs($quantity - (float) $row['quantity']) > 0.00001) {
                    throw ValidationException::withMessages(['items' => 'الكمية غير صالحة؛ الدقة المسموحة ثلاث خانات.']);
                }

                $priceCents = (int) round((float) ($link->local_selling_price ?? $product->base_selling_price) * 100);
                $lineCents = (int) round($priceCents * $quantity);
                if ($priceCents <= 0 || $lineCents <= 0) {
                    throw ValidationException::withMessages(['items' => "سعر المنتج {$product->name} غير صالح."]);
                }
                $totalCents += $lineCents;
                $lines[] = [$product, $quantity, $priceCents / 100, $lineCents / 100];
            }

            if ($totalCents > 99999999999999) {
                throw ValidationException::withMessages(['items' => 'إجمالي المشتريات يتجاوز الحد المسموح.']);
            }

            $installmentCount = $data['payment_plan'] === 'account' ? 1 : (int) $data['installment_count'];
            if ($installmentCount > $totalCents) {
                throw ValidationException::withMessages(['installment_count' => 'قيمة القسط الواحد يجب ألا تقل عن 0.01.']);
            }

            $purchase = EmployeePurchase::query()->create([
                'number' => 'EMP-BUY-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'request_key' => $data['request_key'],
                'employee_id' => $employee->id,
                'location_id' => $location->id,
                'currency_id' => $currency->id,
                'payment_plan' => $data['payment_plan'],
                'status' => 'open',
                'total_amount' => $totalCents / 100,
                'outstanding_amount' => $totalCents / 100,
                'purchased_at' => now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            foreach ($lines as [$product, $quantity, $unitPrice, $lineTotal]) {
                $item = $purchase->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name_ar ?: $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);
                $this->consumeStock($location->id, $product, $quantity, $item->id, $actor);
            }

            $due = CarbonImmutable::parse($data['first_due_date']);
            $regularCents = intdiv($totalCents, $installmentCount);
            for ($i = 0; $i < $installmentCount; $i++) {
                $cents = $i === $installmentCount - 1
                    ? $totalCents - ($regularCents * ($installmentCount - 1)) : $regularCents;
                $purchase->installments()->create([
                    'sequence' => $i + 1,
                    'due_date' => $due->addMonthsNoOverflow($i)->toDateString(),
                    'amount' => $cents / 100,
                ]);
            }

            $this->posting->employeePurchase($purchase, $actor);
            ActivityLogger::log($actor->id, 'employee_purchase.created', 'accounting', 'employee_purchases', $purchase->id,
                null, ['employee_id' => $employee->id, 'location_id' => $location->id, 'total_amount' => $purchase->total_amount]);

            return $purchase->fresh(['items', 'installments', 'employee', 'currency']);
        });
    }

    public function receive(EmployeePurchase $purchase, array $data, ?UploadedFile $proof, User $actor): EmployeePurchaseReceipt
    {
        $path = null;
        try {
            return DB::transaction(function () use ($purchase, $data, $proof, $actor, &$path): EmployeePurchaseReceipt {
                $locked = EmployeePurchase::query()->lockForUpdate()->findOrFail($purchase->id);
                $existing = EmployeePurchaseReceipt::query()->where('request_key', $data['request_key'])->first();
                if ($existing) {
                    abort_unless((int) $existing->employee_purchase_id === $locked->id, 409);

                    return $existing;
                }

                if ($locked->status !== 'open') {
                    throw ValidationException::withMessages(['amount' => 'هذا الحساب مسدد بالكامل.']);
                }
                $method = PaymentMethod::query()->active()->findOrFail($data['payment_method_id']);
                if ($method->requires_reference && blank($data['reference'] ?? null)) {
                    throw ValidationException::withMessages(['reference' => 'أدخل مرجع طريقة الدفع المحددة.']);
                }
                if ($method->requires_verification && ! $proof) {
                    throw ValidationException::withMessages(['payment_proof' => 'هذه الطريقة تحتاج إثبات الدفع للتحقق.']);
                }

                $amountCents = (int) round((float) $data['amount'] * 100);
                $pendingCents = (int) round((float) $locked->receipts()
                    ->where('status', 'pending_verification')->sum('amount') * 100);
                $availableCents = (int) round((float) $locked->outstanding_amount * 100) - $pendingCents;
                if ($amountCents <= 0 || $amountCents > $availableCents) {
                    throw ValidationException::withMessages(['amount' => 'المبلغ يتجاوز الرصيد المتاح بعد احتساب الدفعات المعلقة.']);
                }

                $status = $method->requires_verification ? 'pending_verification' : 'posted';
                if ($status === 'posted' && $method->type === 'cash') {
                    $this->assertCashDayOpen((int) $locked->location_id);
                }
                if ($proof) {
                    $path = $proof->store('accounting/employee-purchase-proofs', 'local');
                    if (! $path) {
                        throw ValidationException::withMessages(['payment_proof' => 'تعذّر حفظ إثبات الدفع.']);
                    }
                }
                $receipt = $locked->receipts()->create([
                    'request_key' => $data['request_key'],
                    'location_id' => $locked->location_id,
                    'payment_method_id' => $method->id,
                    'amount' => $amountCents / 100,
                    'status' => $status,
                    'received_at' => now(),
                    'posted_at' => $status === 'posted' ? now() : null,
                    'reference' => $data['reference'] ?? null,
                    'payment_proof' => $path,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $actor->id,
                ]);
                if ($status === 'posted') {
                    $this->apply($locked, $receipt, $actor);
                }
                ActivityLogger::log($actor->id, 'employee_purchase.receipt_recorded', 'accounting', 'employee_purchase_receipts', $receipt->id,
                    null, ['purchase_id' => $locked->id, 'amount' => $receipt->amount, 'status' => $status]);

                return $receipt->fresh();
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function verify(EmployeePurchaseReceipt $receipt, bool $approved, User $actor, ?string $reason = null): EmployeePurchaseReceipt
    {
        return DB::transaction(function () use ($receipt, $approved, $actor, $reason): EmployeePurchaseReceipt {
            $purchase = EmployeePurchase::query()->lockForUpdate()->findOrFail($receipt->employee_purchase_id);
            $locked = EmployeePurchaseReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            if ($locked->status !== 'pending_verification') {
                throw ValidationException::withMessages(['receipt' => 'تمت معالجة هذه الدفعة مسبقًا.']);
            }
            if ($approved) {
                if ($locked->paymentMethod->type === 'cash') {
                    $this->assertCashDayOpen((int) $purchase->location_id);
                }
                if ((int) round((float) $locked->amount * 100) > (int) round((float) $purchase->outstanding_amount * 100)) {
                    throw ValidationException::withMessages(['receipt' => 'الدفعة أكبر من الرصيد المتبقي.']);
                }
            }

            $locked->update([
                'status' => $approved ? 'posted' : 'rejected',
                'posted_at' => $approved ? now() : null,
                'verified_at' => now(),
                'verified_by' => $actor->id,
                'rejection_reason' => $approved ? null : trim((string) $reason),
            ]);
            if ($approved) {
                $this->apply($purchase, $locked, $actor);
            }
            ActivityLogger::log($actor->id, $approved ? 'employee_purchase.receipt_verified' : 'employee_purchase.receipt_rejected',
                'accounting', 'employee_purchase_receipts', $locked->id, ['status' => 'pending_verification'], ['status' => $locked->status]);

            return $locked->fresh();
        });
    }

    private function apply(EmployeePurchase $purchase, EmployeePurchaseReceipt $receipt, User $actor): void
    {
        $cents = (int) round((float) $receipt->amount * 100);
        foreach ($purchase->installments()->lockForUpdate()->get() as $installment) {
            $remaining = (int) round(((float) $installment->amount - (float) $installment->paid_amount) * 100);
            $applied = min($cents, $remaining);
            if ($applied <= 0) {
                continue;
            }
            $paid = (int) round((float) $installment->paid_amount * 100) + $applied;
            $installment->update([
                'paid_amount' => $paid / 100,
                'status' => $paid >= (int) round((float) $installment->amount * 100) ? 'paid' : 'partial',
            ]);
            $cents -= $applied;
        }
        if ($cents !== 0) {
            throw ValidationException::withMessages(['receipt' => 'تعذّر توزيع الدفعة على جدول الأقساط.']);
        }

        $paid = (int) round((float) $purchase->paid_amount * 100) + (int) round((float) $receipt->amount * 100);
        $remaining = (int) round((float) $purchase->total_amount * 100) - $paid;
        $purchase->update([
            'paid_amount' => $paid / 100,
            'outstanding_amount' => $remaining / 100,
            'status' => $remaining === 0 ? 'settled' : 'open',
            'settled_at' => $remaining === 0 ? now() : null,
        ]);
        $this->posting->employeeCollection($receipt, $actor);
    }

    private function consumeStock(int $locationId, Product $product, float $quantity, int $itemId, User $actor): void
    {
        $batchQuery = InventoryBatch::query()->where('location_id', $locationId)->where('product_id', $product->id);
        if ($product->tracks_batch || (clone $batchQuery)->exists()) {
            $batches = $batchQuery->where('available_quantity', '>', 0)
                ->where(fn ($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', now()->toDateString()))
                ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expiry_date')->orderBy('id')->lockForUpdate()->get();
            if ((float) $batches->sum('available_quantity') + 0.0001 < $quantity) {
                throw ValidationException::withMessages(['items' => 'كمية الدفعات الصالحة لا تكفي لصرف المنتج.']);
            }
            $remaining = $quantity;
            foreach ($batches as $batch) {
                if ($remaining <= 0.0001) {
                    break;
                }
                $take = min($remaining, (float) $batch->available_quantity);
                $this->inventory->decrease($locationId, $product->id, $take, MovementReason::EmployeePurchase,
                    $actor->id, 'employee_purchase_items', $itemId, "employee-purchase-item:{$itemId}:batch:{$batch->id}",
                    inventoryBatchId: $batch->id);
                $batch->update(['available_quantity' => round((float) $batch->available_quantity - $take, 3)]);
                $remaining = round($remaining - $take, 3);
            }

            return;
        }
        $this->inventory->decrease($locationId, $product->id, $quantity, MovementReason::EmployeePurchase,
            $actor->id, 'employee_purchase_items', $itemId, "employee-purchase-item:{$itemId}");
    }

    private function assertCashDayOpen(int $locationId): void
    {
        if (DailyCashReconciliation::query()->where('location_id', $locationId)
            ->where('business_date', '>=', now()->toDateString())->exists()) {
            throw ValidationException::withMessages(['receipt' => 'خزينة اليوم مقفلة؛ سجّل الدفعة في يوم عمل مفتوح.']);
        }
    }
}
