<?php

namespace App\Http\Controllers\Finance;

use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ReviewExpenseRequest;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Http\Requests\Finance\UpdateExpenseRequest;
use App\Http\Requests\Finance\VoidExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Location;
use App\Services\Finance\ExpenseWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function __construct(
        private ExpenseWorkflowService $workflow,
    ) {}

    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(ExpenseStatus::class)],
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $user = $request->user();
        $locationId = $this->resolvedLocationId($request);

        $query = Expense::query()
            ->with(['category', 'location', 'creator', 'approvedBy', 'postedBy'])
            ->latest('expense_date')
            ->latest('id');

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('category_id')) {
            $query->where('expense_category_id', $request->integer('category_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date('date_from')->toDateString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date('date_to')->toDateString());
        }

        return view('finance.expenses.index', [
            'expenses' => $query->paginate(30)->withQueryString(),
            'categories' => ExpenseCategory::active()->orderBy('sort_order')->orderBy('name')->get(),
            'locations' => $this->visibleLocations($request),
            'locationId' => $locationId,
            'statuses' => ExpenseStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('finance.expenses.form', [
            'expense' => new Expense(),
            'categories' => ExpenseCategory::active()->orderBy('sort_order')->orderBy('name')->get(),
            'locations' => $this->visibleLocations($request),
            'locationId' => $this->resolvedLocationId($request),
            'mode' => 'create',
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['location_id'] = $this->enforcedLocationId($request, $data['location_id'] ?? null);

        $expense = $this->workflow->createDraft($data, $request->user());

        return redirect()
            ->route('costing.expenses.show', $expense)
            ->with('success', 'تم إنشاء مسودة المصروف. أرسلها للاعتماد بعد المراجعة.');
    }

    public function show(Request $request, Expense $expense): View
    {
        $this->assertExpenseVisible($request, $expense);

        return view('finance.expenses.show', [
            'expense' => $expense->load([
                'category', 'location', 'financialPeriod', 'creator', 'submittedBy',
                'approvedBy', 'rejectedBy', 'postedBy', 'voidedBy',
            ]),
        ]);
    }

    public function edit(Request $request, Expense $expense): View
    {
        $this->assertExpenseVisible($request, $expense);
        abort_unless($request->user()->can('expenses.update'), 403);

        if (! $expense->isEditable()) {
            abort(409, 'لا يمكن تعديل المصروف في حالته الحالية.');
        }

        return view('finance.expenses.form', [
            'expense' => $expense,
            'categories' => ExpenseCategory::active()->orderBy('sort_order')->orderBy('name')->get(),
            'locations' => $this->visibleLocations($request),
            'locationId' => $expense->location_id,
            'mode' => 'edit',
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->assertExpenseVisible($request, $expense);
        $data = $request->validated();
        $data['location_id'] = $this->enforcedLocationId($request, $data['location_id'] ?? null);

        $this->workflow->updateDraft($expense, $data, $request->user());

        return redirect()->route('costing.expenses.show', $expense)->with('success', 'تم تحديث مسودة المصروف.');
    }

    public function submit(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.submit'), 403);
        $this->assertExpenseVisible($request, $expense);
        $this->workflow->submit($expense, $request->user());

        return back()->with('success', 'تم إرسال المصروف للاعتماد.');
    }

    public function review(ReviewExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->assertExpenseVisible($request, $expense);
        $data = $request->validated();

        if ($data['decision'] === 'approve') {
            $this->workflow->approve($expense, $request->user());
            return back()->with('success', 'تم اعتماد المصروف.');
        }

        $this->workflow->reject($expense, $data['reason'], $request->user());
        return back()->with('success', 'تم رفض المصروف وإعادته لصاحبه للمراجعة.');
    }

    public function post(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.post'), 403);
        $this->assertExpenseVisible($request, $expense);
        $this->workflow->post($expense, $request->user());

        return back()->with('success', 'تم ترحيل المصروف للفترة المالية والسجل المالي.');
    }

    public function void(VoidExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->assertExpenseVisible($request, $expense);
        $this->workflow->void($expense, $request->validated('reason'), $request->user());

        return back()->with('success', 'تم عكس المصروف محاسبيًا بسجل مستقل دون حذف العملية الأصلية.');
    }

    private function assertExpenseVisible(Request $request, Expense $expense): void
    {
        $user = $request->user();

        if ($user->isAdmin() || $user->can('expenses.view_all_locations')) {
            return;
        }

        abort_unless((int) ($user->primaryLocation()?->id ?? 0) === (int) $expense->location_id, 403);
    }

    private function enforcedLocationId(Request $request, mixed $requested): int
    {
        $user = $request->user();

        if ($user->isAdmin() || $user->can('expenses.view_all_locations')) {
            $id = (int) $requested;
            abort_unless($id > 0 && Location::query()->active()->whereKey($id)->exists(), 422, 'الموقع غير صالح.');
            return $id;
        }

        $id = (int) ($user->primaryLocation()?->id ?? 0);
        abort_unless($id > 0, 422, 'لا يوجد موقع رئيسي مرتبط بالمستخدم.');
        return $id;
    }

    private function resolvedLocationId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->isAdmin() || $user->can('expenses.view_all_locations')) {
            return $request->filled('location_id') ? $request->integer('location_id') : null;
        }

        $id = (int) ($user->primaryLocation()?->id ?? 0);
        return $id > 0 ? $id : null;
    }

    private function visibleLocations(Request $request)
    {
        $user = $request->user();

        return ($user->isAdmin() || $user->can('expenses.view_all_locations'))
            ? Location::query()->active()->orderBy('name')->get()
            : collect();
    }
}
