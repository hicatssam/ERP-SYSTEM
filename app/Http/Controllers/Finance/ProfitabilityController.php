<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Order;
use App\Services\ActivityLogger;
use App\Services\Finance\CostingService;
use App\Services\Finance\ProfitabilityService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfitabilityController extends Controller
{
    public function __construct(
        private ProfitabilityService $profitability,
        private CostingService $costing,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        $from = Carbon::parse($request->input('date_from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('date_to', now()->toDateString()))->endOfDay();

        $locationIds = $this->locationIds($request);

        return view('finance.profitability.index', [
            'summary' => $this->profitability->summary($locationIds, $from, $to),
            'products' => $this->profitability->products($locationIds, $from, $to),
            'locationBreakdown' => $this->profitability->locations($locationIds, $from, $to),
            'expenseBreakdown' => $this->profitability->expensesByCategory($locationIds, $from, $to),
            'locations' => ($user->isAdmin() || $user->can('costing.view_all_locations'))
                ? Location::query()->active()->orderBy('name')->get()
                : collect(),
            'locationId' => $request->integer('location_id') ?: null,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function backfill(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('costing.backfill'), 403);

        $data = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $query = Order::query()
            ->with('items')
            ->whereIn('status', ['confirmed', 'completed'])
            ->whereHas('items', fn ($q) => $q->whereNull('cost_snapshotted_at'));

        if (! ($request->user()->isAdmin() || $request->user()->can('costing.view_all_locations'))) {
            $primaryLocationId = (int) ($request->user()->primaryLocation()?->id ?? 0);
            abort_unless($primaryLocationId > 0, 422, 'لا يوجد موقع رئيسي مرتبط بالمستخدم.');
            $query->where('location_id', $primaryLocationId);
        } elseif ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }

        if (! empty($data['date_from'])) {
            $query->whereDate(DB::raw('COALESCE(confirmed_at, created_at)'), '>=', $data['date_from']);
        }

        if (! empty($data['date_to'])) {
            $query->whereDate(DB::raw('COALESCE(confirmed_at, created_at)'), '<=', $data['date_to']);
        }

        $done = 0;
        $missing = 0;
        $ordersChecked = 0;

        $query->orderBy('id')->chunkById(100, function ($orders) use (&$done, &$missing, &$ordersChecked): void {
            foreach ($orders as $order) {
                $ordersChecked++;
                $result = $this->costing->backfillFromStockMovements($order);
                $done += $result['snapshotted'];
                $missing += count($result['missing_costs']);
            }
        });

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'costing.backfill_completed',
            module: 'costing',
            recordType: 'orders',
            recordId: null,
            oldValues: null,
            newValues: [
                'orders_checked' => $ordersChecked,
                'snapshotted_items' => $done,
                'remaining_missing_items' => $missing,
            ],
            metadata: [
                'location_id' => $data['location_id'] ?? null,
                'date_from' => $data['date_from'] ?? null,
                'date_to' => $data['date_to'] ?? null,
            ],
        );

        return back()->with(
            'success',
            "تم فحص {$ordersChecked} طلب، واسترجاع تكلفة {$done} سطر من حركات المخزون. بقي {$missing} سطر بلا تكلفة موثوقة."
        );
    }

    private function locationIds(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin() || $user->can('costing.view_all_locations')) {
            if ($request->filled('location_id')) {
                return collect([$request->integer('location_id')]);
            }

            return Location::query()->active()->pluck('id');
        }

        return collect([$user->primaryLocation()?->id])->filter();
    }
}
