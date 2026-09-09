<?php

use App\Http\Controllers\Admin\EmployeePayrollController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\PayrollDocumentController;
use App\Http\Controllers\Admin\PayrollPaymentController;
use App\Http\Controllers\Admin\PayrollReportController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'location.scope',
    'password.changed',
])->group(function (): void {
    Route::prefix('payroll')
        ->name('payroll.')
        ->group(function (): void {
            Route::get(
                '/',
                [PayrollController::class, 'index']
            )->middleware('can:payroll.view')
                ->name('index');

            Route::post(
                '/periods',
                [PayrollController::class, 'store']
            )->middleware('can:payroll.manage')
                ->name('periods.store');

            Route::get(
                '/periods/{period}',
                [PayrollController::class, 'show']
            )->middleware('can:payroll.view')
                ->name('show');

            Route::post(
                '/periods/{period}/calculate',
                [PayrollController::class, 'calculate']
            )->middleware('can:payroll.manage')
                ->name('calculate');

            Route::post(
                '/periods/{period}/approve',
                [PayrollController::class, 'approve']
            )->middleware('can:payroll.approve')
                ->name('approve');

            Route::post(
                '/items/{item}/pay',
                [PayrollController::class, 'pay']
            )->middleware('can:payroll.pay')
                ->name('pay');

            Route::get(
                '/items/{item}/payslip',
                [PayrollDocumentController::class, 'payslip']
            )->middleware('can:payroll.documents.print')
                ->name('payslip');

            Route::get(
                '/payments/{payment}/receipt',
                [PayrollDocumentController::class, 'paymentReceipt']
            )->middleware('can:payroll.documents.print')
                ->name('payments.receipt');

            Route::post(
                '/payments/{payment}/verify',
                [PayrollPaymentController::class, 'verify']
            )->middleware('can:payroll.payments.verify')
                ->name('payments.verify');

            Route::post(
                '/payments/{payment}/reject',
                [PayrollPaymentController::class, 'reject']
            )->middleware('can:payroll.payments.verify')
                ->name('payments.reject');

            Route::post(
                '/payments/{payment}/void',
                [PayrollPaymentController::class, 'void']
            )->middleware('can:payroll.payments.void')
                ->name('payments.void');

            Route::get(
                '/employees/{employee}',
                [EmployeePayrollController::class, 'show']
            )->middleware('can:employee_ledger.view')
                ->name('employees.show');

            Route::post(
                '/employees/{employee}/compensation',
                [EmployeePayrollController::class, 'compensation']
            )->middleware('can:payroll.manage')
                ->name('employees.compensation');

            Route::post(
                '/employees/{employee}/adjustments',
                [EmployeePayrollController::class, 'adjustment']
            )->middleware('can:payroll.adjustments.manage')
                ->name('employees.adjustments');

            Route::post(
                '/employees/{employee}/advances',
                [EmployeePayrollController::class, 'advance']
            )->middleware('can:payroll.advances.manage')
                ->name('employees.advances');

            Route::get(
                '/employees/{employee}/statement/print',
                [PayrollDocumentController::class, 'employeeStatement']
            )->middleware('can:payroll.documents.print')
                ->name('employees.statement.print');

            Route::get(
                '/reports',
                [PayrollReportController::class, 'index']
            )->middleware('can:payroll.reports.view')
                ->name('reports.index');

            Route::get(
                '/reports/csv',
                [PayrollReportController::class, 'csv']
            )->middleware('can:payroll.reports.view')
                ->name('reports.csv');
        });
});
