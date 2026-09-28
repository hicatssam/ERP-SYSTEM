<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\BranchCashMovement;
use App\Models\Currency;
use App\Models\DailyCashReconciliation;
use App\Models\Expense;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Services\ActivityLogger;
use App\Services\Finance\DailyCashReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DailyCashReconciliationController extends Controller
{
    public function __construct(private readonly DailyCashReconciliationService $cash) {}

    public function index(Request $request): View
    {
        $this->permit($request, 'financial.cash.view');
        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
        $locations = $this->locations($request);
        $locationId = (int) ($validated['location_id'] ?? $locations->first()->id);
        $this->assertLocation($request, $locationId);
        $date = $validated['date'] ?? now()->toDateString();

        $closed = DailyCashReconciliation::query()->with('closedBy')
            ->where('location_id', $locationId)->where('business_date', $date)->first();
        $prior = DailyCashReconciliation::query()->where('location_id', $locationId)
            ->where('business_date', '<', $date)->latest('business_date')->first();
        $hasLaterClose = DailyCashReconciliation::query()->where('location_id', $locationId)
            ->where('business_date', '>', $date)->exists();
        $canCarry = $prior && $prior->business_date->toDateString() === CarbonImmutable::parse($date)->subDay()->toDateString();
        $opening = $closed?->opening_balance ?? ($canCarry ? $prior->actual_closing : null);
        $live = $this->cash->forDay($locationId, $date);
        $start = CarbonImmutable::parse($date)->startOfDay();
        $movements = BranchCashMovement::query()->with('creator')
            ->where('location_id', $locationId)
            ->where('occurred_at', '>=', $start)->where('occurred_at', '<', $start->addDay())
            ->latest('occurred_at')->latest('id')->get();
        $drift = $closed && collect(DailyCashReconciliationService::COMPONENTS)
            ->contains(fn (string $key) => abs((float) $closed->$key - $live['components'][$key]) > 0.005);
        $canClassify = $request->user()->isAdmin()
            || ($request->user()->can('expenses.view') && $request->user()->can('expenses.post'));

        return view('finance.daily-cash.index', [
            'locations' => $locations, 'locationId' => $locationId, 'date' => $date,
            'closed' => $closed, 'prior' => $prior, 'hasLaterClose' => $hasLaterClose,
            'canCarry' => $canCarry, 'opening' => $opening, 'live' => $live,
            'movements' => $movements, 'drift' => $drift,
            'currencySymbol' => Currency::query()->where('is_base', true)->value('symbol') ?: '₪',
            'canManageMovements' => $request->user()->isAdmin() || $request->user()->can('financial.cash.movements.manage'),
            'canClose' => $request->user()->isAdmin() || $request->user()->can('financial.cash.close'),
            'unclassifiedExpenses' => $canClassify && ! $closed ? Expense::query()
                ->where('location_id', $locationId)->where('expense_date', $date)
                ->whereIn('status', ['posted', 'void'])->whereNull('payment_method_id')
                ->orderBy('id')->get() : collect(),
            'cashPaymentMethods' => $canClassify && ! $closed ? PaymentMethod::active()->orderBy('sort_order')->get() : collect(),
        ]);
    }

    public function storeMovement(Request $request): RedirectResponse
    {
        $this->permit($request, 'financial.cash.movements.manage');
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'type' => ['required', Rule::in(['other_income', 'transfer_in', 'transfer_out'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'reference' => ['required_if:type,transfer_in,transfer_out', 'nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $this->assertLocation($request, (int) $data['location_id']);

        $movement = DB::transaction(function () use ($request, $data): BranchCashMovement {
            Location::query()->whereKey($data['location_id'])->lockForUpdate()->firstOrFail();
            $this->assertDayEditable((int) $data['location_id'], $data['date']);
            $time = $data['date'] === now()->toDateString() ? now()->format('H:i:s') : '12:00:00';

            return BranchCashMovement::query()->create([
                'location_id' => $data['location_id'], 'type' => $data['type'],
                'amount' => $data['amount'], 'occurred_at' => $data['date'].' '.$time,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'], 'created_by' => $request->user()->id,
            ]);
        });
        ActivityLogger::log(
            userId: $request->user()->id, action: 'branch_cash.movement_created',
            module: 'finance', recordType: 'branch_cash_movements', recordId: $movement->id,
            oldValues: null, newValues: $movement->only(['type', 'amount', 'location_id', 'occurred_at']),
        );

        return redirect()->route('daily-cash.index', ['location_id' => $data['location_id'], 'date' => $data['date']])
            ->with('success', 'تم تسجيل الحركة النقدية.');
    }

    public function voidMovement(Request $request, BranchCashMovement $movement): RedirectResponse
    {
        $this->permit($request, 'financial.cash.movements.manage');
        $this->assertLocation($request, (int) $movement->location_id);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $date = $movement->occurred_at->toDateString();

        DB::transaction(function () use ($request, $movement, $date, $data): void {
            Location::query()->whereKey($movement->location_id)->lockForUpdate()->firstOrFail();
            $locked = BranchCashMovement::query()->lockForUpdate()->findOrFail($movement->id);
            if ($locked->voided_at) {
                throw ValidationException::withMessages(['movement' => 'الحركة ملغاة مسبقًا.']);
            }
            $this->assertDayEditable((int) $locked->location_id, $date);
            $locked->update([
                'voided_at' => now(), 'voided_by' => $request->user()->id,
                'void_reason' => $data['reason'],
            ]);
        });
        ActivityLogger::log(
            userId: $request->user()->id, action: 'branch_cash.movement_voided',
            module: 'finance', recordType: 'branch_cash_movements', recordId: $movement->id,
            oldValues: ['voided_at' => null], newValues: ['voided_at' => now()->toDateTimeString(), 'reason' => $data['reason']],
        );

        return redirect()->route('daily-cash.index', ['location_id' => $movement->location_id, 'date' => $date])
            ->with('success', 'تم إلغاء الحركة مع الاحتفاظ بأثرها للتدقيق.');
    }

    public function close(Request $request): RedirectResponse
    {
        $this->permit($request, 'financial.cash.close');
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'actual_closing' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->assertLocation($request, (int) $data['location_id']);

        $closed = DB::transaction(function () use ($request, $data): DailyCashReconciliation {
            Location::query()->whereKey($data['location_id'])->lockForUpdate()->firstOrFail();
            $this->assertDayEditable((int) $data['location_id'], $data['date']);
            $prior = DailyCashReconciliation::query()->where('location_id', $data['location_id'])
                ->where('business_date', '<', $data['date'])->latest('business_date')->first();
            if ($prior && $prior->business_date->toDateString() !== CarbonImmutable::parse($data['date'])->subDay()->toDateString()) {
                throw ValidationException::withMessages(['date' => 'أغلق الأيام الفاصلة أولًا حتى لا تضيع حركات نقدية من الرصيد المرحّل.']);
            }
            if (! $prior && ! isset($data['opening_balance'])) {
                throw ValidationException::withMessages(['opening_balance' => 'أدخل رصيد افتتاح أول يوم للفرع.']);
            }
            $opening = $prior?->actual_closing ?? $data['opening_balance'];
            $live = $this->cash->forDay((int) $data['location_id'], $data['date']);
            if ($live['unclassified_expenses'] || $live['foreign_cash_supplier_payments']
                || $live['unclassified_cash_payroll'] || $live['foreign_cash_payroll']) {
                throw ValidationException::withMessages([
                    'cash' => 'لا يمكن إقفال اليوم: توجد مصروفات بلا طريقة دفع أو دفعات نقدية لموردين/موظفين بعملة مجهولة أو غير أساسية. راجعها أولًا.',
                ]);
            }
            $expected = $this->cash->expected($opening, $live['components']);
            if ($expected < 0) {
                throw ValidationException::withMessages(['cash' => 'الرصيد المتوقع سالب؛ راجع الحركات الخارجة والرصيد الافتتاحي قبل الإقفال.']);
            }
            if (abs((float) $data['actual_closing'] - $expected) > 0.005 && blank($data['note'] ?? null)) {
                throw ValidationException::withMessages(['note' => 'اكتب سبب فرق الصندوق قبل الإقفال.']);
            }

            return DailyCashReconciliation::query()->create([
                'location_id' => $data['location_id'], 'business_date' => $data['date'],
                'opening_balance' => $opening, ...$live['components'],
                'expected_closing' => $expected, 'actual_closing' => $data['actual_closing'],
                'variance' => round((float) $data['actual_closing'] - $expected, 2),
                'note' => $data['note'] ?? null, 'closed_by' => $request->user()->id,
                'closed_at' => now(),
            ]);
        });
        ActivityLogger::log(
            userId: $request->user()->id, action: 'branch_cash.day_closed', module: 'finance',
            recordType: 'daily_cash_reconciliations', recordId: $closed->id,
            oldValues: null, newValues: $closed->only(['business_date', 'opening_balance', 'expected_closing', 'actual_closing', 'variance']),
        );

        return redirect()->route('daily-cash.index', ['location_id' => $data['location_id'], 'date' => $data['date']])
            ->with('success', 'تم حفظ مطابقة خزينة الفرع وإقفال اليوم.');
    }

    private function assertDayEditable(int $locationId, string $date): void
    {
        if (DailyCashReconciliation::query()->where('location_id', $locationId)
            ->where('business_date', '>=', $date)->exists()) {
            throw ValidationException::withMessages(['date' => 'هذا اليوم أو يوم لاحق مقفل؛ لا يمكن تغيير حركاته النقدية.']);
        }
    }

    private function permit(Request $request, string $permission): void
    {
        abort_unless($request->user()->isAdmin() || $request->user()->can($permission), 403);
    }

    private function locations(Request $request)
    {
        $ids = $request->user()->isAdmin()
            ? Location::query()->where('type', 'branch')->pluck('id')
            : collect([$request->user()->primaryLocation()?->id])->filter();
        $locations = Location::query()->where('type', 'branch')->whereIn('id', $ids)->orderBy('name')->get();
        abort_if($locations->isEmpty(), 403, 'لا يوجد فرع مرتبط بالحساب.');

        return $locations;
    }

    private function assertLocation(Request $request, int $locationId): void
    {
        abort_unless($this->locations($request)->contains('id', $locationId), 403);
    }
}
