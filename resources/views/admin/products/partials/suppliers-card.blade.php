@php
    $supplierRows = $supplierRows ?? collect();
@endphp

<div class="card">
    <div class="card-header">
        <span class="card-title">مورّدو المنتج</span>
    </div>

    <div class="card-body" style="padding:0">
        @if($supplierRows->isEmpty())
            <div style="padding:1rem;text-align:center;color:var(--text-muted)">
                لا يوجد مورد مرتبط بهذا المنتج حتى الآن.
            </div>
        @else
            <div style="overflow-x:auto">
                <table class="data-table" style="margin:0">
                    <thead>
                        <tr>
                            <th>المورد</th>
                            <th>النوع</th>
                            <th>سعر الشراء</th>
                            <th>الحد الأدنى</th>
                            <th>مدة التوريد</th>
                            <th>SKU المورد</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($supplierRows as $row)
                            <tr>
                                <td>
                                    @if($row->supplier)
                                        <a
                                            href="{{ route('suppliers.show', $row->supplier) }}"
                                            style="color:var(--theme-primary);font-weight:800;text-decoration:none"
                                        >
                                            {{ $row->supplier->company_name ?: $row->supplier->name }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $row->is_preferred ? 'مفضل' : 'بديل' }}</td>
                                <td>
                                    {{ number_format((float) $row->purchase_price, 4) }}
                                    {{ $row->currency?->symbol ?: $row->currency?->displayName() }}
                                </td>
                                <td>{{ number_format((float) $row->minimum_order_quantity, 3) }}</td>
                                <td>{{ $row->lead_time_days !== null ? $row->lead_time_days . ' يوم' : '—' }}</td>
                                <td dir="ltr">{{ $row->supplier_sku ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
