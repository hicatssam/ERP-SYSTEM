@extends('layouts.app')
@section('title', 'تحويلات المخزون')
@section('content')
<div class="page-actions">
    <div class="page-actions-title">تحويلات المخزون</div>
</div>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>رقم التحويل</th><th>من</th><th>إلى</th><th>الحالة</th><th>التاريخ</th><th>الإجراءات</th></tr></thead>
        <tbody>
        @forelse($transfers as $tr)
            <tr>
                <td><strong>{{ $tr->transfer_number }}</strong></td>
                <td>{{ $tr->fromLocation?->name }}</td>
                <td>{{ $tr->toLocation?->name }}</td>
                <td><span class="badge badge-pending">@statusArabic($tr->status)</span></td>
                <td>{{ $tr->created_at->format('Y-m-d') }}</td>
                <td><div class="actions"><a href="{{ route('stock-transfers.show', $tr) }}" class="btn btn-ghost btn-sm">عرض</a></div></td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="empty-state-sm">لا توجد تحويلات.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>


<div>
    {{ $transfers->withQueryString()->links() }}
    </div>
@endsection
