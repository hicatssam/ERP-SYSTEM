<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Exports\ReportExport;
use App\Exports\ChunkedQueryReportExport;
use App\Models\Order;
use App\Models\SpecialCakeOrder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\CashSession;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Location;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;

class ReportController extends Controller
{
    private array $reportTypes = [
        'orders'                => 'تقرير الطلبات',
        'cake-orders'           => 'تقرير طلبات الكيك الخاصة',
        'cake-production'       => 'تقرير إنتاج الكيك الموحد',
        'inventory'             => 'تقرير المخزون',
        'stock-movements'       => 'تقرير حركات المخزون',
        'low-stock'             => 'تقرير المخزون المنخفض',
        'stock-transfers'       => 'تقرير التحويلات',
        'payments'              => 'تقرير الدفعات',
        'invoices'              => 'تقرير الفواتير',
        'cash-sessions'         => 'تقرير جلسات الكاشير',
        'daily-sales'           => 'تقرير المبيعات اليومية',
        'monthly-sales'         => 'تقرير المبيعات الشهرية',
        'branch-sales'          => 'تقرير أداء الفروع',
        'product-sales'         => 'تقرير مبيعات المنتجات',
        'collections'           => 'تقرير التحصيلات',
        'outstanding'           => 'تقرير الأرصدة المعلقة',
        'activity-logs'         => 'سجل النشاطات',
        'payment-method-sales'  => 'المبيعات حسب طريقة الدفع',
        'sales-channel-sales'   => 'المبيعات حسب قناة البيع',
    ];

    private array $reportIcons = [
        'orders'                => 'clipboard',
        'cake-orders'           => 'cake',
        'cake-production'       => 'layers',
        'inventory'             => 'box',
        'stock-movements'       => 'transfer',
        'low-stock'             => 'warning',
        'stock-transfers'       => 'truck',
        'payments'              => 'credit-card',
        'invoices'              => 'file-text',
        'cash-sessions'         => 'dollar',
        'daily-sales'           => 'trending-up',
        'monthly-sales'         => 'bar-chart',
        'branch-sales'          => 'map-pin',
        'product-sales'         => 'package',
        'collections'           => 'check-circle',
        'outstanding'           => 'clock',
        'activity-logs'         => 'activity',
        'payment-method-sales'  => 'credit-card',
        'sales-channel-sales'   => 'layers',
    ];

    public function index()
    {
        return view('reports.index', [
            'reportTypes' => $this->reportTypes,
            'reportIcons' => $this->reportIcons,
        ]);
    }

    public function show(Request $request, string $type)
    {
        if (! array_key_exists($type, $this->reportTypes)) {
            abort(404);
        }

        $user       = Auth::user();
        $title      = $this->reportTypes[$type];
        $dateFrom   = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo     = $request->input('date_to',   now()->toDateString());
        $locationId = $request->input('location_id');

        $locationIds = $this->resolveLocationIds(
            $user,
            $locationId,
            $type
        );

        $locations = $type === 'cake-production'
            ? (
                $this->canViewAllCakeProductionBranches($user)
                    ? Location::query()
                        ->where('type', 'branch')
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get()
                    : collect()
            )
            : (
                $user->isAdmin()
                    ? Location::orderBy('name')->get()
                    : collect()
            );

        [$columns, $data, $summary] = $this->buildReportData($type, $locationIds, $dateFrom, $dateTo, true);

        return view('reports.show', compact(
            'type', 'title', 'data', 'columns', 'summary',
            'dateFrom', 'dateTo', 'locations', 'locationId'
        ));
    }

    /** Maximum rows written to an Excel export (configurable via EXPORT_XLSX_ROW_CAP). */
    private const XLSX_ROW_CAP = 10000;

    /** Maximum rows written to a PDF export (configurable via EXPORT_PDF_ROW_CAP). */
    private const PDF_ROW_CAP = 500;

    // ─────────────────────────────────────────────────────────────────────────
    //  Export: Excel  (chunked via FromQuery — does not load all rows at once)
    // ─────────────────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────────────────
    //  Row-count API  (lightweight — single COUNT(*) only)
    // ─────────────────────────────────────────────────────────────────────────

    public function count(Request $request, string $type)
    {
        if (! array_key_exists($type, $this->reportTypes)) {
            abort(404);
        }

        $user       = Auth::user();
        $dateFrom   = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo     = $request->input('date_to',   now()->toDateString());
        $locationId = $request->input('location_id');

        $locationIds = $this->resolveLocationIds($user, $locationId, $type);

        [, $query] = $this->buildExportQuery($type, $locationIds, $dateFrom, $dateTo);

        $total = $this->countExportRows(clone $query);

        return response()->json([
            'count'            => $total,
            'xlsx_cap'         => (int) env('EXPORT_XLSX_ROW_CAP', self::XLSX_ROW_CAP),
            'pdf_cap'          => (int) env('EXPORT_PDF_ROW_CAP',  self::PDF_ROW_CAP),
            'exceeds_xlsx_cap' => $total > (int) env('EXPORT_XLSX_ROW_CAP', self::XLSX_ROW_CAP),
            'exceeds_pdf_cap'  => $total > (int) env('EXPORT_PDF_ROW_CAP',  self::PDF_ROW_CAP),
        ]);
    }

    public function exportXlsx(Request $request, string $type)
    {
        if (! array_key_exists($type, $this->reportTypes)) {
            abort(404);
        }

        $user       = Auth::user();
        $dateFrom   = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo     = $request->input('date_to',   now()->toDateString());
        $locationId = $request->input('location_id');
        $title      = $this->reportTypes[$type];

        $locationIds = $this->resolveLocationIds($user, $locationId, $type);

        $cap = (int) (env('EXPORT_XLSX_ROW_CAP', self::XLSX_ROW_CAP));

        [$columns, $query] = $this->buildExportQuery($type, $locationIds, $dateFrom, $dateTo);

        // Count the number of output rows (groups for aggregate reports, records otherwise).
        // The actual cap is enforced inside ChunkedQueryReportExport via lazy()->take($cap).
        $total     = $this->countExportRows(clone $query);
        $truncated = $total > $cap;

        $mapper   = fn ($row) => $this->formatRowForExport($type, $row);
        $filename = "{$type}_{$dateFrom}_{$dateTo}.xlsx";

        return Excel::download(
            new ChunkedQueryReportExport($query, $columns, $title, $mapper, $cap, $truncated),
            $filename
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Export: PDF  (capped at PDF_ROW_CAP rows to keep files manageable)
    // ─────────────────────────────────────────────────────────────────────────

    public function exportPdf(Request $request, string $type)
    {
        if (! array_key_exists($type, $this->reportTypes)) {
            abort(404);
        }

        $user       = Auth::user();
        $dateFrom   = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo     = $request->input('date_to',   now()->toDateString());
        $locationId = $request->input('location_id');
        $title      = $this->reportTypes[$type];

        $locationIds = $this->resolveLocationIds($user, $locationId, $type);

        $cap = (int) env('EXPORT_PDF_ROW_CAP', self::PDF_ROW_CAP);

        [$columns, $query] = $this->buildExportQuery(
            $type,
            $locationIds,
            $dateFrom,
            $dateTo
        );

        $summary = $this->buildReportSummary(
            $type,
            $locationIds,
            $dateFrom,
            $dateTo
        );

        $total     = $this->countExportRows(clone $query);
        $truncated = $total > $cap;

        $rows = $query
            ->lazy()
            ->take($cap)
            ->collect();

        $html = view('pdf.reports.template', compact(
            'type',
            'title',
            'rows',
            'columns',
            'summary',
            'dateFrom',
            'dateTo',
            'truncated',
            'cap',
            'total'
        ))->render();

        $tempDir = storage_path('app/mpdf');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($tempDir);

        $mpdf = new Mpdf([
            'mode'             => 'utf-8',
            'format'           => 'A4-L',
            'margin_top'       => 12,
            'margin_bottom'    => 12,
            'margin_left'      => 12,
            'margin_right'     => 12,
            'directionality'   => 'rtl',
            'default_font'     => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont'   => true,
            'tempDir'          => $tempDir,
        ]);

        $mpdf->SetTitle($title);
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->WriteHTML($html);

        $filename = "{$type}_{$dateFrom}_{$dateTo}.pdf";
        $pdf = $mpdf->Output(
            '',
            \Mpdf\Output\Destination::STRING_RETURN
        );

        if (
            ! str_starts_with($pdf, '%PDF')
            || strlen($pdf) < 1000
            || ! preg_match('/\/Type\s*\/Page\b/', $pdf)
        ) {
            throw new \RuntimeException(
                'تعذر إنشاء ملف PDF صالح للتقرير.'
            );
        }

        return response()->streamDownload(
            static function () use ($pdf): void {
                echo $pdf;
            },
            $filename,
            [
                'Content-Type'           => 'application/pdf',
                'Content-Length'         => (string) strlen($pdf),
                'Cache-Control'          => 'private, no-store, max-age=0',
                'Pragma'                 => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }


    /**
     * طباعة التقرير كامل في قالب HTML مخصص للطباعة.
     * هذا المسار مستقل عن قالب mPDF حتى تكون طباعة المتصفح مرتبة على A4.
     */
    /**
     * طباعة التقرير كامل في قالب HTML مخصص للطباعة.
     *
     * نستخدم نفس buildReportData المستخدم في شاشة التقرير حتى تكون
     * بيانات الشاشة والطباعة متطابقة، خصوصاً في التقارير المجمعة
     * مثل daily-sales و monthly-sales و branch-sales.
     */
    public function printReport(Request $request, string $type)
    {
        if (! array_key_exists($type, $this->reportTypes)) {
            abort(404);
        }

        $user       = Auth::user();
        $dateFrom   = $request->input(
            'date_from',
            now()->startOfMonth()->toDateString()
        );
        $dateTo     = $request->input(
            'date_to',
            now()->toDateString()
        );
        $locationId = $request->input('location_id');
        $title      = $this->reportTypes[$type];

        $locationIds = $this->resolveLocationIds(
            $user,
            $locationId,
            $type
        );

        /*
         * نفس مصدر بيانات صفحة التقرير.
         * paginate = false => نحصل على كامل الصفوف للطباعة.
         */
        [$columns, $rows, $summary] = $this->buildReportData(
            $type,
            $locationIds,
            $dateFrom,
            $dateTo,
            false
        );

        /*
         * وحّد نوع البيانات دائماً إلى Collection.
         */
        $rows = collect($rows)->values();

        /*
         * في الطباعة العادية لا يوجد truncation.
         */
        $total     = $rows->count();
        $truncated = false;
        $cap       = $total;

        return view('reports.print', compact(
            'type',
            'title',
            'rows',
            'columns',
            'summary',
            'dateFrom',
            'dateTo',
            'truncated',
            'cap',
            'total'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveLocationIds(
        $user,
        ?string $locationId,
        ?string $reportType = null
    ): Collection {
        if (
            $reportType === 'cake-production'
            && $this->canViewAllCakeProductionBranches($user)
        ) {
            $branchIds = Location::query()
                ->where('type', 'branch')
                ->where('is_active', true)
                ->pluck('id');

            if (
                $locationId
                && $branchIds->contains(
                    (int) $locationId
                )
            ) {
                return collect([
                    (int) $locationId,
                ]);
            }

            return $branchIds;
        }

        $locationIds = $user->isAdmin()
            ? Location::pluck('id')
            : collect([
                $user->primaryLocation()?->id,
            ])->filter();

        if ($locationId && $user->isAdmin()) {
            $locationIds = collect([
                (int) $locationId,
            ]);
        }

        return $locationIds;
    }

    private function canViewAllCakeProductionBranches(
        User $user
    ): bool {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->can('cake_orders.view_all')
            || $user->can('cake_orders.review')
            || $user->can('cake_orders.accept')
            || $user->can('cake_orders.prepare')
            || $user->can('cake_orders.decorate')
            || $user->can('cake_orders.quality_check')
            || $user->can('cake_orders.dispatch');
    }


    /**
     * Return the correct year-month SQL expression for the active database.
     * Production uses MySQL while automated tests may use SQLite.
     */
    private function yearMonthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            default  => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * Base query for payment/collection reports.
     *
     * Payment has no invoice_id / invoice relationship. The financial source is
     * linked to invoices by the composite key: order_type + order_id.
     * Scalar subqueries keep the report query eager-load safe and avoid N+1.
     */
    private function paymentReportQuery(
    Collection $locationIds,
    string $dateFrom,
    string $end,
    bool $confirmedOnly = false
): \Illuminate\Database\Eloquent\Builder {

    /*
    |--------------------------------------------------------------------------
    | Latest invoice per financial source
    |--------------------------------------------------------------------------
    |
    | Payment -> Invoice:
    |
    | order_type + order_id
    |
    | نأخذ آخر فاتورة فقط لكل مصدر حتى لا تتكرر الدفعة
    | إذا وُجد أكثر من Invoice لنفس الطلب.
    |--------------------------------------------------------------------------
    */
    $invoiceLinks = DB::table('invoices')
        ->selectRaw(
            '
                MAX(id) as invoice_id,
                order_type,
                order_id
            '
        )
        ->groupBy(
            'order_type',
            'order_id'
        );

    $query = Payment::query()

        /*
        |--------------------------------------------------------------------------
        | Invoice composite-key join
        |--------------------------------------------------------------------------
        */
        ->leftJoinSub(
            $invoiceLinks,
            'report_invoice_links',
            function ($join): void {

                $join
                    ->on(
                        'report_invoice_links.order_type',
                        '=',
                        'payments.order_type'
                    )
                    ->on(
                        'report_invoice_links.order_id',
                        '=',
                        'payments.order_id'
                    );
            }
        )

        /*
        |--------------------------------------------------------------------------
        | Actual invoice
        |--------------------------------------------------------------------------
        */
        ->leftJoin(
            'invoices as report_invoices',
            'report_invoices.id',
            '=',
            'report_invoice_links.invoice_id'
        )

        /*
        |--------------------------------------------------------------------------
        | Invoice customer
        |--------------------------------------------------------------------------
        */
        ->leftJoin(
            'customers as report_customers',
            'report_customers.id',
            '=',
            'report_invoices.customer_id'
        )

        /*
        |--------------------------------------------------------------------------
        | Normal Payment relations
        |--------------------------------------------------------------------------
        */
        ->with([
            'location',
            'paymentMethod',
        ])

        /*
        |--------------------------------------------------------------------------
        | Payment + report fields
        |--------------------------------------------------------------------------
        */
        ->select([
            'payments.*',

            'report_invoices.id
                as report_invoice_id',

            'report_invoices.invoice_number
                as report_invoice_number',

            'report_customers.name
                as report_customer_name',
        ])

        ->whereIn(
            'payments.location_id',
            $locationIds
        )

        ->whereBetween(
            'payments.paid_at',
            [
                $dateFrom,
                $end,
            ]
        );

    /*
    |--------------------------------------------------------------------------
    | Collections only confirmed
    |--------------------------------------------------------------------------
    */
    if ($confirmedOnly) {

        $query->where(
            'payments.status',
            'confirmed'
        );
    }

    return $query;
}

    /**
     * Return the number of rows that the export query will produce.
     *
     * Calling ->count() directly on a grouped query (daily-sales, monthly-sales,
     * branch-sales, product-sales) returns a wrong value because Laravel's
     * aggregate() call keeps the GROUP BY clause, yielding the count of the first
     * group rather than the number of groups. Wrapping in a subquery fixes this for
     * all cases — grouped and non-grouped alike.
     */
    private function countExportRows(
        \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
    ): int {
        // Normalise to a base query builder so fromSub always works.
        $base = $query instanceof \Illuminate\Database\Eloquent\Builder
            ? $query->toBase()
            : $query;

        return (int) DB::query()
            ->fromSub($base->reorder(), 'sub')
            ->count();
    }

    /**
     * Return the raw (un-executed) query builder and column headings for an export.
     * Used by both exportXlsx() and exportPdf() so they can apply limits before
     * executing, keeping peak memory proportional to the cap rather than the full
     * dataset.
     *
     * Every query MUST have a fully deterministic ORDER BY so that offset-based
     * lazy() pagination produces a stable, non-overlapping result set. For simple
     * queries this means a primary timestamp sort PLUS a secondary ->orderByDesc('id')
     * tiebreaker. For grouped aggregate queries the group key is already unique per
     * row, so a single sort on the group key is sufficient.
     *
     * @return array{0: array, 1: \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder}
     */
    private function buildExportQuery(
        string $type,
        Collection $locationIds,
        string $dateFrom,
        string $dateTo,
    ): array {
        $columns = [];
        $end     = $dateTo . ' 23:59:59';

        switch ($type) {
            case 'orders':
                $columns = ['#', 'العميل', 'الفرع', 'المبلغ', 'الحالة', 'التاريخ'];
                $query   = Order::with(['customer', 'location'])
                    ->whereIn('location_id', $locationIds)
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest()
                    ->orderByDesc('id'); // stable tiebreaker
                break;

            case 'invoices':
                $columns = ['#', 'العميل', 'الفرع', 'الإجمالي', 'المدفوع', 'المتبقي', 'الحالة', 'التاريخ'];
                $query   = Invoice::with(['customer', 'location'])
                    ->whereIn('location_id', $locationIds)
                    ->whereBetween('issued_at', [$dateFrom, $end])
                    ->latest('issued_at')
                    ->orderByDesc('id');
                break;

            case 'payments':
                $columns = ['#', 'الفاتورة', 'الفرع', 'المبلغ', 'الطريقة', 'الحالة', 'التاريخ'];
                $query = $this->paymentReportQuery(
                    $locationIds,
                    $dateFrom,
                    $end
                )
                    ->latest('payments.paid_at')
                    ->orderByDesc('payments.id');
                break;

            case 'daily-sales':
                // Grouped by unique date — no tiebreaker needed; group key is deterministic.
                $columns = ['التاريخ', 'عدد الفواتير', 'إجمالي المبيعات ₪', 'إجمالي التحصيلات ₪'];
                $query   = Invoice::whereIn('location_id', $locationIds)
                    ->where('status', 'active')
                    ->whereBetween('issued_at', [$dateFrom, $end])
                    ->select(
                        DB::raw('DATE(issued_at) as sale_date'),
                        DB::raw('COUNT(*) as invoice_count'),
                        DB::raw('SUM(total_amount) as total_sales'),
                        DB::raw('SUM(paid_amount) as total_paid')
                    )
                    ->groupBy('sale_date')
                    ->orderByDesc('sale_date');
                break;

            case 'monthly-sales':
                // Grouped by unique year-month — no tiebreaker needed.
                $columns = ['الشهر', 'عدد الفواتير', 'إجمالي المبيعات ₪', 'إجمالي التحصيلات ₪'];
                $query   = Invoice::whereIn('location_id', $locationIds)
                    ->where('status', 'active')
                    ->whereBetween('issued_at', [$dateFrom, $end])
                    ->select(
                        DB::raw($this->yearMonthExpression('issued_at') . ' as sale_month'),
                        DB::raw('COUNT(*) as invoice_count'),
                        DB::raw('SUM(total_amount) as total_sales'),
                        DB::raw('SUM(paid_amount) as total_paid')
                    )
                    ->groupBy('sale_month')
                    ->orderByDesc('sale_month');
                break;

            case 'branch-sales':
                // Grouped by location_id (unique per group); tiebreaker on branch name.
                $columns = ['الفرع', 'عدد الفواتير', 'إجمالي المبيعات ₪', 'إجمالي التحصيلات ₪'];
                $query   = Invoice::whereIn('location_id', $locationIds)
                    ->where('status', 'active')
                    ->whereBetween('issued_at', [$dateFrom, $end])
                    ->join('locations', 'locations.id', '=', 'invoices.location_id')
                    ->select(
                        'locations.name as branch_name',
                        DB::raw('COUNT(*) as invoice_count'),
                        DB::raw('SUM(invoices.total_amount) as total_sales'),
                        DB::raw('SUM(invoices.paid_amount) as total_paid')
                    )
                    ->groupBy('invoices.location_id', 'locations.name')
                    ->orderByDesc('total_sales')
                    ->orderBy('branch_name'); // stable tiebreaker
                break;

            case 'product-sales':
                // Grouped by product_id (unique per group); tiebreaker on product name.
                $columns = ['المنتج', 'الكمية المباعة', 'إجمالي المبيعات ₪'];
                $query   = DB::table('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->whereIn('orders.location_id', $locationIds->toArray())
                    ->whereBetween('orders.created_at', [$dateFrom, $end])
                    ->where('orders.status', '!=', 'cancelled')
                    ->groupBy('order_items.product_id', 'order_items.product_name')
                    ->select(
                        'order_items.product_name',
                        DB::raw('SUM(order_items.quantity) as total_qty'),
                        DB::raw('SUM(order_items.line_total) as total_revenue')
                    )
                    ->orderByDesc('total_qty')
                    ->orderBy('order_items.product_name'); // stable tiebreaker
                break;

            case 'low-stock':
                $columns = ['المنتج', 'الموقع', 'الكمية الحالية', 'الحد الأدنى'];
                $query   = Inventory::with(['product', 'location'])
                    ->whereIn('location_id', $locationIds)
                    ->whereRaw('quantity <= (SELECT minimum_stock_level FROM location_products WHERE location_products.location_id = inventories.location_id AND location_products.product_id = inventories.product_id LIMIT 1)')
                    ->orderBy('id'); // stable — id is unique
                break;

            case 'stock-movements':
                $columns = ['المنتج', 'الموقع', 'النوع', 'الكمية', 'التاريخ'];
                $query   = StockMovement::with(['product', 'location'])
                    ->whereIn('location_id', $locationIds)
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest()
                    ->orderByDesc('id');
                break;

            case 'stock-transfers':
                $columns = ['من', 'إلى', 'الحالة', 'المرسل', 'التاريخ'];
                $query   = StockTransfer::with(['fromLocation', 'toLocation', 'dispatchedBy'])
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest()
                    ->orderByDesc('id');
                break;

            case 'cash-sessions':
                $columns = ['الفرع', 'الموظف', 'الرصيد الافتتاحي', 'المستلم', 'الفعلي', 'الفرق', 'الحالة', 'التاريخ'];
                $query   = CashSession::with(['location', 'employee'])
                    ->whereIn('location_id', $locationIds)
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest()
                    ->orderByDesc('id');
                break;

            case 'collections':
                $columns = ['العميل', 'الفاتورة', 'المبلغ', 'الطريقة', 'التاريخ'];
                $query = $this->paymentReportQuery(
                    $locationIds,
                    $dateFrom,
                    $end,
                    true
                )
                    ->latest('payments.paid_at')
                    ->orderByDesc('payments.id');
                break;

            case 'outstanding':
                $columns = ['العميل', 'رقم الفاتورة', 'الإجمالي', 'المدفوع', 'المتبقي', 'تاريخ الفاتورة'];
                $query   = Invoice::with(['customer', 'location'])
                    ->whereIn('location_id', $locationIds)
                    ->where('status', 'active')
                    ->where('remaining_amount', '>', 0)
                    ->latest('issued_at')
                    ->orderByDesc('id');
                break;

            case 'activity-logs':
                $columns = ['المستخدم', 'الإجراء', 'السجل', 'التاريخ'];
                $query   = ActivityLog::with('user')
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest()
                    ->orderByDesc('id');
                break;

            case 'cake-orders':
                $columns = ['رقم الطلب', 'العميل', 'الفرع', 'الحالة', 'تاريخ التسليم', 'التاريخ'];
                $query   = SpecialCakeOrder::with(['customer', 'originBranch'])
                    ->whereIn('origin_branch_id', $locationIds)
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest()
                    ->orderByDesc('id');
                break;

            case 'cake-production':
                $columns = [
                    'نوع الكيك',
                    'الحجم',
                    'الشكل',
                    'طلبات خاصة',
                    'طلبات الفروع',
                    'الإجمالي',
                ];
                $query = $this->cakeProductionQuery(
                    $locationIds,
                    $dateFrom,
                    $dateTo
                );
                break;

            case 'inventory':
                $columns = ['المنتج', 'الموقع', 'الكمية', 'الحد الأدنى', 'الحد الأقصى'];
                $query   = Inventory::with(['product', 'location'])
                    ->whereIn('location_id', $locationIds)
                    ->orderBy('id'); // stable — id is unique
                break;

            default:
                // Fallback: empty query that returns nothing.
                $query = Order::whereRaw('1 = 0');
                break;
        }

        return [$columns, $query];
    }

    /**
     * Raw production demand from special-cake orders and showroom/branch
     * requests. The report period follows the required/needed production date,
     * not the record creation timestamp.
     */
    private function cakeDemandUnion(
        Collection $locationIds,
        string $dateFrom,
        string $dateTo
    ): \Illuminate\Database\Query\Builder {
        $ids = $locationIds
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $special = DB::table('special_cake_orders')
            ->selectRaw("'special' as source")
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(cake_type), ''), 'غير محدد') as cake_type"
            )
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(cake_size), ''), 'غير محدد') as cake_size"
            )
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(shape), ''), 'غير محدد') as shape"
            )
            ->selectRaw('1 as quantity')
            ->whereIn('origin_branch_id', $ids)
            ->whereBetween(
                'required_date',
                [$dateFrom, $dateTo]
            )
            ->where('status', '!=', 'cancelled')
            ->whereNull('deleted_at');

        $showroom = DB::table(
            'showroom_cake_request_items as items'
        )
            ->join(
                'showroom_cake_requests as requests',
                'requests.id',
                '=',
                'items.showroom_cake_request_id'
            )
            ->selectRaw("'showroom' as source")
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(items.cake_type), ''), 'غير محدد') as cake_type"
            )
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(items.cake_size), ''), 'غير محدد') as cake_size"
            )
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(items.shape), ''), 'غير محدد') as shape"
            )
            ->selectRaw('items.quantity as quantity')
            ->whereIn(
                'requests.requesting_location_id',
                $ids
            )
            ->whereRaw(
                'COALESCE(requests.needed_by, DATE(requests.created_at)) BETWEEN ? AND ?',
                [$dateFrom, $dateTo]
            )
            ->whereNotIn(
                'requests.status',
                ['cancelled', 'rejected']
            )
            ->whereNull('requests.deleted_at');

        return $special->unionAll($showroom);
    }

    /**
     * Aggregated factory production view.
     */
    private function cakeProductionQuery(
        Collection $locationIds,
        string $dateFrom,
        string $dateTo
    ): \Illuminate\Database\Query\Builder {
        return DB::query()
            ->fromSub(
                $this->cakeDemandUnion(
                    $locationIds,
                    $dateFrom,
                    $dateTo
                ),
                'cake_demand'
            )
            ->select(
                'cake_type',
                'cake_size',
                'shape'
            )
            ->selectRaw(
                "SUM(CASE WHEN source = 'special' THEN quantity ELSE 0 END) as special_quantity"
            )
            ->selectRaw(
                "SUM(CASE WHEN source = 'showroom' THEN quantity ELSE 0 END) as showroom_quantity"
            )
            ->selectRaw(
                'SUM(quantity) as total_quantity'
            )
            ->groupBy(
                'cake_type',
                'cake_size',
                'shape'
            )
            ->orderByDesc('total_quantity')
            ->orderBy('cake_type')
            ->orderBy('cake_size')
            ->orderBy('shape');
    }

    /**
     * Summary cards for the unified cake-production report.
     *
     * @return array<string, int>
     */
    private function cakeProductionSummary(
        Collection $locationIds,
        string $dateFrom,
        string $dateTo
    ): array {
        $demand = DB::query()
            ->fromSub(
                $this->cakeDemandUnion(
                    $locationIds,
                    $dateFrom,
                    $dateTo
                ),
                'cake_demand'
            );

        return [
            'إجمالي قطع الكيك' =>
                (int) (clone $demand)->sum('quantity'),

            'طلبات الكيك الخاصة' =>
                (int) (clone $demand)
                    ->where('source', 'special')
                    ->sum('quantity'),

            'كيك الفروع' =>
                (int) (clone $demand)
                    ->where('source', 'showroom')
                    ->sum('quantity'),

            'تشكيلات الإنتاج' =>
                $this->countExportRows(
                    $this->cakeProductionQuery(
                        $locationIds,
                        $dateFrom,
                        $dateTo
                    )
                ),
        ];
    }

    /**
     * Compute the summary KPIs for a report using aggregate-only queries.
     * No full row set is ever fetched — every value is a COUNT or SUM.
     * This is used by exportPdf() so the memory cost of summary figures
     * is independent of the number of matching rows.
     *
     * @return array<string, string|int>
     */
    private function buildReportSummary(
        string $type,
        Collection $locationIds,
        string $dateFrom,
        string $dateTo,
    ): array {
        $end = $dateTo . ' 23:59:59';

        return match ($type) {
            'orders' => [
                'الإجمالي' => Order::whereIn('location_id', $locationIds)->whereBetween('created_at', [$dateFrom, $end])->count(),
                'المكتملة' => Order::whereIn('location_id', $locationIds)->whereBetween('created_at', [$dateFrom, $end])->where('status', 'completed')->count(),
                'الملغاة'  => Order::whereIn('location_id', $locationIds)->whereBetween('created_at', [$dateFrom, $end])->where('status', 'cancelled')->count(),
            ],
            'invoices' => [
                'إجمالي الفواتير'    => Invoice::whereIn('location_id', $locationIds)->whereBetween('issued_at', [$dateFrom, $end])->count(),
                'إجمالي المبيعات ₪' => number_format(Invoice::whereIn('location_id', $locationIds)->whereBetween('issued_at', [$dateFrom, $end])->where('status', 'active')->sum('total_amount'), 2),
                'إجمالي المتبقي ₪'  => number_format(Invoice::whereIn('location_id', $locationIds)->where('status', 'active')->sum('remaining_amount'), 2),
            ],
            'payments' => [
                'عدد الدفعات'       => Payment::whereIn('location_id', $locationIds)->whereBetween('paid_at', [$dateFrom, $end])->count(),
                'إجمالي المحصّل ₪' => number_format(Payment::whereIn('location_id', $locationIds)->where('status', 'confirmed')->whereBetween('paid_at', [$dateFrom, $end])->sum('amount'), 2),
            ],
            'daily-sales' => (function () use ($locationIds, $dateFrom, $end): array {
                $sales = Invoice::query()
                    ->whereIn('location_id', $locationIds)
                    ->where('status', 'active')
                    ->whereBetween('issued_at', [$dateFrom, $end]);

                return [
                    'عدد الفواتير' => (clone $sales)->count(),
                    'إجمالي المبيعات ₪' => number_format((clone $sales)->sum('total_amount'), 2),
                    'إجمالي التحصيلات ₪' => number_format((clone $sales)->sum('paid_amount'), 2),
                    'إجمالي المتبقي ₪' => number_format((clone $sales)->sum('remaining_amount'), 2),
                ];
            })(),
            'low-stock' => [
                'منتجات منخفضة' => Inventory::whereIn('location_id', $locationIds)->whereRaw('quantity <= (SELECT minimum_stock_level FROM location_products WHERE location_products.location_id = inventories.location_id AND location_products.product_id = inventories.product_id LIMIT 1)')->count(),
            ],
            'collections' => [
                'إجمالي التحصيلات ₪' => number_format(Payment::whereIn('location_id', $locationIds)->where('status', 'confirmed')->whereBetween('paid_at', [$dateFrom, $end])->sum('amount'), 2),
            ],
            'outstanding' => [
                'إجمالي المستحقات ₪' => number_format(Invoice::whereIn('location_id', $locationIds)->where('status', 'active')->where('remaining_amount', '>', 0)->sum('remaining_amount'), 2),
                'عدد الفواتير'         => Invoice::whereIn('location_id', $locationIds)->where('status', 'active')->where('remaining_amount', '>', 0)->count(),
            ],
            'cake-production' => $this->cakeProductionSummary(
                $locationIds,
                $dateFrom,
                $dateTo
            ),
            // Types whose summaries are not displayed in the PDF header.
            default => [],
        };
    }

    /**
     * Build the report dataset.
     *
     * @return array{0: array, 1: \Illuminate\Support\Collection|\Illuminate\Pagination\LengthAwarePaginator, 2: array}
     */
    private function buildReportData(
        string $type,
        Collection $locationIds,
        string $dateFrom,
        string $dateTo,
        bool $paginate
    ): array {
        $columns = [];
        $data    = collect();
        $summary = [];

        $end = $dateTo . ' 23:59:59';

        switch ($type) {
            case 'orders':
                $columns = ['#', 'العميل', 'الفرع', 'المبلغ', 'الحالة', 'التاريخ'];
                $query = Order::with(['customer', 'location'])
                    ->whereIn('location_id', $locationIds)
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest();
                $summary = [
                    'الإجمالي' => (clone $query)->count(),
                    'المكتملة' => (clone $query)->where('status', 'completed')->count(),
                    'الملغاة'  => (clone $query)->where('status', 'cancelled')->count(),
                ];
                $data = $paginate ? $query->paginate(25)->withQueryString() : $query->get();
                break;

            case 'invoices':
                $columns = ['#', 'العميل', 'الفرع', 'الإجمالي', 'المدفوع', 'المتبقي', 'الحالة', 'التاريخ'];
                $query = Invoice::with(['customer', 'location'])
                    ->whereIn('location_id', $locationIds)
                    ->whereBetween('issued_at', [$dateFrom, $end])
                    ->latest('issued_at');
                $summary = [
                    'إجمالي الفواتير'    => (clone $query)->count(),
                    'إجمالي المبيعات ₪' => number_format(Invoice::whereIn('location_id', $locationIds)->whereBetween('issued_at', [$dateFrom, $end])->where('status', 'active')->sum('total_amount'), 2),
                    'إجمالي المتبقي ₪'  => number_format(Invoice::whereIn('location_id', $locationIds)->where('status', 'active')->sum('remaining_amount'), 2),
                ];
                $data = $paginate ? $query->paginate(25)->withQueryString() : $query->get();
                break;

            case 'payments':
                $columns = ['#', 'الفاتورة', 'الفرع', 'المبلغ', 'الطريقة', 'الحالة', 'التاريخ'];
                $query = $this->paymentReportQuery(
                    $locationIds,
                    $dateFrom,
                    $end
                )
                    ->latest('payments.paid_at')
                    ->orderByDesc('payments.id');

                $summary = [
                    'عدد الدفعات' => (clone $query)->count(),
                    'إجمالي المحصّل ₪' => number_format(
                        Payment::query()
                            ->whereIn('location_id', $locationIds)
                            ->where('status', 'confirmed')
                            ->whereBetween('paid_at', [$dateFrom, $end])
                            ->sum('amount'),
                        2
                    ),
                ];

                $data = $paginate
                    ? $query->paginate(25)->withQueryString()
                    : $query->get();
                break;

            case 'daily-sales':
                $columns = ['التاريخ', 'عدد الفواتير', 'إجمالي المبيعات ₪', 'إجمالي التحصيلات ₪'];
                $query = Invoice::whereIn('location_id', $locationIds)
                    ->where('status', 'active')
                    ->whereBetween('issued_at', [$dateFrom, $end])
                    ->select(
                        DB::raw('DATE(issued_at) as sale_date'),
                        DB::raw('COUNT(*) as invoice_count'),
                        DB::raw('SUM(total_amount) as total_sales'),
                        DB::raw('SUM(paid_amount) as total_paid')
                    )
                    ->groupBy('sale_date')
                    ->orderByDesc('sale_date');
                $summary = $this->buildReportSummary(
                    'daily-sales',
                    $locationIds,
                    $dateFrom,
                    $dateTo
                );
                $data = $paginate ? $query->paginate(31)->withQueryString() : $query->get();
                break;

            case 'monthly-sales':
                $columns = ['الشهر', 'عدد الفواتير', 'إجمالي المبيعات ₪', 'إجمالي التحصيلات ₪'];
                $query = Invoice::whereIn('location_id', $locationIds)
                    ->where('status', 'active')
                    ->whereBetween('issued_at', [$dateFrom, $end])
                    ->select(
                        DB::raw($this->yearMonthExpression('issued_at') . ' as sale_month'),
                        DB::raw('COUNT(*) as invoice_count'),
                        DB::raw('SUM(total_amount) as total_sales'),
                        DB::raw('SUM(paid_amount) as total_paid')
                    )
                    ->groupBy('sale_month')
                    ->orderByDesc('sale_month');
                $data = $paginate ? $query->paginate(12)->withQueryString() : $query->get();
                break;

            case 'branch-sales':
                $columns = ['الفرع', 'عدد الفواتير', 'إجمالي المبيعات ₪', 'إجمالي التحصيلات ₪'];
                $query = Invoice::whereIn('location_id', $locationIds)
                    ->where('status', 'active')
                    ->whereBetween('issued_at', [$dateFrom, $end])
                    ->join('locations', 'locations.id', '=', 'invoices.location_id')
                    ->select(
                        'locations.name as branch_name',
                        DB::raw('COUNT(*) as invoice_count'),
                        DB::raw('SUM(invoices.total_amount) as total_sales'),
                        DB::raw('SUM(invoices.paid_amount) as total_paid')
                    )
                    ->groupBy('invoices.location_id', 'locations.name')
                    ->orderByDesc('total_sales');
                $data = $paginate ? $query->paginate(20)->withQueryString() : $query->get();
                break;

            case 'product-sales':
                $columns = ['المنتج', 'الكمية المباعة', 'إجمالي المبيعات ₪'];
                $query = DB::table('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->whereIn('orders.location_id', $locationIds->toArray())
                    ->whereBetween('orders.created_at', [$dateFrom, $end])
                    ->where('orders.status', '!=', 'cancelled')
                    ->groupBy('order_items.product_id', 'order_items.product_name')
                    ->select(
                        'order_items.product_name',
                        DB::raw('SUM(order_items.quantity) as total_qty'),
                        DB::raw('SUM(order_items.line_total) as total_revenue')
                    )
                    ->orderByDesc('total_qty');
                $data = $paginate ? $query->paginate(25) : collect($query->get());
                break;

            case 'low-stock':
                $columns = ['المنتج', 'الموقع', 'الكمية الحالية', 'الحد الأدنى'];
                $query = Inventory::with(['product', 'location'])
                    ->whereIn('location_id', $locationIds)
                    ->whereRaw('quantity <= (SELECT minimum_stock_level FROM location_products WHERE location_products.location_id = inventories.location_id AND location_products.product_id = inventories.product_id LIMIT 1)');
                $summary = ['منتجات منخفضة' => (clone $query)->count()];
                $data = $paginate ? $query->paginate(25)->withQueryString() : $query->get();
                break;

            case 'stock-movements':
                $columns = ['المنتج', 'الموقع', 'النوع', 'الكمية', 'التاريخ'];
                $query = StockMovement::with(['product', 'location'])
                    ->whereIn('location_id', $locationIds)
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest();
                $data = $paginate ? $query->paginate(25)->withQueryString() : $query->get();
                break;

            case 'stock-transfers':
                $columns = ['من', 'إلى', 'الحالة', 'المرسل', 'التاريخ'];
                $query = StockTransfer::with(['fromLocation', 'toLocation', 'dispatchedBy'])
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest();
                $data = $paginate ? $query->paginate(25)->withQueryString() : $query->get();
                break;

            case 'cash-sessions':
                $columns = ['الفرع', 'الموظف', 'الرصيد الافتتاحي', 'المستلم', 'الفعلي', 'الفرق', 'الحالة', 'التاريخ'];
                $query = CashSession::with(['location', 'employee'])
                    ->whereIn('location_id', $locationIds)
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest();
                $data = $paginate ? $query->paginate(25)->withQueryString() : $query->get();
                break;

            case 'collections':
                $columns = ['العميل', 'الفاتورة', 'المبلغ', 'الطريقة', 'التاريخ'];
                $query = $this->paymentReportQuery(
                    $locationIds,
                    $dateFrom,
                    $end,
                    true
                )
                    ->latest('payments.paid_at')
                    ->orderByDesc('payments.id');

                $summary = [
                    'إجمالي التحصيلات ₪' => number_format(
                        Payment::query()
                            ->whereIn('location_id', $locationIds)
                            ->where('status', 'confirmed')
                            ->whereBetween('paid_at', [$dateFrom, $end])
                            ->sum('amount'),
                        2
                    ),
                ];

                $data = $paginate
                    ? $query->paginate(25)->withQueryString()
                    : $query->get();
                break;

            case 'outstanding':
                $columns = ['العميل', 'رقم الفاتورة', 'الإجمالي', 'المدفوع', 'المتبقي', 'تاريخ الفاتورة'];
                $query = Invoice::with(['customer', 'location'])
                    ->whereIn('location_id', $locationIds)
                    ->where('status', 'active')
                    ->where('remaining_amount', '>', 0)
                    ->latest('issued_at');
                $summary = [
                    'إجمالي المستحقات ₪' => number_format(Invoice::whereIn('location_id', $locationIds)->where('status', 'active')->where('remaining_amount', '>', 0)->sum('remaining_amount'), 2),
                    'عدد الفواتير'         => (clone $query)->count(),
                ];
                $data = $paginate ? $query->paginate(25)->withQueryString() : $query->get();
                break;

            case 'activity-logs':
                $columns = ['المستخدم', 'الإجراء', 'السجل', 'التاريخ'];
                $query = ActivityLog::with('user')
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest();
                $data = $paginate ? $query->paginate(25)->withQueryString() : $query->get();
                break;

            case 'cake-orders':
                $columns = ['رقم الطلب', 'العميل', 'الفرع', 'الحالة', 'تاريخ التسليم', 'التاريخ'];
                $query = SpecialCakeOrder::with(['customer', 'originBranch'])
                    ->whereIn('origin_branch_id', $locationIds)
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest();
                $data = $paginate ? $query->paginate(25)->withQueryString() : $query->get();
                break;

            case 'cake-production':
                $columns = [
                    'نوع الكيك',
                    'الحجم',
                    'الشكل',
                    'طلبات خاصة',
                    'طلبات الفروع',
                    'الإجمالي',
                ];

                $query = $this->cakeProductionQuery(
                    $locationIds,
                    $dateFrom,
                    $dateTo
                );

                $summary = $this->cakeProductionSummary(
                    $locationIds,
                    $dateFrom,
                    $dateTo
                );

                $data = $paginate
                    ? $query->paginate(25)->withQueryString()
                    : $query->get();
                break;

            case 'inventory':
                $columns = ['المنتج', 'الموقع', 'الكمية', 'الحد الأدنى', 'الحد الأقصى'];
                $query = Inventory::with(['product', 'location'])
                    ->whereIn('location_id', $locationIds);
                $data = $paginate ? $query->paginate(25)->withQueryString() : $query->get();
                break;

            default:
                break;
        }

        return [$columns, $data, $summary];
    }

    /**
     * Normalize PaymentStatus enum/string to an Arabic label for exports.
     */
    private function paymentStatusLabel(mixed $status): string
    {
        if (is_object($status) && method_exists($status, 'label')) {
            return (string) $status->label();
        }

        $value = $status instanceof \BackedEnum
            ? (string) $status->value
            : (string) ($status ?? '');

        return match ($value) {
            'confirmed'            => 'مؤكدة',
            'pending_verification' => 'بانتظار التحقق',
            'rejected'             => 'مرفوضة',
            'corrected'            => 'مصححة',
            'refunded'             => 'مستردة',
            default                => $value !== '' ? $value : '—',
        };
    }

    /**
     * Convert a report row to a flat array suitable for Excel export.
     */
    private function formatRowForExport(string $type, mixed $row): array
    {
        return match ($type) {
            'orders' => [
                '#' . $row->id,
                $row->customer?->name ?? '—',
                $row->location?->name ?? '—',
                number_format($row->total_amount ?? 0, 2),
                $row->status?->label() ?? $row->status?->value ?? '—',
                \Carbon\Carbon::parse($row->created_at)->format('Y/m/d'),
            ],
            'invoices' => [
                '#' . $row->id,
                $row->customer?->name ?? '—',
                $row->location?->name ?? '—',
                number_format($row->total_amount ?? 0, 2),
                number_format($row->paid_amount ?? 0, 2),
                number_format($row->remaining_amount ?? 0, 2),
                $row->status?->label() ?? $row->status?->value ?? '—',
                \Carbon\Carbon::parse($row->issued_at)->format('Y/m/d'),
            ],
            'payments' => [
                '#' . $row->id,
                $row->report_invoice_number ?? '—',
                $row->location?->name ?? '—',
                number_format((float) ($row->amount ?? 0), 2),
                $row->paymentMethod?->name_ar
                    ?? $row->paymentMethod?->name
                    ?? '—',
                $this->paymentStatusLabel($row->status ?? null),
                $row->paid_at
                    ? \Carbon\Carbon::parse($row->paid_at)->format('Y/m/d')
                    : '—',
            ],
            'collections' => [
                $row->report_customer_name ?? 'بيع سريع',
                $row->report_invoice_number ?? '—',
                number_format((float) ($row->amount ?? 0), 2),
                $row->paymentMethod?->name_ar
                    ?? $row->paymentMethod?->name
                    ?? '—',
                $row->paid_at
                    ? \Carbon\Carbon::parse($row->paid_at)->format('Y/m/d')
                    : '—',
            ],
            'daily-sales' => [
                $row->sale_date ?? '—',
                (int) ($row->invoice_count ?? 0),
                number_format($row->total_sales ?? 0, 2),
                number_format($row->total_paid ?? 0, 2),
            ],
            'monthly-sales' => [
                $row->sale_month ?? '—',
                (int) ($row->invoice_count ?? 0),
                number_format($row->total_sales ?? 0, 2),
                number_format($row->total_paid ?? 0, 2),
            ],
            'branch-sales' => [
                $row->branch_name ?? '—',
                (int) ($row->invoice_count ?? 0),
                number_format($row->total_sales ?? 0, 2),
                number_format($row->total_paid ?? 0, 2),
            ],
            'product-sales' => [
                $row->product_name ?? '—',
                number_format($row->total_qty ?? 0, 2),
                number_format($row->total_revenue ?? 0, 2),
            ],
            'low-stock' => [
                $row->product?->name ?? '—',
                $row->location?->name ?? '—',
                (int) ($row->quantity ?? 0),
                $row->minimum_stock_level ?? '—',
            ],
            'stock-movements' => [
                $row->product?->name ?? '—',
                $row->location?->name ?? '—',
                $row->movement_type?->label() ?? $row->movement_type?->value ?? '—',
                number_format($row->quantity ?? 0, 2),
                \Carbon\Carbon::parse($row->created_at)->format('Y/m/d'),
            ],
            'stock-transfers' => [
                $row->fromLocation?->name ?? '—',
                $row->toLocation?->name ?? '—',
                $row->status?->label() ?? $row->status?->value ?? '—',
                $row->dispatchedBy?->employee?->full_name ?? '—',
                \Carbon\Carbon::parse($row->created_at)->format('Y/m/d'),
            ],
            'cash-sessions' => [
                $row->location?->name ?? '—',
                $row->employee?->full_name ?? '—',
                number_format($row->opening_balance ?? 0, 2),
                number_format($row->cash_received ?? 0, 2),
                number_format($row->actual_cash ?? 0, 2),
                number_format($row->variance ?? 0, 2),
                ($row->status?->value ?? $row->status) === 'open' ? 'مفتوح' : 'مغلق',
                \Carbon\Carbon::parse($row->created_at)->format('Y/m/d'),
            ],
            'outstanding' => [
                $row->customer?->name ?? '—',
                '#' . $row->id,
                number_format($row->total_amount ?? 0, 2),
                number_format($row->paid_amount ?? 0, 2),
                number_format($row->remaining_amount ?? 0, 2),
                \Carbon\Carbon::parse($row->issued_at)->format('Y/m/d'),
            ],
            'activity-logs' => [
                $row->user?->username ?? '—',
                $row->action,
                $row->module . ' / ' . $row->record_type,
                \Carbon\Carbon::parse($row->created_at)->format('Y/m/d H:i'),
            ],
            'cake-orders' => [
                '#' . $row->id,
                $row->customer?->name ?? '—',
                $row->originBranch?->name ?? '—',
                $row->status?->label() ?? $row->status?->value ?? '—',
                $row->required_date ? \Carbon\Carbon::parse($row->required_date)->format('Y/m/d') : '—',
                \Carbon\Carbon::parse($row->created_at)->format('Y/m/d'),
            ],
            'cake-production' => [
                $row->cake_type ?? 'غير محدد',
                $row->cake_size ?? 'غير محدد',
                $row->shape ?? 'غير محدد',
                (int) ($row->special_quantity ?? 0),
                (int) ($row->showroom_quantity ?? 0),
                (int) ($row->total_quantity ?? 0),
            ],
            'inventory' => [
                $row->product?->name ?? '—',
                $row->location?->name ?? '—',
                (int) ($row->quantity ?? 0),
                $row->minimum_stock_level ?? '—',
                $row->maximum_stock_level ?? '—',
            ],
            default => is_array($row) ? array_values($row) : [(string) $row],
        };
    }
}

