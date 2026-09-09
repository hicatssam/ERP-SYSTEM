<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryExpiryService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryExpiryController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();

        $status = (string) $request->input('status', '');
        $locationId = $request->integer('location_id') ?: null;
        $productId = $request->integer('product_id') ?: null;
        $search = trim((string) $request->input('q', ''));

        $base = DB::table('inventory_batches as b')
            ->leftJoin('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoin('locations as l', 'l.id', '=', 'b.location_id')
            ->whereNotNull('b.expiry_date')
            ->where('b.available_quantity', '>', 0);

        $this->scopeToUserLocation($base, $request);

        /*
         * تطبيق فلاتر الموقع والمنتج والبحث على الإحصائيات والجدول.
         * لا نطبق فلتر الحالة هنا حتى تبقى جميع بطاقات الإحصائيات ظاهرة.
         */
        if ($locationId) {
            $base->where('b.location_id', $locationId);
        }

        if ($productId) {
            $base->where('b.product_id', $productId);
        }

        if ($search !== '') {
            $base->where(function ($query) use ($search) {
                $query->where('b.batch_number', 'like', "%{$search}%")
                    ->orWhere('p.name', 'like', "%{$search}%")
                    ->orWhere('p.name_ar', 'like', "%{$search}%");
            });
        }

        /*
         * الفترات منفصلة:
         * 7 أيام: اليوم وحتى اليوم السابع.
         * 30 يومًا: من اليوم الثامن وحتى اليوم الثلاثين.
         * 60 يومًا: من اليوم الحادي والثلاثين وحتى اليوم الستين.
         */
        $day7 = $today->copy()->addDays(7);
        $day8 = $today->copy()->addDays(8);
        $day30 = $today->copy()->addDays(30);
        $day31 = $today->copy()->addDays(31);
        $day60 = $today->copy()->addDays(60);

        $stats = [
            'expired' => (clone $base)
                ->whereDate('b.expiry_date', '<', $today->toDateString())
                ->count(),

            'days_7' => (clone $base)
                ->whereDate('b.expiry_date', '>=', $today->toDateString())
                ->whereDate('b.expiry_date', '<=', $day7->toDateString())
                ->count(),

            'days_30' => (clone $base)
                ->whereDate('b.expiry_date', '>=', $day8->toDateString())
                ->whereDate('b.expiry_date', '<=', $day30->toDateString())
                ->count(),

            'days_60' => (clone $base)
                ->whereDate('b.expiry_date', '>=', $day31->toDateString())
                ->whereDate('b.expiry_date', '<=', $day60->toDateString())
                ->count(),

            'all' => (clone $base)->count(),
        ];

        $query = (clone $base)->select([
            'b.id',
            'b.product_id',
            'b.location_id',
            'b.goods_receipt_item_id',
            'b.batch_number',
            'b.manufacturing_date',
            'b.expiry_date',
            'b.received_quantity',
            'b.available_quantity',
            'p.name as product_name',
            'l.name as location_name',
        ]);

        /*
         * فلتر الحالة مطابق تمامًا لفترات الإحصائيات.
         */
        if ($status === 'expired') {
            $query->whereDate(
                'b.expiry_date',
                '<',
                $today->toDateString()
            );
        } elseif ($status === '7') {
            $query
                ->whereDate(
                    'b.expiry_date',
                    '>=',
                    $today->toDateString()
                )
                ->whereDate(
                    'b.expiry_date',
                    '<=',
                    $day7->toDateString()
                );
        } elseif ($status === '30') {
            $query
                ->whereDate(
                    'b.expiry_date',
                    '>=',
                    $day8->toDateString()
                )
                ->whereDate(
                    'b.expiry_date',
                    '<=',
                    $day30->toDateString()
                );
        } elseif ($status === '60') {
            $query
                ->whereDate(
                    'b.expiry_date',
                    '>=',
                    $day31->toDateString()
                )
                ->whereDate(
                    'b.expiry_date',
                    '<=',
                    $day60->toDateString()
                );
        }

        $batches = $query
            ->orderBy('b.expiry_date')
            ->paginate(30)
            ->withQueryString();

        $batches->getCollection()->transform(
            function ($batch) use ($today) {
                $expiry = Carbon::parse($batch->expiry_date)->startOfDay();

                $daysLeft = $today->diffInDays($expiry, false);

                $batch->days_left = $daysLeft;

                $batch->expiry_status = match (true) {
                    $daysLeft < 0 => 'expired',
                    $daysLeft <= 7 => 'critical',
                    $daysLeft <= 30 => 'warning',
                    $daysLeft <= 60 => 'soon',
                    default => 'safe',
                };

                return $batch;
            }
        );

        $locations = DB::table('locations')
            ->whereNull('deleted_at')
            ->when(
                ! $request->user()->isAdmin(),
                fn ($query) => $query->where(
                    'id',
                    $request->user()->primaryLocation()?->id ?? 0
                )
            )
            ->orderBy('name')
            ->get(['id', 'name']);

        $products = DB::table('products')
            ->whereNull('deleted_at')
            ->where('tracks_expiry', true)
            ->orderBy('name')
            ->get(['id', 'name', 'name_ar']);

        return view('inventory.expiry.index', compact(
            'batches',
            'stats',
            'locations',
            'products',
            'status',
            'locationId',
            'productId',
            'search'
        ));
    }

    public function print(Request $request): View
    {
        $today = Carbon::today();
        $status = (string) $request->input('status', '');
        $locationId = $request->integer('location_id') ?: null;
        $productId = $request->integer('product_id') ?: null;
        $search = trim((string) $request->input('q', ''));

        $base = DB::table('inventory_batches as b')
            ->leftJoin('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoin('locations as l', 'l.id', '=', 'b.location_id')
            ->whereNotNull('b.expiry_date')
            ->where('b.available_quantity', '>', 0);

        $this->scopeToUserLocation($base, $request);
        $this->applyFilters($base, $locationId, $productId, $search);

        $summary = [
            'expired' => (clone $base)->whereDate('b.expiry_date', '<', $today)->count(),
            'within_7_days' => (clone $base)
                ->whereDate('b.expiry_date', '>=', $today)
                ->whereDate('b.expiry_date', '<=', $today->copy()->addDays(7))->count(),
            'within_30_days' => (clone $base)
                ->whereDate('b.expiry_date', '>=', $today->copy()->addDays(8))
                ->whereDate('b.expiry_date', '<=', $today->copy()->addDays(30))->count(),
            'within_60_days' => (clone $base)
                ->whereDate('b.expiry_date', '>=', $today->copy()->addDays(31))
                ->whereDate('b.expiry_date', '<=', $today->copy()->addDays(60))->count(),
        ];

        $query = (clone $base)->select([
            'b.id', 'b.batch_number', 'b.manufacturing_date', 'b.expiry_date',
            'b.available_quantity', 'p.name as product_name', 'l.name as location_name',
        ]);
        $this->applyStatus($query, $status, $today);

        $rows = $query->orderBy('b.expiry_date')->get()->each(function ($row) use ($today): void {
            $row->days_left = $today->diffInDays(Carbon::parse($row->expiry_date)->startOfDay(), false);
        });

        return view('inventory.expiry.print', compact('rows', 'summary'));
    }

    public function scan(Request $request, InventoryExpiryService $service)
    {
        $result = $service->scanAndNotify(force: true);

        return back()->with(
            'success',
            sprintf(
                'اكتمل فحص الصلاحية: %d دفعة، %d مستحقة للتنبيه، %d إشعار ناجح.',
                (int) ($result['scanned_batches'] ?? 0),
                (int) ($result['qualifying_batches'] ?? 0),
                (int) (($result['database_sent'] ?? 0) + ($result['email_sent'] ?? 0) + ($result['whatsapp_sent'] ?? 0))
            )
        );
    }

    private function scopeToUserLocation($query, Request $request): void
    {
        if (! $request->user()->isAdmin()) {
            $locationId = $request->user()->primaryLocation()?->id;
            abort_unless($locationId, 403, 'لا يوجد موقع رئيسي مرتبط بحسابك.');
            $query->where('b.location_id', $locationId);
        }
    }

    private function applyFilters($query, ?int $locationId, ?int $productId, string $search): void
    {
        if ($locationId) {
            $query->where('b.location_id', $locationId);
        }
        if ($productId) {
            $query->where('b.product_id', $productId);
        }
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('b.batch_number', 'like', "%{$search}%")
                    ->orWhere('p.name', 'like', "%{$search}%")
                    ->orWhere('p.name_ar', 'like', "%{$search}%");
            });
        }
    }

    private function applyStatus($query, string $status, Carbon $today): void
    {
        if ($status === 'expired') {
            $query->whereDate('b.expiry_date', '<', $today);
        } elseif ($status === '7') {
            $query->whereDate('b.expiry_date', '>=', $today)
                ->whereDate('b.expiry_date', '<=', $today->copy()->addDays(7));
        } elseif ($status === '30') {
            $query->whereDate('b.expiry_date', '>=', $today->copy()->addDays(8))
                ->whereDate('b.expiry_date', '<=', $today->copy()->addDays(30));
        } elseif ($status === '60') {
            $query->whereDate('b.expiry_date', '>=', $today->copy()->addDays(31))
                ->whereDate('b.expiry_date', '<=', $today->copy()->addDays(60));
        }
    }
}
