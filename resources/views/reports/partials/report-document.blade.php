{{-- Header --}}
@include('reports.partials.header')

{{-- Summary --}}
@include('reports.partials.summary')

{{-- Table --}}
@include('reports.partials.table')

{{-- Signatures --}}
<div class="report-signatures">
    <div class="signature-box">
        <div class="signature-line"></div>
        <div>توقيع الموظف</div>
    </div>

    <div class="signature-box">
        <div class="signature-line"></div>
        <div>اعتماد الإدارة</div>
    </div>
</div>

{{-- Footer --}}
@include('reports.partials.footer')