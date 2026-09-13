<?php

use App\Http\Controllers\Finance\PaymentProofAnalysisController;
use Illuminate\Support\Facades\Route;

Route::post(
    'payments/{payment}/proof-analysis',
    [PaymentProofAnalysisController::class, 'store']
)
    ->whereNumber('payment')
    ->middleware('can:payments.verify')
    ->name('payments.proof-analysis.store');
