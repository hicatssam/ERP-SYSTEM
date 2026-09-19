<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;

class OrderPaymentStatusSynchronizer
{
    public function syncFromPayment(Payment $payment): void
    {
        if ($payment->orderTypeValue() !== 'order') {
            return;
        }

        $order = Order::query()->find($payment->order_id);

        if (! $order) {
            return;
        }

        $this->sync($order);
    }

    public function sync(Order $order): void
    {
        $payments = $order->payments()
            ->with('refunds:id,payment_id,amount')
            ->get();

        $confirmedGross = 0.0;
        $refundedTotal = 0.0;
        $pendingTotal = 0.0;

        foreach ($payments as $payment) {
            $status = $payment->statusValue();
            $refunds = round((float) $payment->refunds->sum('amount'), 2);

            if (in_array($status, ['confirmed', 'corrected', 'refunded'], true)) {
                $confirmedGross += (float) $payment->amount;
                $refundedTotal += $refunds;
            }

            if ($status === 'pending_verification') {
                $pendingTotal += (float) $payment->amount;
            }
        }

        $confirmedGross = round($confirmedGross, 2);
        $refundedTotal = round($refundedTotal, 2);
        $netPaid = round(max(0, $confirmedGross - $refundedTotal), 2);
        $total = round((float) $order->total_amount, 2);

        if ($refundedTotal > 0.004) {
            $status = $confirmedGross > 0.004 && $netPaid <= 0.004
                ? 'refunded'
                : 'partially_refunded';
        } elseif ($netPaid <= 0.004 && $pendingTotal > 0.004) {
            $status = 'pending_payment_verification';
        } elseif ($netPaid <= 0.004) {
            $status = 'payment_pending';
        } elseif ($netPaid + 0.004 >= $total) {
            $status = 'paid';
        } else {
            $status = 'partially_paid';
        }

        $current = $order->payment_status instanceof \BackedEnum
            ? $order->payment_status->value
            : (string) $order->payment_status;

        if ($current !== $status) {
            $order->forceFill(['payment_status' => $status])->save();
        }
    }
}
