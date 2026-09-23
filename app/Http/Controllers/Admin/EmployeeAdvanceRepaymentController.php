<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeAdvanceRepayment;
use App\Services\EmployeeAdvanceRepaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmployeeAdvanceRepaymentController extends Controller
{
    public function verify(
        Request $request,
        EmployeeAdvanceRepayment $repayment,
        EmployeeAdvanceRepaymentService $service
    ): RedirectResponse {
        $service->verify(
            $repayment,
            $request->user()
        );

        return back()->with(
            'success',
            'تم اعتماد سداد السلفة وتحديث الرصيد.'
        );
    }

    public function reject(
        Request $request,
        EmployeeAdvanceRepayment $repayment,
        EmployeeAdvanceRepaymentService $service
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $service->reject(
            $repayment,
            $data['reason'],
            $request->user()
        );

        return back()->with(
            'success',
            'تم رفض دفعة سداد السلفة.'
        );
    }
}
