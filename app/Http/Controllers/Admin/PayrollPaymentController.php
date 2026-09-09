<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayrollPayment;
use App\Services\PayrollSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PayrollPaymentController extends Controller
{
    public function verify(
        Request $request,
        PayrollPayment $payment,
        PayrollSettlementService $settlements
    ): RedirectResponse {
        $settlements->verify(
            $payment,
            $request->user()
        );

        return back()->with('success', 'تم التحقق من دفعة الراتب وترحيلها.');
    }

    public function reject(
        Request $request,
        PayrollPayment $payment,
        PayrollSettlementService $settlements
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $settlements->reject(
            $payment,
            $data['reason'],
            $request->user()
        );

        return back()->with('success', 'تم رفض دفعة الراتب.');
    }

    public function void(
        Request $request,
        PayrollPayment $payment,
        PayrollSettlementService $settlements
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $settlements->void(
            $payment,
            $data['reason'],
            $request->user()
        );

        return back()->with('success', 'تم إلغاء دفعة الراتب وعكس أثرها في كشف الموظف.');
    }
}
