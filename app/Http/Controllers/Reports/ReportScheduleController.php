<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\ReportSchedule;
use App\Services\ReportScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReportScheduleController extends Controller
{
    private array $reportTypes = [
        'orders'          => 'تقرير الطلبات',
        'cake-orders'     => 'تقرير طلبات الكيك الخاصة',
        'inventory'       => 'تقرير المخزون',
        'stock-movements' => 'تقرير حركات المخزون',
        'low-stock'       => 'تقرير المخزون المنخفض',
        'stock-transfers' => 'تقرير التحويلات',
        'payments'        => 'تقرير الدفعات',
        'invoices'        => 'تقرير الفواتير',
        'cash-sessions'   => 'تقرير جلسات الكاشير',
        'daily-sales'     => 'تقرير المبيعات اليومية',
        'monthly-sales'   => 'تقرير المبيعات الشهرية',
        'branch-sales'    => 'تقرير أداء الفروع',
        'product-sales'   => 'تقرير مبيعات المنتجات',
        'collections'     => 'تقرير التحصيلات',
        'outstanding'     => 'تقرير الأرصدة المعلقة',
        'activity-logs'   => 'سجل النشاطات',
    ];

    private array $dateRanges = [
        'today'        => 'اليوم',
        'yesterday'    => 'أمس',
        'last_7_days'  => 'آخر 7 أيام',
        'last_30_days' => 'آخر 30 يوم',
        'this_month'   => 'هذا الشهر',
        'last_month'   => 'الشهر الماضي',
    ];

    public function index(): \Illuminate\View\View
    {
        $user  = Auth::user();
        $query = ReportSchedule::with(['location', 'creator'])->orderByDesc('created_at');

        // Non-admins only see schedules scoped to their primary location.
        if (! $user->isAdmin()) {
            $ownLocation = $user->primaryLocation();
            $query->where('location_id', $ownLocation?->id);
        }

        $schedules = $query->get();

        return view('report-schedules.index', compact('schedules'));
    }

    public function create(): \Illuminate\View\View
    {
        $user = Auth::user();
        // Non-admins can only target their own primary location; admins see all.
        $locations = $user->isAdmin()
            ? Location::orderBy('name')->get()
            : collect([$user->primaryLocation()])->filter();

        $reportTypes = $this->reportTypes;
        $dateRanges  = $this->dateRanges;
        $isAdmin     = $user->isAdmin();

        return view('report-schedules.create', compact('locations', 'reportTypes', 'dateRanges', 'isAdmin'));
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'report_type' => ['required', 'in:' . implode(',', array_keys($this->reportTypes))],
            'frequency'   => ['required', 'in:daily,weekly,monthly'],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'hour'        => ['required', 'integer', 'min:0', 'max:23'],
            'recipients'  => ['required', 'string'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'date_range'  => ['required', 'in:' . implode(',', array_keys($this->dateRanges))],
            'is_active'   => ['boolean'],
        ]);

        // Enforce location scope: non-admins must always filter by their own location.
        if (! $user->isAdmin()) {
            $ownLocation = $user->primaryLocation();
            if (! $ownLocation) {
                return back()->withErrors(['location_id' => 'لا يمكن إنشاء جدول تقرير بدون موقع محدد.'])->withInput();
            }
            // Force the location to the user's own, regardless of submitted value.
            $data['location_id'] = $ownLocation->id;
        }

        // Validate each email in the recipients list
        $emails = array_filter(array_map('trim', explode(',', $data['recipients'])));
        if (empty($emails)) {
            return back()->withErrors(['recipients' => 'يرجى إدخال بريد إلكتروني واحد على الأقل.'])->withInput();
        }
        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return back()->withErrors(['recipients' => "البريد الإلكتروني غير صالح: {$email}"])->withInput();
            }
        }
        $data['recipients'] = implode(', ', $emails);

        // day_of_week only relevant for weekly
        if ($data['frequency'] !== 'weekly') {
            $data['day_of_week'] = null;
        }

        $data['created_by'] = Auth::id();
        $data['is_active']  = $request->boolean('is_active', true);

        ReportSchedule::create($data);

        return redirect()->route('report-schedules.index')
            ->with('success', 'تم إنشاء جدول التقرير بنجاح.');
    }

    public function edit(ReportSchedule $reportSchedule): \Illuminate\View\View
    {
        $this->authorize('update', $reportSchedule);

        $user = Auth::user();
        $locations = $user->isAdmin()
            ? Location::orderBy('name')->get()
            : collect([$user->primaryLocation()])->filter();

        $reportTypes = $this->reportTypes;
        $dateRanges  = $this->dateRanges;
        $isAdmin     = $user->isAdmin();

        return view('report-schedules.edit', compact('reportSchedule', 'locations', 'reportTypes', 'dateRanges', 'isAdmin'));
    }

    public function update(Request $request, ReportSchedule $reportSchedule): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $reportSchedule);

        $user = Auth::user();

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'report_type' => ['required', 'in:' . implode(',', array_keys($this->reportTypes))],
            'frequency'   => ['required', 'in:daily,weekly,monthly'],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'hour'        => ['required', 'integer', 'min:0', 'max:23'],
            'recipients'  => ['required', 'string'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'date_range'  => ['required', 'in:' . implode(',', array_keys($this->dateRanges))],
            'is_active'   => ['boolean'],
        ]);

        // Enforce location scope for non-admins.
        if (! $user->isAdmin()) {
            $ownLocation = $user->primaryLocation();
            if (! $ownLocation) {
                return back()->withErrors(['location_id' => 'لا يمكن حفظ جدول تقرير بدون موقع محدد.'])->withInput();
            }
            $data['location_id'] = $ownLocation->id;
        }

        // Validate each email
        $emails = array_filter(array_map('trim', explode(',', $data['recipients'])));
        if (empty($emails)) {
            return back()->withErrors(['recipients' => 'يرجى إدخال بريد إلكتروني واحد على الأقل.'])->withInput();
        }
        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return back()->withErrors(['recipients' => "البريد الإلكتروني غير صالح: {$email}"])->withInput();
            }
        }
        $data['recipients'] = implode(', ', $emails);

        if ($data['frequency'] !== 'weekly') {
            $data['day_of_week'] = null;
        }

        $data['is_active'] = $request->boolean('is_active', false);

        $reportSchedule->update($data);

        return redirect()->route('report-schedules.index')
            ->with('success', 'تم تحديث جدول التقرير بنجاح.');
    }

    public function destroy(ReportSchedule $reportSchedule): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $reportSchedule);

        $reportSchedule->delete();

        return redirect()->route('report-schedules.index')
            ->with('success', 'تم حذف جدول التقرير.');
    }

    public function toggleActive(ReportSchedule $reportSchedule): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $reportSchedule);

        $reportSchedule->update(['is_active' => ! $reportSchedule->is_active]);

        $label = $reportSchedule->is_active ? 'تفعيل' : 'إيقاف';
        return back()->with('success', "تم {$label} جدول التقرير.");
    }

    public function sendNow(ReportSchedule $reportSchedule, ReportScheduleService $service): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $reportSchedule);

        try {
            $service->send($reportSchedule);
            return redirect()->route('report-schedules.index')
                ->with('success', "تم إرسال التقرير «{$reportSchedule->name}» بنجاح إلى المستلمين.");
        } catch (\Throwable $e) {
            Log::error("Send-now report [{$reportSchedule->id}] failed: " . $e->getMessage(), ['exception' => $e]);
            return redirect()->route('report-schedules.index')
                ->with('error', 'فشل إرسال التقرير، يرجى المحاولة لاحقاً.');
        }
    }

    /**
     * Test-send using draft (unsaved) fields submitted from the create or edit form.
     * Validates the same rules as store/update, builds a temporary unsaved model,
     * and invokes the shared sender. Redirects back with the form values preserved.
     */
    public function testSend(Request $request, ReportScheduleService $service): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'report_type' => ['required', 'in:' . implode(',', array_keys($this->reportTypes))],
            'frequency'   => ['required', 'in:daily,weekly,monthly'],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'hour'        => ['required', 'integer', 'min:0', 'max:23'],
            'recipients'  => ['required', 'string'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'date_range'  => ['required', 'in:' . implode(',', array_keys($this->dateRanges))],
        ]);

        // Enforce location scope for non-admins.
        if (! $user->isAdmin()) {
            $ownLocation = $user->primaryLocation();
            if (! $ownLocation) {
                return back()->withInput()->withErrors(['location_id' => 'لا يمكن إرسال تقرير تجريبي بدون موقع محدد.']);
            }
            $data['location_id'] = $ownLocation->id;
        }

        // Validate emails.
        $emails = array_filter(array_map('trim', explode(',', $data['recipients'])));
        if (empty($emails)) {
            return back()->withInput()->withErrors(['recipients' => 'يرجى إدخال بريد إلكتروني واحد على الأقل.']);
        }
        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return back()->withInput()->withErrors(['recipients' => "البريد الإلكتروني غير صالح: {$email}"]);
            }
        }
        $data['recipients'] = implode(', ', $emails);

        if ($data['frequency'] !== 'weekly') {
            $data['day_of_week'] = null;
        }

        // Build a temporary unsaved schedule — never persisted to the database.
        $schedule = new ReportSchedule($data);

        try {
            $service->send($schedule);
            return back()->withInput()
                ->with('success', 'تم إرسال التقرير التجريبي بنجاح إلى المستلمين المحددين.');
        } catch (\Throwable $e) {
            Log::error('Test-send report failed: ' . $e->getMessage(), ['exception' => $e]);
            return back()->withInput()
                ->with('error', 'فشل إرسال التقرير التجريبي، يرجى المحاولة لاحقاً.');
        }
    }
}
