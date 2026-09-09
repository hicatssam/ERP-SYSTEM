@php
    /*
     * Use only the report type supplied by the controller. Never merge the
     * complete query string after ['type' => $type], because a stale ?type=
     * value can replace the current route parameter.
     */
    $reportFilterParams = array_filter([
        'date_from'  => $dateFrom ?? null,
        'date_to'    => $dateTo ?? null,
        'location_id'=> $locationId ?? null,
    ], static fn ($value) => $value !== null && $value !== '');

    $reportRouteParams = ['type' => $type] + $reportFilterParams;
@endphp

<div class="page-header-actions">
    <a id="btn-export-xlsx"
       href="{{ route('reports.export.xlsx', $reportRouteParams) }}"
       class="btn btn-outline btn-sm">
        Excel
    </a>

    <a id="btn-export-pdf"
       href="{{ route('reports.export.pdf', $reportRouteParams) }}"
       class="btn btn-outline btn-sm">
        PDF
    </a>

    <a id="btn-print-report"
       href="{{ route('reports.print', $reportRouteParams) }}"
       target="_blank"
       rel="noopener"
       class="btn btn-gold btn-sm">
        طباعة التقرير كامل
    </a>

    <a href="{{ route('reports.index') }}" class="btn btn-ghost btn-sm">
        رجوع
    </a>
</div>