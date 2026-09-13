<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Payments\PaymentProofAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzePaymentProof implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 90;

    public function __construct(public int $paymentId)
    {
    }

    public function handle(PaymentProofAnalysisService $service): void
    {
        $payment = Payment::query()->find($this->paymentId);

        if (! $payment || blank($payment->payment_proof)) {
            return;
        }

        $service->analyze($payment);
    }
}
