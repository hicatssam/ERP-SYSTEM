@php
    $supplierRows = $supplierRows ?? collect();
    $preferredRow = $supplierRows
        ->first(fn ($row) => (bool) $row->is_preferred)
        ?? $supplierRows->first();
    $supplier = $preferredRow?->supplier;
    $alternativesCount = max(0, $supplierRows->count() - 1);
@endphp

@if($supplier)
    <div style="display:flex;align-items:center;gap:.45rem;flex-wrap:wrap">
        <a
            href="{{ route('suppliers.show', $supplier) }}"
            style="color:var(--theme-primary);font-weight:800;text-decoration:none"
            title="فتح ملف المورد"
        >
            {{ $supplier->company_name ?: $supplier->name }}
        </a>

        @if($preferredRow?->is_preferred)
            <span style="font-size:.62rem;font-weight:800;color:var(--theme-primary)">
                مفضل
            </span>
        @endif

        @if($alternativesCount > 0)
            <span style="color:var(--text-muted);font-size:.65rem">
                +{{ $alternativesCount }} بديل
            </span>
        @endif
    </div>
@else
    <span style="color:var(--text-muted)">غير مرتبط بمورد</span>
@endif
