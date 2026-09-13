<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentProofAnalysisService;
use Illuminate\Http\RedirectResponse;

class PaymentProofAnalysisController extends Controller
{
    public function store(
        Payment $payment,
        PaymentProofAnalysisService $service
    ): RedirectResponse {
        $this->authorize('verify', $payment);

        if (blank($payment->payment_proof)) {
            return back()->with('error', 'لا يوجد إثبات دفع مرفوع لهذه الدفعة.');
        }

        $analysis = $service->analyze($payment);

        if ($analysis->status !== 'completed') {
            return back()->with(
                'error',
                'تعذر تحليل إثبات الدفع: ' . ($analysis->failure_reason ?: 'خطأ غير معروف')
            );
        }

        return back()->with(
            'success',
            'تم تحليل إثبات الدفع. النتيجة مساعدة للمراجعة اليدوية ولا تعتمد أو ترفض الدفعة تلقائيًا.'
        );
    }
}
