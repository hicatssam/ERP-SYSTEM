@extends('layouts.app')
@section('title', 'طلبات المخزون')
@section('content')
<div class="page-actions">
    <div class="page-actions-title">طلبات المخزون</div>
    <div class="action-btns">
        @can('stock_requests.create')
        <a href="{{ route('stock-requests.create') }}" class="btn btn-gold">طلب مخزون جديد</a>
        @endcan
    </div>
</div>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>رقم الطلب</th><th>الفرع</th><th>المصنع</th><th>الحالة</th><th>التاريخ</th><th>الإجراءات</th></tr></thead>
        <tbody>
        @forelse($requests as $req)
            <tr>
                <td><strong>{{ $req->request_number }}</strong></td>
                <td>{{ $req->branch?->name }}</td>
                <td>{{ $req->factory?->name }}</td>
                <td><span class="badge badge-pending">@statusArabic($req->status)</span></td>
                <td>{{ $req->created_at->format('Y-m-d') }}</td>
                <td><div class="actions"><a href="{{ route('stock-requests.show', $req) }}" class="btn btn-ghost btn-sm">عرض</a></div></td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="empty-state-sm">لا توجد طلبات مخزون.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div>
    {{ $requests->withQueryString()->links() }}
    </div>
@endsection
