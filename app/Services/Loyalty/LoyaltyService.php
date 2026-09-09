<?php

namespace App\Services\Loyalty;

use App\Enums\LoyaltyTransactionType;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoyaltyService
{
    public function activeProgram(): ?LoyaltyProgram
    {
        return LoyaltyProgram::query()
            ->currentlyActive()
            ->latest('id')
            ->first();
    }

    public function earnForCompletedOrder(
        Order $order,
        ?User $actor = null
    ): ?LoyaltyTransaction {
        $status = $order->status instanceof \BackedEnum
            ? $order->status->value
            : (string) $order->status;

        if ($status !== 'completed' || ! $order->customer_id) {
            return null;
        }

        $program = $this->activeProgram();

        if (
            ! $program
            || (float) $order->total_amount < (float) $program->minimum_order_amount
        ) {
            return null;
        }

        $points = (int) floor(
            (float) $order->total_amount
            * (float) $program->points_per_currency_unit
        );

        if ($points <= 0) {
            return null;
        }

        $key = "order:{$order->id}:earn";
        $created = false;

        $transaction = DB::transaction(
            function () use (
                $order,
                $actor,
                $points,
                $program,
                $key,
                &$created
            ): LoyaltyTransaction {
                $existing = LoyaltyTransaction::query()
                    ->where('idempotency_key', $key)
                    ->first();

                if ($existing) {
                    return $existing;
                }

                // The customer account lock serializes concurrent status/event
                // handling for the same customer.
                $account = $this->lockedAccount((int) $order->customer_id);

                // Recheck after acquiring the account lock. This prevents a
                // duplicate event from incrementing the balance and then only
                // failing later on the unique idempotency key.
                $existing = LoyaltyTransaction::query()
                    ->where('idempotency_key', $key)
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $newBalance = (int) $account->points_balance + $points;

                $account->update([
                    'points_balance' => $newBalance,
                    'lifetime_earned' => (int) $account->lifetime_earned + $points,
                ]);

                $transaction = LoyaltyTransaction::query()->create([
                    'loyalty_account_id' => $account->id,
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->id,
                    'type' => LoyaltyTransactionType::Earn,
                    'points' => $points,
                    'balance_after' => $newBalance,
                    'idempotency_key' => $key,
                    'note' => 'نقاط مكتسبة من إكمال الطلب '
                        . ($order->order_number ?? '#'.$order->id),
                    'metadata' => [
                        'program_id' => $program->id,
                        'order_total' => (float) $order->total_amount,
                    ],
                    'created_by' => $actor?->id,
                ]);

                $created = true;

                return $transaction;
            },
            3
        );

        if ($actor && $created) {
            $this->log($actor, 'loyalty.earned', $transaction);
        }

        return $transaction;
    }

    public function reverseCancelledOrder(
        Order $order,
        ?User $actor = null
    ): ?LoyaltyTransaction {
        if (! $order->customer_id) {
            return null;
        }

        $key = "order:{$order->id}:reversal";
        $created = false;

        $transaction = DB::transaction(
            function () use ($order, $actor, $key, &$created): ?LoyaltyTransaction {
                $existing = LoyaltyTransaction::query()
                    ->where('idempotency_key', $key)
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $earned = LoyaltyTransaction::query()
                    ->where('order_id', $order->id)
                    ->where('type', LoyaltyTransactionType::Earn->value)
                    ->first();

                if (! $earned || (int) $earned->points <= 0) {
                    return null;
                }

                $account = $this->lockedAccount((int) $order->customer_id);

                $existing = LoyaltyTransaction::query()
                    ->where('idempotency_key', $key)
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $delta = -abs((int) $earned->points);
                $newBalance = (int) $account->points_balance + $delta;

                // Exact reversal is intentional. If the customer already spent
                // these points, the ledger may become negative rather than hiding
                // the liability. Future redemption is blocked until balance recovers.
                $account->update([
                    'points_balance' => $newBalance,
                ]);

                $transaction = LoyaltyTransaction::query()->create([
                    'loyalty_account_id' => $account->id,
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->id,
                    'type' => LoyaltyTransactionType::Reversal,
                    'points' => $delta,
                    'balance_after' => $newBalance,
                    'idempotency_key' => $key,
                    'note' => 'عكس نقاط بسبب إلغاء الطلب',
                    'metadata' => [
                        'reversed_transaction_id' => $earned->id,
                    ],
                    'created_by' => $actor?->id,
                ]);

                $created = true;

                return $transaction;
            },
            3
        );

        if ($actor && $transaction && $created) {
            $this->log($actor, 'loyalty.reversed', $transaction);
        }

        return $transaction;
    }

    public function adjust(
        Customer $customer,
        int $points,
        string $reason,
        User $actor
    ): LoyaltyTransaction {
        if ($points === 0) {
            throw ValidationException::withMessages([
                'points' => 'قيمة التعديل لا يمكن أن تكون صفراً.',
            ]);
        }

        $transaction = DB::transaction(
            function () use ($customer, $points, $reason, $actor): LoyaltyTransaction {
                $account = $this->lockedAccount((int) $customer->id);
                $newBalance = (int) $account->points_balance + $points;

                if ($newBalance < 0) {
                    throw ValidationException::withMessages([
                        'points' => 'لا يمكن أن يصبح رصيد النقاط سالباً بسبب تعديل يدوي.',
                    ]);
                }

                $account->update([
                    'points_balance' => $newBalance,
                ]);

                return LoyaltyTransaction::query()->create([
                    'loyalty_account_id' => $account->id,
                    'customer_id' => $customer->id,
                    'type' => LoyaltyTransactionType::Adjustment,
                    'points' => $points,
                    'balance_after' => $newBalance,
                    'idempotency_key' => 'adjustment:'.str()->uuid(),
                    'note' => $reason,
                    'metadata' => ['manual' => true],
                    'created_by' => $actor->id,
                ]);
            },
            3
        );

        $this->log($actor, 'loyalty.adjusted', $transaction);

        return $transaction;
    }

    public function redeem(
        Customer $customer,
        int $points,
        string $reason,
        User $actor
    ): LoyaltyTransaction {
        $program = $this->activeProgram();

        if (! $program) {
            throw ValidationException::withMessages([
                'points' => 'لا يوجد برنامج ولاء فعال.',
            ]);
        }

        if ($points <= 0) {
            throw ValidationException::withMessages([
                'points' => 'عدد النقاط المطلوب استبدالها يجب أن يكون أكبر من صفر.',
            ]);
        }

        if ($points < (int) $program->minimum_redeem_points) {
            throw ValidationException::withMessages([
                'points' => 'عدد النقاط أقل من الحد الأدنى للاستبدال.',
            ]);
        }

        $transaction = DB::transaction(
            function () use (
                $customer,
                $points,
                $reason,
                $actor,
                $program
            ): LoyaltyTransaction {
                $account = $this->lockedAccount((int) $customer->id);

                if ((int) $account->points_balance < $points) {
                    throw ValidationException::withMessages([
                        'points' => 'رصيد نقاط العميل غير كافٍ.',
                    ]);
                }

                $newBalance = (int) $account->points_balance - $points;

                $account->update([
                    'points_balance' => $newBalance,
                    'lifetime_redeemed' => (int) $account->lifetime_redeemed + $points,
                ]);

                return LoyaltyTransaction::query()->create([
                    'loyalty_account_id' => $account->id,
                    'customer_id' => $customer->id,
                    'type' => LoyaltyTransactionType::Redeem,
                    'points' => -$points,
                    'balance_after' => $newBalance,
                    'idempotency_key' => 'redeem:'.str()->uuid(),
                    'note' => $reason,
                    'metadata' => [
                        'program_id' => $program->id,
                        'nominal_value' => $points
                            * (float) $program->redemption_value_per_point,
                        // Deliberately no invoice/order mutation in Sprint 08.
                        'financial_effect' => 'none_sprint08',
                    ],
                    'created_by' => $actor->id,
                ]);
            },
            3
        );

        $this->log($actor, 'loyalty.redeemed', $transaction);

        return $transaction;
    }

    private function lockedAccount(int $customerId): LoyaltyAccount
    {
        // insertOrIgnore makes first-use account creation concurrency-safe under
        // the unique(customer_id) constraint.
        DB::table('loyalty_accounts')->insertOrIgnore([
            'customer_id' => $customerId,
            'points_balance' => 0,
            'lifetime_earned' => 0,
            'lifetime_redeemed' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return LoyaltyAccount::query()
            ->where('customer_id', $customerId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function log(
        User $actor,
        string $action,
        LoyaltyTransaction $transaction
    ): void {
        ActivityLogger::log(
            userId: $actor->id,
            action: $action,
            module: 'loyalty',
            recordType: 'loyalty_transactions',
            recordId: $transaction->id,
            oldValues: null,
            newValues: [
                'customer_id' => $transaction->customer_id,
                'type' => $transaction->type instanceof \BackedEnum
                    ? $transaction->type->value
                    : $transaction->type,
                'points' => $transaction->points,
                'balance_after' => $transaction->balance_after,
            ],
            metadata: [
                'order_id' => $transaction->order_id,
            ],
        );
    }
}
