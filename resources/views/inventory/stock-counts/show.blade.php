@extends('layouts.app')
@section('title', 'تفاصيل الجرد')
@section('content')
<div class="page-actions">
    <div class="page-actions-title">جرد: {{ $stockCount->location?->name }} — {{ $stockCount->created_at->format('Y-m-d') }}</div>
    <div class="action-btns">
        @if($stockCount->status === 'in_progress')
            @can('inventory.count')
            <a href="{{ route('stock-counts.edit', $stockCount) }}" class="btn btn-outline btn-sm">إدخال كميات</a>
            <form action="{{ route('stock-counts.approve', $stockCount) }}" method="POST" style="display:inline">@csrf
                <button class="btn btn-gold btn-sm" data-confirm="اعتماد الجرد وتعديل المخزون؟">اعتماد الجرد</button>
            </form>
            @endcan
        @endif
        <a href="{{ route('stock-counts.index') }}" class="btn btn-ghost btn-sm">رجوع</a>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">عناصر الجرد</span></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>المنتج</th><th>الكمية في النظام</th><th>الكمية الفعلية</th><th>الفارق</th></tr></thead>
            <tbody>
            @foreach($stockCount->items as $item)
                @php $diff = $item->actual_quantity !== null ? ($item->actual_quantity - $item->system_quantity) : null; @endphp
                <tr>
                    <td>{{ $item->product?->name_ar ?? $item->product?->name }}</td>
                    <td>{{ number_format($item->system_quantity, 2) }}</td>
                    <td>{{ $item->actual_quantity !== null ? number_format($item->actual_quantity, 2) : '—' }}</td>
                    <td>
                        @if($diff !== null)
                            <span style="color: {{ $diff > 0 ? 'var(--success)' : ($diff < 0 ? 'var(--error)' : 'var(--text-muted)') }}; font-weight:700">
                                {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 2) }}
                            </span>
                        @else
                            <span style="color:var(--text-muted)">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
