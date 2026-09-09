<?php

namespace App\Services;

use App\Exports\ChunkedQueryReportExport;
use App\Mail\ScheduledReportMail;
use App\Models\Location;
use App\Models\ReportSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

class ReportScheduleService
{
    /** Maximum rows included in a scheduled Excel export. */
    private const CAP = 10000;

    /**
     * Generate the Excel attachment and send the report email(s) for
     * a given schedule. Throws on failure — callers should handle exceptions.
     */
    public function send(ReportSchedule $schedule): void
    {
        ['date_from' => $dateFrom, 'date_to' => $dateTo] = $schedule->resolveDateRange();

        // Resolve location IDs — never escalate to all locations unless the
        // schedule explicitly has no location filter (admin-created).
        $locationIds = $schedule->location_id
            ? collect([$schedule->location_id])
            : Location::pluck('id');

        // Reuse the same private query builder that web exports use.
        $controller = app(\App\Http\Controllers\Reports\ReportController::class);

        [$columns, $query] = $this->callProtected($controller, 'buildExportQuery', [
            $schedule->report_type, $locationIds, $dateFrom, $dateTo,
        ]);

        $title  = $this->reportTypeName($schedule->report_type);
        $mapper = fn ($row) => $this->callProtected($controller, 'formatRowForExport', [
            $schedule->report_type, $row,
        ]);

        $total     = $this->countRows(clone $query);
        $truncated = $total > self::CAP;

        $filename = "{$schedule->report_type}_{$dateFrom}_{$dateTo}.xlsx";
        $tmpPath  = sys_get_temp_dir() . '/' . uniqid('rpt_', true) . '_' . $filename;

        $content = Excel::raw(
            new ChunkedQueryReportExport($query, $columns, $title, $mapper, self::CAP, $truncated),
            \Maatwebsite\Excel\Excel::XLSX,
        );

        file_put_contents($tmpPath, $content);

        try {
            $mailable = new ScheduledReportMail($schedule, $tmpPath, $filename, $dateFrom, $dateTo);
            foreach ($schedule->recipientList() as $email) {
                Mail::to($email)->send($mailable);
            }
        } finally {
            @unlink($tmpPath);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function callProtected(object $object, string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invoke($object, ...$args);
    }

    private function countRows(\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query): int
    {
        $base = $query instanceof \Illuminate\Database\Eloquent\Builder
            ? $query->toBase()
            : $query;

        return (int) DB::query()
            ->fromSub($base->reorder(), 'sub')
            ->count();
    }

    private function reportTypeName(string $type): string
    {
        return [
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
        ][$type] ?? $type;
    }
}
