<?php

namespace App\Http\Controllers\Finance;

use App\Enums\LedgerEntryType;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\SalesLedgerEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountingLedgerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'entry_type' => ['nullable', Rule::in(array_map(
                fn (LedgerEntryType $type) => $type->value,
                LedgerEntryType::cases()
            ))],
        ]);

        $user = $request->user();
        $canViewAll = $user->isAdmin() || $user->can('financial.global.view');
        $locationId = isset($filters['location_id']) ? (int) $filters['location_id'] : null;

        if (! $canViewAll) {
            $primaryId = $user->primaryLocation()?->id;
            abort_unless($primaryId && (! $locationId || $locationId === $primaryId), 403);
            $locationId = $primaryId;
        }

        $from = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['date_to'] ?? now()->toDateString();
        $endExclusive = CarbonImmutable::parse($to)->addDay()->toDateString();
        $locationIds = $locationId ? collect([$locationId]) : Location::query()->pluck('id');

        $query = SalesLedgerEntry::query()
            ->whereIn('location_id', $locationIds)
            ->where('entry_date', '>=', $from)
            // DATE casts may persist midnight timestamps on SQLite; a half-open
            // range includes the full selected day and keeps the date index usable.
            ->where('entry_date', '<', $endExclusive)
            ->when($filters['entry_type'] ?? null, fn ($q, $type) => $q->where('entry_type', $type));

        $employeeTypes = [LedgerEntryType::EmployeePurchase->value, LedgerEntryType::EmployeeCollection->value];
        if (! $user->can('accounting.employee_accounts.view')) {
            $query->whereNotIn('entry_type', $employeeTypes);
        }

        // Keep currencies and posting types separate: sales, receipts and expenses
        // are different measures and cannot be totaled into one account balance.
        $summary = (clone $query)
            ->select('entry_type', 'currency_code')
            ->selectRaw('COUNT(*) AS entries_count, SUM(amount) AS amount_total')
            ->groupBy('entry_type', 'currency_code')
            ->orderBy('entry_type')
            ->get();

        return view('finance.accounting.ledger', [
            'entries' => $query->with(['location', 'period', 'createdBy'])
                ->orderByDesc('entry_date')->orderByDesc('id')
                ->paginate(30)->withQueryString(),
            'summary' => $summary,
            'types' => $user->can('accounting.employee_accounts.view')
                ? LedgerEntryType::cases()
                : array_values(array_filter(LedgerEntryType::cases(),
                    fn (LedgerEntryType $type) => ! in_array($type->value, $employeeTypes, true))),
            'locations' => $canViewAll ? Location::query()->orderBy('name')->get() : collect(),
            'locationId' => $locationId,
            'entryType' => $filters['entry_type'] ?? null,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
