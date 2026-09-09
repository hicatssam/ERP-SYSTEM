<?php

namespace App\Http\Controllers\Finance;

use App\Enums\ExpenseCategoryType;
use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function index(): View
    {
        return view('finance.expense-categories.index', [
            'categories' => ExpenseCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
            'types' => ExpenseCategoryType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:70', 'alpha_dash', 'unique:expense_categories,code'],
            'name' => ['required', 'string', 'max:140'],
            'classification' => ['required', Rule::enum(ExpenseCategoryType::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $category = ExpenseCategory::create($data + [
            'is_active' => true,
            'is_system' => false,
        ]);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'expense_category.created',
            module: 'costing',
            recordType: 'expense_categories',
            recordId: $category->id,
            oldValues: null,
            newValues: $category->toArray(),
            metadata: [],
        );

        return back()->with('success', 'تمت إضافة تصنيف المصروف.');
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'classification' => ['required', Rule::enum(ExpenseCategoryType::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $old = $expenseCategory->toArray();
        $expenseCategory->update($data);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'expense_category.updated',
            module: 'costing',
            recordType: 'expense_categories',
            recordId: $expenseCategory->id,
            oldValues: $old,
            newValues: $expenseCategory->fresh()->toArray(),
            metadata: [],
        );

        return back()->with('success', 'تم تحديث تصنيف المصروف.');
    }

    public function toggle(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        if ($expenseCategory->is_system && $expenseCategory->is_active) {
            abort(409, 'التصنيف النظامي لا يمكن تعطيله من هذه الشاشة.');
        }

        $old = $expenseCategory->only(['is_active']);
        $expenseCategory->update(['is_active' => ! $expenseCategory->is_active]);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'expense_category.status_changed',
            module: 'costing',
            recordType: 'expense_categories',
            recordId: $expenseCategory->id,
            oldValues: $old,
            newValues: $expenseCategory->only(['is_active']),
            metadata: [],
        );

        return back()->with('success', 'تم تحديث حالة التصنيف.');
    }
}
