<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentProofAnalysis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PaymentProofAnalysisService
{
    public function analyze(Payment $payment): PaymentProofAnalysis
    {
        $payment->loadMissing(['paymentMethod', 'locationPaymentAccount']);

        $analysis = PaymentProofAnalysis::query()->create([
            'payment_id' => $payment->id,
            'status' => 'processing',
            'provider' => 'openai-compatible',
            'model' => (string) config('services.payment_proof_ai.model'),
        ]);

        try {
            if (! config('services.payment_proof_ai.enabled')) {
                throw new RuntimeException('Payment proof AI is disabled.');
            }

            if (blank($payment->payment_proof)) {
                throw new RuntimeException('Payment does not have an uploaded proof.');
            }

            $disk = Storage::disk('public');
            $path = ltrim((string) $payment->payment_proof, '/');

            if (! $disk->exists($path)) {
                throw new RuntimeException('Payment proof file was not found on the public disk.');
            }

            $bytes = $disk->get($path);
            $mime = $disk->mimeType($path) ?: 'application/octet-stream';
            $hash = hash('sha256', $bytes);

            $analysis->update(['proof_sha256' => $hash]);

            if (! str_starts_with($mime, 'image/')) {
                throw new RuntimeException('AI analysis currently supports image proofs only. PDF remains available for manual review.');
            }

            $duplicateCount = PaymentProofAnalysis::query()
                ->where('proof_sha256', $hash)
                ->where('payment_id', '!=', $payment->id)
                ->count();

            $result = $this->callVisionModel($payment, $mime, $bytes);
            $signals = collect($result['risk_signals'] ?? [])
                ->filter(fn ($signal) => is_string($signal) && trim($signal) !== '')
                ->map(fn ($signal) => trim($signal))
                ->values();

            if ($duplicateCount > 0) {
                $signals->push('تم استخدام نفس ملف إثبات الدفع في دفعة أخرى داخل النظام.');
            }

            $extractedAmount = $this->nullableFloat($result['amount'] ?? null);
            if ($extractedAmount !== null && abs($extractedAmount - (float) $payment->amount) > 0.01) {
                $signals->push('المبلغ المستخرج من الإثبات لا يطابق مبلغ الدفعة المسجل.');
            }

            $submittedReference = trim((string) ($payment->reference_number ?? ''));
            $extractedReference = trim((string) ($result['transaction_reference'] ?? ''));
            if ($submittedReference !== '' && $extractedReference !== '' && ! $this->looselyMatches($submittedReference, $extractedReference)) {
                $signals->push('رقم المرجع المستخرج لا يطابق المرجع الذي أدخله العميل.');
            }

            $expectedAccount = trim((string) (
                $payment->locationPaymentAccount?->account_number
                ?? $payment->locationPaymentAccount?->wallet_number
                ?? $payment->locationPaymentAccount?->phone_number
                ?? ''
            ));
            $extractedRecipient = trim((string) ($result['recipient_account'] ?? ''));
            if ($expectedAccount !== '' && $extractedRecipient !== '' && ! $this->looselyMatches($expectedAccount, $extractedRecipient)) {
                $signals->push('حساب المستفيد الظاهر في الإثبات لا يطابق حساب الفرع المحدد للدفعة.');
            }

            $riskLevel = $this->normalizeRiskLevel((string) ($result['risk_level'] ?? 'unknown'));
            if ($duplicateCount > 0 || $signals->count() >= 3) {
                $riskLevel = 'high';
            } elseif ($signals->isNotEmpty() && $riskLevel === 'low') {
                $riskLevel = 'medium';
            }

            $analysis->update([
                'status' => 'completed',
                'sender_name' => $this->nullableString($result['sender_name'] ?? null),
                'sender_account' => $this->nullableString($result['sender_account'] ?? null),
                'recipient_name' => $this->nullableString($result['recipient_name'] ?? null),
                'recipient_account' => $this->nullableString($result['recipient_account'] ?? null),
                'transaction_reference' => $this->nullableString($result['transaction_reference'] ?? null),
                'extracted_amount' => $extractedAmount,
                'extracted_currency' => $this->nullableString($result['currency'] ?? null),
                'transaction_at' => $this->nullableDateTime($result['transaction_datetime'] ?? null),
                'confidence' => max(0, min(100, (int) ($result['confidence'] ?? 0))),
                'risk_level' => $riskLevel,
                'risk_signals' => $signals->unique()->values()->all(),
                'raw_text' => $this->nullableString($result['raw_text'] ?? null, 12000),
                'raw_payload' => $result,
                'analyzed_at' => now(),
                'failure_reason' => null,
            ]);
        } catch (Throwable $e) {
            $analysis->update([
                'status' => 'failed',
                'risk_level' => 'unknown',
                'failure_reason' => Str::limit($e->getMessage(), 2000, ''),
                'analyzed_at' => now(),
            ]);
        }

        return $analysis->fresh();
    }

    private function callVisionModel(Payment $payment, string $mime, string $bytes): array
    {
        $baseUrl = rtrim((string) config('services.payment_proof_ai.base_url'), '/');
        $apiKey = (string) config('services.payment_proof_ai.api_key');
        $model = (string) config('services.payment_proof_ai.model');

        if ($apiKey === '') {
            throw new RuntimeException('PAYMENT_PROOF_AI_API_KEY is missing.');
        }

        $prompt = <<<'PROMPT'
حلّل صورة إشعار الدفع كمساعد لموظف مالي. استخرج فقط ما تراه بوضوح ولا تخمّن القيم المخفية.
أعد JSON صالحاً فقط بالمفاتيح التالية:
sender_name, sender_account, recipient_name, recipient_account, transaction_reference, amount, currency, transaction_datetime, confidence, risk_level, risk_signals, raw_text.
confidence رقم من 0 إلى 100.
risk_level واحد من: low, medium, high, unknown.
risk_signals مصفوفة نصوص قصيرة بالعربية تصف مؤشرات الاشتباه البصرية مثل اختلاف الخطوط، قص/لصق، محاذاة غير طبيعية، معلومات متناقضة، أو عناصر واجهة غير متسقة.
مهم: لا تقل إن الإشعار "مزور" بشكل قطعي. الصورة وحدها لا تثبت صحة العملية البنكية؛ قيّم فقط مؤشرات الاشتباه الظاهرة.
PROMPT;

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout((int) config('services.payment_proof_ai.timeout', 45))
            ->post($baseUrl . '/responses', [
                'model' => $model,
                'input' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $prompt],
                        ['type' => 'input_image', 'image_url' => 'data:' . $mime . ';base64,' . base64_encode($bytes)],
                    ],
                ]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('AI provider error: HTTP ' . $response->status() . ' ' . Str::limit($response->body(), 800, ''));
        }

        $payload = $response->json();
        $text = $payload['output_text'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            $text = collect($payload['output'] ?? [])
                ->flatMap(fn ($item) => is_array($item) ? ($item['content'] ?? []) : [])
                ->first(fn ($item) => is_array($item) && ($item['type'] ?? null) === 'output_text')['text'] ?? null;
        }

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('AI provider returned no text output.');
        }

        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text) ?? $text;
        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('AI output was not valid JSON.');
        }

        return $decoded;
    }

    private function looselyMatches(string $left, string $right): bool
    {
        $normalize = fn (string $value): string => preg_replace('/[^\pL\pN]+/u', '', mb_strtolower($value)) ?? '';
        $a = $normalize($left);
        $b = $normalize($right);

        return $a !== '' && $b !== '' && ($a === $b || str_contains($a, $b) || str_contains($b, $a));
    }

    private function normalizeRiskLevel(string $value): string
    {
        return in_array($value, ['low', 'medium', 'high', 'unknown'], true) ? $value : 'unknown';
    }

    private function nullableString(mixed $value, int $max = 255): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : Str::limit($value, $max, '');
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }
        return round((float) $value, 2);
    }

    private function nullableDateTime(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        try {
            return now()->parse($value)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}
