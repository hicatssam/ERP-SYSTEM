<?php

namespace App\Services\Payments;

use App\Events\PaymentReceived;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentCorrection;
use App\Models\PaymentMethod;
use App\Models\SpecialCakeOrder;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Finance\FinancialPostingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private readonly FinancialPostingService $posting) {}

    public function recordPayment(array $data, User $user): Payment
    {
        $payment = DB::transaction(function () use ($data, $user): Payment {
            [$source, $locationId, $total] = $this->lockSource((string) $data['order_type'], (int) $data['order_id']);
            $this->assertLocationAccess($user, $locationId);

            $method = PaymentMethod::query()->active()->findOrFail($data['payment_method_id']);
            $this->assertMethodAvailable($method, $locationId);

            if ($method->requires_reference && blank($data['reference_number'] ?? null)) {
                throw ValidationException::withMessages(['reference_number' => 'طريقة الدفع المختارة تتطلب رقم مرجع.']);
            }

            $proof = $data['payment_proof'] ?? null;
            if ($method->requires_verification && ! $proof instanceof UploadedFile) {
                throw ValidationException::withMessages(['payment_proof' => 'طريقة الدفع المختارة تتطلب إرفاق إثبات دفع.']);
            }

            $amount = round((float) $data['amount'], 2);
            $available = $this->availableBalance((string) $data['order_type'], (int) $data['order_id'], $total);
            if ($amount <= 0 || $amount > $available + 0.004) {
                throw ValidationException::withMessages([
                    'amount' => sprintf('المبلغ يتجاوز الرصيد المتبقي القابل للتحصيل: ₪%.2f.', $available),
                ]);
            }

            $proofPath = $proof instanceof UploadedFile ? $proof->store('payment-proofs', 'public') : null;
            $needsVerification = (bool) $method->requires_verification
                || (bool) ($data['force_verification'] ?? false);
            $payment = Payment::query()->create([
                'order_type' => $data['order_type'],
                'order_id' => $data['order_id'],
                'location_id' => $locationId,
                'payment_method_id' => $method->id,
                'amount' => $amount,
                'reference_number' => $data['reference_number'] ?? null,
                'payment_proof' => $proofPath,
                'status' => $needsVerification ? 'pending_verification' : 'confirmed',
                'paid_at' => now(),
                'received_by' => $user->id,
                'verified_by' => $needsVerification ? null : $user->id,
                'verified_at' => $needsVerification ? null : now(),
            ]);

            $this->syncSourcePaymentStatus($source, (string) $data['order_type']);
            if (! $needsVerification) {
                $this->posting->collection($payment, $user);
            }
            ActivityLogger::log(
                userId: $user->id,
                action: 'payment.recorded',
                module: 'payments',
                recordType: 'payments',
                recordId: $payment->id,
                newValues: $payment->only(['amount', 'status', 'order_type', 'order_id', 'payment_method_id']),
                metadata: ['location_id' => $locationId, 'needs_verification' => $needsVerification],
            );

            return $payment;
        });

        PaymentReceived::dispatch($payment, $user);

        return $payment;
    }

    public function verify(Payment $payment, bool $approved, ?string $reason, User $user): Payment
    {
        return DB::transaction(function () use ($payment, $approved, $reason, $user): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $this->assertLocationAccess($user, (int) $locked->location_id);
            if ($locked->statusValue() !== 'pending_verification') {
                throw ValidationException::withMessages(['payment' => 'تمت معالجة هذه الدفعة مسبقًا.']);
            }

            [$source, , $total] = $this->lockSource($locked->orderTypeValue(), (int) $locked->order_id);
            if ($approved) {
                $otherPaid = $this->effectivePaid($locked->orderTypeValue(), (int) $locked->order_id, $locked->id);
                if ($otherPaid + (float) $locked->amount > $total + 0.004) {
                    throw ValidationException::withMessages([
                        'payment' => 'لا يمكن اعتماد الدفعة لأن تحصيلات أخرى غطت رصيد المستند.',
                    ]);
                }
            }

            $locked->update([
                'status' => $approved ? 'confirmed' : 'rejected',
                'rejection_reason' => $approved ? null : $reason,
                'verified_by' => $user->id,
                'verified_at' => now(),
            ]);

            $this->syncSourcePaymentStatus($source, $locked->orderTypeValue());
            if ($approved) {
                $this->posting->collection($locked, $user);
            }
            ActivityLogger::log(
                userId: $user->id,
                action: $approved ? 'payment.verified' : 'payment.rejected',
                module: 'payments',
                recordType: 'payments',
                recordId: $locked->id,
                oldValues: ['status' => 'pending_verification'],
                newValues: ['status' => $approved ? 'confirmed' : 'rejected'],
                metadata: ['location_id' => $locked->location_id, 'reason' => $reason],
            );

            return $locked->fresh();
        });
    }

    public function correct(Payment $payment, float $amount, string $reason, User $user): Payment
    {
        return DB::transaction(function () use ($payment, $amount, $reason, $user): Payment {
            $locked = Payment::query()->with('refunds')->lockForUpdate()->findOrFail($payment->id);
            $this->assertLocationAccess($user, (int) $locked->location_id);
            if (! in_array($locked->statusValue(), ['confirmed', 'corrected'], true)) {
                throw ValidationException::withMessages(['corrected_amount' => 'لا يمكن تصحيح دفعة غير مؤكدة.']);
            }

            [$source, , $total] = $this->lockSource($locked->orderTypeValue(), (int) $locked->order_id);
            $otherPaid = $this->effectivePaid($locked->orderTypeValue(), (int) $locked->order_id, $locked->id);
            $refunded = round((float) $locked->refunds()->sum('amount'), 2);
            $amount = round($amount, 2);
            if ($amount < $refunded - 0.004) {
                throw ValidationException::withMessages([
                    'corrected_amount' => sprintf('لا يمكن جعل الدفعة أقل من المبلغ المسترد منها: ₪%.2f.', $refunded),
                ]);
            }
            if ($otherPaid + max(0, $amount - $refunded) > $total + 0.004) {
                throw ValidationException::withMessages([
                    'corrected_amount' => sprintf('التصحيح يتجاوز إجمالي المستند. أقصى مبلغ: ₪%.2f.', max(0, $total - $otherPaid + $refunded)),
                ]);
            }

            PaymentCorrection::query()->create([
                'original_payment_id' => $locked->id,
                'original_amount' => $locked->amount,
                'corrected_amount' => $amount,
                'reason' => $reason,
                'corrected_by' => $user->id,
            ]);

            $oldAmount = $locked->amount;
            $locked->update(['status' => 'corrected', 'amount' => $amount]);
            $correctionId = (int) $locked->corrections()->latest('id')->value('id');
            $difference = round($amount - (float) $oldAmount, 2);
            if ($difference > 0) {
                $this->posting->collection($locked, $user, "correction-{$correctionId}", $difference);
            } elseif ($difference < 0) {
                $this->posting->paymentReversal($locked, $user, "correction-{$correctionId}", abs($difference));
            }
            $this->syncSourcePaymentStatus($source, $locked->orderTypeValue());
            ActivityLogger::log(
                userId: $user->id,
                action: 'payment.corrected',
                module: 'payments',
                recordType: 'payments',
                recordId: $locked->id,
                oldValues: ['amount' => $oldAmount],
                newValues: ['amount' => $amount, 'status' => 'corrected'],
                metadata: ['location_id' => $locked->location_id, 'reason' => $reason],
            );

            return $locked->fresh();
        });
    }

    private function lockSource(string $type, int $id): array
    {
        if ($type === 'order') {
            $source = Order::query()->lockForUpdate()->findOrFail($id);
            if ($source->statusValue() === 'cancelled') {
                throw ValidationException::withMessages(['order_id' => 'لا يمكن تسجيل دفعة لطلب ملغى.']);
            }
            return [$source, (int) $source->location_id, round((float) $source->total_amount, 2)];
        }
        if ($type === 'special_cake_order') {
            $source = SpecialCakeOrder::query()->lockForUpdate()->findOrFail($id);
            $status = $source->status instanceof \BackedEnum ? $source->status->value : (string) $source->status;
            if (in_array($status, ['cancelled', 'rejected'], true)) {
                throw ValidationException::withMessages(['order_id' => 'لا يمكن تسجيل دفعة لطلب كيك ملغى أو مرفوض.']);
            }
            return [$source, (int) $source->origin_branch_id, round((float) ($source->net_price ?? $source->total_price), 2)];
        }
        throw ValidationException::withMessages(['order_type' => 'نوع المستند المالي غير مدعوم.']);
    }

    private function availableBalance(string $type, int $id, float $total): float
    {
        $pending = (float) Payment::query()
            ->where('order_type', $type)
            ->where('order_id', $id)
            ->where('status', 'pending_verification')
            ->sum('amount');

        return round(max(0, $total - $this->effectivePaid($type, $id) - $pending), 2);
    }

    private function effectivePaid(string $type, int $id, ?int $exceptPaymentId = null): float
    {
        return Payment::query()
            ->withSum('refunds as refunded_amount', 'amount')
            ->where('order_type', $type)
            ->where('order_id', $id)
            ->whereIn('status', ['confirmed', 'corrected', 'refunded'])
            ->when($exceptPaymentId, fn ($query) => $query->where('id', '!=', $exceptPaymentId))
            ->get()
            ->sum(fn (Payment $item): float => max(0, (float) $item->amount - (float) ($item->refunded_amount ?? 0)));
    }

    private function assertLocationAccess(User $user, int $locationId): void
    {
        if (! $user->isAdmin() && ! $user->can('financial.global.view')
            && (int) ($user->primaryLocation()?->id ?? 0) !== $locationId) {
            abort(403, 'لا يمكنك تنفيذ حركة مالية لفرع آخر.');
        }
    }

    private function assertMethodAvailable(PaymentMethod $method, int $locationId): void
    {
        if ($method->locationPaymentMethods()->exists() && ! $method->isAvailableAt($locationId)) {
            throw ValidationException::withMessages(['payment_method_id' => 'طريقة الدفع غير مفعلة في فرع المستند.']);
        }
    }

    private function syncSourcePaymentStatus(Order|SpecialCakeOrder $source, string $type): void
    {
        $total = $type === 'order' ? (float) $source->total_amount : (float) ($source->net_price ?? $source->total_price);
        $paid = $this->effectivePaid($type, (int) $source->id);
        $status = $paid <= 0 ? 'payment_pending' : ($paid + 0.004 >= $total ? 'paid' : 'partially_paid');
        $source->update(['payment_status' => $status]);
    }
}
