<?php

use App\Http\Controllers\Finance\PaymentProofAnalysisController;
use App\Http\Controllers\Finance\PaymentProofReviewController;
use Illuminate\Support\Facades\Route;

Route::get(
    'payments/proof-review',
    [PaymentProofReviewController::class, 'index']
)
    ->middleware('can:payments.verify')
    ->name('payments.proof-review.index');

Route::get(
    'payments/{payment}/proof-analysis',
    [PaymentProofAnalysisController::class, 'show']
)
    ->whereNumber('payment')
    ->middleware('can:payments.verify')
    ->name('payments.proof-analysis.show');

Route::post(
    'payments/{payment}/proof-analysis',
    [PaymentProofAnalysisController::class, 'store']
)
    ->whereNumber('payment')
    ->middleware('can:payments.verify')
    ->name('payments.proof-analysis.store');
