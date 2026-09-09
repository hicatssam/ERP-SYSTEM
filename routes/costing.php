<?php

use App\Http\Controllers\Finance\ExpenseCategoryController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\ProfitabilityController;
use App\Http\Middleware\EnsureModuleEnabled;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'location.scope',
    'password.changed',
    EnsureModuleEnabled::class . ':costing',
])
    ->prefix('financial')
    ->name('costing.')
    ->group(function (): void {

        Route::get('/profitability', [ProfitabilityController::class, 'index'])
            ->middleware('can:costing.view')
            ->name('profitability');

        Route::post('/profitability/backfill', [ProfitabilityController::class, 'backfill'])
            ->middleware('can:costing.backfill')
            ->name('backfill');

        Route::get('/expenses', [ExpenseController::class, 'index'])
            ->middleware('can:expenses.view')
            ->name('expenses.index');

        Route::get('/expenses/create', [ExpenseController::class, 'create'])
            ->middleware('can:expenses.create')
            ->name('expenses.create');

        Route::post('/expenses', [ExpenseController::class, 'store'])
            ->middleware('can:expenses.create')
            ->name('expenses.store');

        Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])
            ->middleware('can:expenses.view')
            ->name('expenses.show');

        Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])
            ->middleware('can:expenses.update')
            ->name('expenses.edit');

        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])
            ->middleware('can:expenses.update')
            ->name('expenses.update');

        Route::post('/expenses/{expense}/submit', [ExpenseController::class, 'submit'])
            ->middleware('can:expenses.submit')
            ->name('expenses.submit');

        Route::post('/expenses/{expense}/review', [ExpenseController::class, 'review'])
            ->middleware('can:expenses.approve')
            ->name('expenses.review');

        Route::post('/expenses/{expense}/post', [ExpenseController::class, 'post'])
            ->middleware('can:expenses.post')
            ->name('expenses.post');

        Route::post('/expenses/{expense}/void', [ExpenseController::class, 'void'])
            ->middleware('can:expenses.void')
            ->name('expenses.void');

        Route::middleware('can:expense_categories.manage')
            ->prefix('expense-categories')
            ->name('expense-categories.')
            ->group(function (): void {
                Route::get('/', [ExpenseCategoryController::class, 'index'])->name('index');
                Route::post('/', [ExpenseCategoryController::class, 'store'])->name('store');
                Route::put('/{expenseCategory}', [ExpenseCategoryController::class, 'update'])->name('update');
                Route::post('/{expenseCategory}/toggle', [ExpenseCategoryController::class, 'toggle'])->name('toggle');
            });
    });
