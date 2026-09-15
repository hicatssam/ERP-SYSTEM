<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payments\PaymentProofAnalysisService;
use Illuminate\Console\Command;

class AnalyzePaymentProofCommand extends Command
{
    protected $signature = 'payment:analyze-proof {payment : Payment ID}';
    protected $description = 'Analyze an uploaded payment proof with the configured AI provider';

    public function handle(PaymentProofAnalysisService $service): int
    {
        $payment = Payment::query()->find((int) $this->argument('payment'));

        if (! $payment) {
            $this->error('Payment not found.');
            return self::FAILURE;
        }

        $analysis = $service->analyze($payment);

        $this->table(
            ['Field', 'Value'],
            [
                ['status', $analysis->status],
                ['sender_name', $analysis->sender_name ?? '—'],
                ['sender_account', $analysis->sender_account ?? '—'],
                ['reference', $analysis->transaction_reference ?? '—'],
                ['amount', $analysis->extracted_amount ?? '—'],
                ['currency', $analysis->extracted_currency ?? '—'],
                ['confidence', $analysis->confidence ?? '—'],
                ['risk_level', $analysis->risk_level ?? 'unknown'],
                ['risk_signals', implode(' | ', $analysis->risk_signals ?? []) ?: '—'],
                ['failure_reason', $analysis->failure_reason ?? '—'],
            ]
        );

        return $analysis->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }
}
