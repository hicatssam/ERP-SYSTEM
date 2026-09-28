<?php

namespace App\Services\Assistant;

use App\Enums\CakeOrderStatus;
use App\Models\AttendanceRecord;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\IncomingBankTransfer;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomSweetsRequest;
use App\Models\SpecialCakeOrder;
use App\Models\User;
use App\Services\ModuleService;
use App\Services\AttendanceFeatureService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Every query is scoped again after the model chooses an intent. No arbitrary SQL or writes. */
class AssistantReadService
{
    public function __construct(
        private readonly ModuleService $modules,
        private readonly AttendanceFeatureService $attendanceFeatures
    ) {}

    private const PAYMENT_PERMISSIONS = [
        'payments.record', 'payments.verify', 'payments.correct',
        'payments.refund', 'financial.branch.view',
        'financial.global.view', 'financial.collections.view',
    ];

    private const TRANSFER_PERMISSIONS = [
        'payments.record', 'payments.verify', 'financial.branch.view',
        'financial.global.view', 'financial.collections.view',
    ];

    public function suggestions(User $user): array
    {
        return collect([
            ['cake_due', 'كم طلب كيك لازم نجهز اليوم؟'],
            ['cake_top_branch', 'أي فرع طلب أكثر كيك هذا الأسبوع؟'],
            ['branch_cakes', 'ما طلبات كيك الفروع اليوم؟'],
            ['branch_sweets', 'ما طلبات حلويات الفروع اليوم؟'],
            ['low_stock', 'شو المنتجات اللي قربت تخلص؟'],
            ['sales_compare', 'مبيعات اليوم مقارنة بالأمس'],
            ['transfers', 'كم حوالة تنتظر التحقق؟'],
            ['priorities', 'هل عندنا مشاكل تحتاج تدخل الآن؟'],
        ])->filter(fn (array $row) => $this->allowed($user, $row[0]))
            ->map(fn (array $row) => $row[1])->values()->all();
    }

    public function answer(User $user, array $plan): array
    {
        $intent = $plan['intent'] ?? 'unknown';
        if ($intent === 'unknown' || ! in_array($intent, AssistantPlanner::INTENTS, true)) {
            return $this->result('حاليًا أقدر أقرأ وأحلل بيانات النظام فقط. اسألني عن الطلبات، الكيك، المخزون، المبيعات أو الحوالات. أي تعديل يحتاج مرحلة إجراءات منفصلة.');
        }

        abort_unless($this->allowed($user, $intent), 403, 'لا تملك صلاحية الاطلاع على هذه البيانات.');

        $period = $plan['period'] ?? 'none';
        $focus = $plan['focus'] ?? 'summary';
        $hour = $plan['hour'] ?? null;

        return match ($intent) {
            'cake_due' => $this->cakeDue($user, $period, $focus, $hour),
            'cake_top_branch' => $this->cakeTopBranch($user),
            'branch_cakes' => $this->branchRequests($user, $period, true),
            'branch_sweets' => $this->branchRequests($user, $period, false),
            'low_stock' => $this->lowStock($user),
            'orders' => $this->orders($user, $period),
            'sales_compare' => $this->salesCompare($user),
            'payments' => $this->payments($user, $period),
            'invoices' => $this->invoices($user, $period),
            'transfers' => $this->transfers($user, $period),
            'attendance' => $this->attendance($user, $period),
            'payroll' => $this->payroll($user),
            'priorities' => $this->priorities($user),
            'reports' => $this->result('افتح مركز التقارير لاختيار التقرير والفترة المناسبة. أقدر أيضًا ألخّص المبيعات أو الكيك أو المخزون حسب صلاحياتك.', [
                $this->item('فتح التقارير', route('reports.index')),
            ]),
            default => $this->result('لم أفهم السؤال. جرّب صياغة أقصر.'),
        };
    }

    private function allowed(User $user, string $intent): bool
    {
        if ($intent === 'priorities') {
            return collect(['cake_due', 'low_stock', 'transfers', 'branch_cakes', 'branch_sweets'])
                ->contains(fn ($part) => $this->allowed($user, $part));
        }

        $module = match ($intent) {
            'cake_due', 'cake_top_branch', 'branch_cakes' => 'cake_orders',
            'branch_sweets' => 'bakery',
            'low_stock' => 'inventory',
            'orders' => 'sales',
            'sales_compare' => 'finance',
            'payments', 'transfers' => 'payments',
            'invoices' => 'invoices',
            'reports' => 'reports',
            'attendance', 'payroll' => 'employees',
            default => null,
        };

        if ($module === null || ! $this->modules->isEnabled($module)) {
            return false;
        }

        if ($intent === 'attendance' && ! $this->attendanceFeatures->attendanceEnabled()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return match ($intent) {
            'cake_due', 'cake_top_branch' => $user->can('cake_orders.view'),
            'branch_cakes' => $user->can('showroom_cake_requests.view'),
            'branch_sweets' => $user->can('showroom_sweets_requests.view'),
            'low_stock' => $user->can('inventory.view'),
            'orders' => $user->can('orders.view'),
            'sales_compare' => $user->canAny(['financial.dashboard.view', 'financial.sales.view']),
            'payments' => $user->canAny(self::PAYMENT_PERMISSIONS),
            'transfers' => $user->canAny(self::TRANSFER_PERMISSIONS),
            'invoices' => $user->can('invoices.view'),
            'attendance' => $user->can('attendance.view'),
            'payroll' => $user->can('payroll.view'),
            'reports' => $user->can('reports.view'),
            default => false,
        };
    }

    private function locationIds(User $user, bool $global = false): Collection
    {
        return ($user->isAdmin() || ($global && $user->can('financial.global.view')))
            ? Location::query()->pluck('id')->map(fn ($id) => (int) $id)
            : collect([$user->primaryLocation()?->id])->filter()->map(fn ($id) => (int) $id);
    }

    private function assignedLocationIds(User $user): Collection
    {
        return $user->employee?->locations()->pluck('locations.id')
            ->map(fn ($id) => (int) $id) ?? collect();
    }

    private function cakes(User $user, bool $activeOnly = true): Builder
    {
        $active = collect(CakeOrderStatus::cases())
            ->reject(fn ($status) => $status->isTerminal() || $status === CakeOrderStatus::Draft)
            ->map(fn ($status) => $status->value)->all();

        $query = SpecialCakeOrder::query();
        if ($activeOnly) {
            $query->whereIn('status', $active);
        }

        if (! $user->isAdmin() && ! $user->can('cake_orders.view_all')) {
            $ids = $this->assignedLocationIds($user)->all();
            $query->where(fn (Builder $q) => $q->whereIn('origin_branch_id', $ids)
                ->orWhereIn('factory_location_id', $ids));
        }

        return $query;
    }

    private function cakeDue(User $user, string $period, string $focus, ?int $hour): array
    {
        if ($hour !== null && $hour >= 1 && $hour <= 12) {
            return $this->result("تقصد الساعة {$hour} صباحًا أم مساءً؟");
        }

        $query = $this->cakes($user);
        if ($focus === 'nearest' && $period === 'none') {
            $query->whereNotNull('required_time')
                ->where(fn (Builder $q) => $q->whereDate('required_date', '>', today())
                ->orWhere(fn (Builder $today) => $today
                    ->whereDate('required_date', today())
                    ->where('required_time', '>=', now()->format('H:i:s'))));
        } else {
            [$from, $to] = $this->dates($period);
            $query->whereDate('required_date', '>=', $from->toDateString())
                ->whereDate('required_date', '<=', $to->toDateString());
        }

        if ($hour !== null) {
            $query->where('required_time', 'like', sprintf('%02d:', $hour).'%');
        }

        $count = (clone $query)->count();
        $filtered = clone $query;
        $orders = $query->orderBy('required_date')
            ->orderByRaw('required_time IS NULL')
            ->orderBy('required_time')->orderBy('id')->limit(8)->get();
        $cards = $orders->map(fn (SpecialCakeOrder $order) => $this->item(
            'طلب '.$order->order_number,
            route('cake-orders.show', $order),
            $order->required_date?->format('Y-m-d').' '.substr((string) $order->required_time, 0, 5)
        ))->all();

        if ($focus === 'nearest') {
            return $this->result($count ? 'أقرب طلب كيك للتسليم: '.$orders->first()->order_number.'، موعده '.$orders->first()->required_date?->format('Y-m-d').' '.substr((string) $orders->first()->required_time, 0, 5).'.' : 'لا يوجد طلب كيك قادم بوقت تسليم محدد.', $cards);
        }

        $label = $this->periodLabel($period);
        $message = "عدد طلبات الكيك النشطة {$label}: {$count}.";
        if ($hour !== null) {
            $message .= ' عند الساعة '.sprintf('%02d:00', $hour).'.';
        } elseif (in_array($period, ['today', 'none'], true) && $count > 0) {
            $now = now()->format('H:i:s');
            $deadline = now()->addHours(2);
            $limit = $deadline->isSameDay(now()) ? $deadline->format('H:i:s') : '23:59:59';
            $overdue = (clone $filtered)->whereNotNull('required_time')
                ->where('required_time', '<', $now)->count();
            $soon = (clone $filtered)->whereNotNull('required_time')
                ->where('required_time', '>=', $now)
                ->where('required_time', '<=', $limit)->count();
            $message .= " مواعيد فاتت: {$overdue}؛ خلال ساعتين: {$soon}.";
        }
        if ($count > 8) {
            $message .= ' أعرض أول 8 حسب موعد التسليم.';
        }

        return $this->result($message, $cards);
    }

    private function cakeTopBranch(User $user): array
    {
        [$from, $to] = $this->dates('week');
        $rows = $this->cakes($user, false)->whereBetween('created_at', [$from, $to])
            ->selectRaw('origin_branch_id, COUNT(*) as requests_count')
            ->groupBy('origin_branch_id')->orderByDesc('requests_count')->limit(5)->get();
        $names = Location::query()->whereIn('id', $rows->pluck('origin_branch_id'))->pluck('name', 'id');
        $top = $rows->first();

        return $this->result($top
            ? 'أكثر فرع طلب كيك هذا الأسبوع ضمن الطلبات المتاحة لك: '.($names[$top->origin_branch_id] ?? 'فرع غير معروف').' ('.$top->requests_count.' طلب).'
            : 'لا توجد طلبات كيك لهذا الأسبوع ضمن الفروع المتاحة لك.',
            $rows->map(fn ($row) => $this->item(($names[$row->origin_branch_id] ?? 'فرع').' · '.$row->requests_count.' طلب', route('cake-orders.index')))->all());
    }

    private function branchRequests(User $user, string $period, bool $cakes): array
    {
        $model = $cakes ? ShowroomCakeRequest::class : ShowroomSweetsRequest::class;
        $permission = $cakes ? 'showroom_cake_requests.view_all' : 'showroom_sweets_requests.view_all';
        $route = $cakes ? 'showroom-cake-requests.show' : 'showroom-sweets-requests.show';
        $label = $cakes ? 'كيك الفروع' : 'حلويات الفروع';
        [$from, $to] = $this->dates($period);
        $query = $model::query()->whereDate('needed_by', '>=', $from->toDateString())
            ->whereDate('needed_by', '<=', $to->toDateString())
            ->whereNotIn('status', ['completed', 'cancelled', 'fulfilled', 'rejected']);

        if (! $user->isAdmin() && ! $user->can($permission)) {
            $ids = $this->assignedLocationIds($user)->all();
            $query->where(fn (Builder $q) => $q->whereIn('requesting_location_id', $ids)
                ->orWhereIn('factory_location_id', $ids));
        }

        $count = (clone $query)->count();
        $rows = $query->orderBy('needed_by')->limit(8)->get();

        return $this->result("طلبات {$label} النشطة {$this->periodLabel($period)}: {$count}.",
            $rows->map(fn ($row) => $this->item($row->request_number, route($route, $row), $row->needed_by?->format('Y-m-d')))->all());
    }

    private function lowStock(User $user): array
    {
        $query = LocationProduct::query()
            ->whereIn('location_products.location_id', $this->locationIds($user)->all())
            ->where('location_products.minimum_stock_level', '>', 0)
            ->leftJoin('inventories as stock', fn ($join) => $join
                ->on('stock.location_id', '=', 'location_products.location_id')
                ->on('stock.product_id', '=', 'location_products.product_id'))
            ->whereRaw('COALESCE(stock.quantity, 0) - COALESCE(stock.reserved_quantity, 0) <= location_products.minimum_stock_level');
        $count = (clone $query)->count();
        $rows = $query->with(['product:id,name,name_ar', 'location:id,name'])
            ->select('location_products.*')
            ->selectRaw('COALESCE(stock.quantity, 0) - COALESCE(stock.reserved_quantity, 0) as available_stock')
            ->limit(10)->get();

        return $this->result("المنتجات عند الحد الأدنى أو أقل: {$count}.",
            $rows->map(fn ($row) => $this->item(
                ($row->product?->name_ar ?: $row->product?->name ?: 'منتج').' · '.($row->location?->name ?: 'فرع'),
                route('inventory.index', ['location_id' => $row->location_id]),
                'المتاح '.number_format(max(0, (float) $row->available_stock), 3).' / الحد '.number_format((float) $row->minimum_stock_level, 3)
            ))->all());
    }

    private function orders(User $user, string $period): array
    {
        [$from, $to] = $this->dates($period);
        $query = Order::query()->whereIn('location_id', $this->locationIds($user)->all())
            ->whereBetween('created_at', [$from, $to]);
        $count = (clone $query)->count();
        $rows = $query->latest()->limit(8)->get();

        return $this->result("الطلبات {$this->periodLabel($period)}: {$count}.",
            $rows->map(fn (Order $order) => $this->item($order->order_number, route('orders.show', $order), $order->statusValue()))->all());
    }

    private function salesCompare(User $user): array
    {
        $ids = $this->locationIds($user, true)->all();
        $total = fn (Carbon $date) => (float) Invoice::query()
            ->whereIn('location_id', $ids)->where('status', 'active')
            ->whereDate('issued_at', $date->toDateString())->sum('total_amount');
        $today = $total(today());
        $yesterday = $total(today()->subDay());
        $difference = $today - $yesterday;

        return $this->result('مبيعات اليوم من الفواتير النشطة: '.$this->money($today).'، أمس: '.$this->money($yesterday).'، الفرق: '.$this->money($difference).'.',
            $user->can('reports.view') ? [$this->item('فتح التقارير', route('reports.index'))] : []);
    }

    private function payments(User $user, string $period): array
    {
        [$from, $to] = $this->dates($period);
        $base = Payment::query()->whereIn('location_id', $this->locationIds($user, true)->all());
        $confirmed = (clone $base)->where('status', 'confirmed')
            ->whereBetween('paid_at', [$from, $to])->sum('amount');
        $pending = (clone $base)->where('status', 'pending_verification')
            ->whereBetween('created_at', [$from, $to])->count();

        return $this->result('التحصيلات المؤكدة '.$this->periodLabel($period).': '.$this->money((float) $confirmed).'؛ دفعات تنتظر التحقق: '.$pending.'.',
            [$this->item('فتح المدفوعات', route('payments.index'))]);
    }

    private function invoices(User $user, string $period): array
    {
        [$from, $to] = $this->dates($period);
        $query = Invoice::query()->whereIn('location_id', $this->locationIds($user, true)->all())
            ->where('status', 'active')->whereBetween('issued_at', [$from, $to]);
        $count = (clone $query)->count();
        $remaining = (clone $query)->sum('remaining_amount');

        return $this->result("الفواتير النشطة {$this->periodLabel($period)}: {$count}؛ المبلغ المتبقي: ".$this->money((float) $remaining).'.',
            [$this->item('فتح الفواتير', route('invoices.index'))]);
    }

    private function transfers(User $user, string $period): array
    {
        $query = IncomingBankTransfer::query()->whereIn('location_id', $this->locationIds($user, true)->all());
        // A pending inbox is a current-state question; historical totals use the selected period.
        if ($period !== 'none') {
            [$from, $to] = $this->dates($period);
            $query->whereBetween('received_at', [$from, $to]);
        }
        $pending = (clone $query)->where('status', 'pending_verification');
        $count = (clone $pending)->count();
        $amounts = (clone $pending)
            ->selectRaw('currency_code, SUM(amount) as total_amount')
            ->groupBy('currency_code')->get()
            ->map(fn ($row) => $this->money((float) $row->total_amount, $row->currency_code))
            ->implode('، ');

        return $this->result('الحوالات المنتظرة للتحقق'.($period === 'none' ? '' : ' '.$this->periodLabel($period)).': '.$count.'، بإجمالي '.($amounts ?: '0').'.',
            [$this->item('فتح الحوالات', route('incoming-bank-transfers.index'))]);
    }

    private function attendance(User $user, string $period): array
    {
        [$from, $to] = $this->dates($period);
        $employees = Employee::query()->select('employees.id');
        if (! $user->isAdmin()) {
            $locationId = $user->primaryLocation()?->id ?? -1;
            $employees->whereHas('employeeLocations', fn (Builder $q) => $q
                ->where('location_id', $locationId)
                ->where('is_primary', true)
                ->whereNull('ended_at'));
        }
        $query = AttendanceRecord::query()
            ->whereIn('employee_id', $employees)
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString());
        $present = (clone $query)->where('status', 'present')->count();
        $late = (clone $query)->where('late_minutes', '>', 0)->count();

        return $this->result("سجلات الحضور {$this->periodLabel($period)}: {$present} حاضر؛ {$late} تأخروا.",
            [$this->item('فتح الحضور', route('attendance.index'))]);
    }

    private function payroll(User $user): array
    {
        $query = PayrollPeriod::query();
        if (! $user->isAdmin()) {
            $query->where('location_id', $user->primaryLocation()?->id ?? -1);
        }
        $period = $query->latest('start_date')->first();
        if (! $period) {
            return $this->result('لا توجد دورة رواتب متاحة لك.', [$this->item('فتح الرواتب', route('payroll.index'))]);
        }
        $items = PayrollItem::query()->where('payroll_period_id', $period->id);

        $currency = Currency::query()->whereKey($period->currency_id)->value('code');
        return $this->result('آخر دورة رواتب متاحة: '.$period->name.'؛ الحالة: '.$period->status.'؛ الموظفون: '.(clone $items)->count().'؛ صافي الرواتب: '.$this->money((float) $items->sum('net_salary'), $currency).'.',
            [$this->item('فتح دورة الرواتب', route('payroll.show', $period))]);
    }

    private function priorities(User $user): array
    {
        $parts = [];
        $links = [];
        if ($this->allowed($user, 'cake_due')) {
            $late = $this->cakes($user)->whereDate('required_date', '<', today())->count();
            $today = $this->cakes($user)->whereDate('required_date', today())->count();
            $parts[] = "كيك متأخر: {$late}، مطلوب اليوم: {$today}";
            $links[] = $this->item('طلبات الكيك', route('cake-orders.index'));
        }
        if ($this->allowed($user, 'low_stock')) {
            $stock = $this->lowStock($user);
            $parts[] = $stock['message'];
            $links[] = $this->item('المخزون', route('inventory.index'));
        }
        if ($this->allowed($user, 'transfers')) {
            $count = IncomingBankTransfer::query()
                ->whereIn('location_id', $this->locationIds($user, true)->all())
                ->where('status', 'pending_verification')->count();
            $parts[] = "حوالات تنتظر التحقق: {$count}";
            $links[] = $this->item('الحوالات', route('incoming-bank-transfers.index'));
        }

        foreach ([['branch_cakes', true, 'طلبات كيك الفروع'], ['branch_sweets', false, 'طلبات حلويات الفروع']] as [$intent, $cakes, $label]) {
            if ($this->allowed($user, $intent)) {
                $parts[] = $this->branchRequests($user, 'today', $cakes)['message'];
                $links[] = $this->item($label, route($cakes ? 'showroom-cake-requests.index' : 'showroom-sweets-requests.index'));
            }
        }

        return $this->result(implode('؛ ', $parts).'.', $links);
    }

    private function dates(string $period): array
    {
        $day = today();
        return match ($period) {
            'yesterday' => [$day->copy()->subDay()->startOfDay(), $day->copy()->subDay()->endOfDay()],
            'tomorrow' => [$day->copy()->addDay()->startOfDay(), $day->copy()->addDay()->endOfDay()],
            'week' => [$day->copy()->startOfWeek()->startOfDay(), $day->copy()->endOfWeek()->endOfDay()],
            default => [$day->copy()->startOfDay(), $day->copy()->endOfDay()],
        };
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'yesterday' => 'أمس',
            'tomorrow' => 'غدًا',
            'week' => 'هذا الأسبوع',
            default => 'اليوم',
        };
    }

    private function item(string $label, string $url, ?string $meta = null): array
    {
        return compact('label', 'url', 'meta');
    }

    private function result(string $message, array $items = []): array
    {
        return compact('message', 'items');
    }

    private function money(float $amount, ?string $currency = null): string
    {
        $code = $currency ?: Currency::query()->where('is_base', true)->value('code');

        return trim(number_format($amount, 2).' '.($code ?: ''));
    }
}
