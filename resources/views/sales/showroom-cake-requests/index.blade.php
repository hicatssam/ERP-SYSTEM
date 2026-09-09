@extends('layouts.app')
@section('title', 'طلبات المعرض')

@section('content')
<div class="page-actions">
    <div class="page-actions-title">طلبات كيك المعرض</div>
    <div class="action-btns">
        <a href="{{ route('showroom-cake-requests.create') }}" class="btn btn-gold">+ طلب جديد</a>
    </div>
</div>

{{-- Filters --}}
<div class="filter-row">
    <form method="GET" class="filter-grid">
        <div class="filter-group">
            <label class="filter-label">الحالة</label>
            <select name="status" class="form-select">
                <option value="">الكل</option>
                @foreach($statusEnum as $s)
                    <option value="{{ $s->value }}" {{ request('status') === $s->value ? 'selected' : '' }}>
                        {{ $s->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        @if(auth()->user()->isAdmin())
        <div class="filter-group">
            <label class="filter-label">المعرض / الفرع</label>
            <select name="location_id" class="form-select">
                <option value="">الكل</option>
                @foreach($locations as $loc)
                    <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>
                        {{ $loc->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="filter-group" style="justify-content:flex-end">
            <button class="btn btn-outline btn-sm" type="submit">تصفية</button>
            <a href="{{ route('showroom-cake-requests.index') }}" class="btn btn-ghost btn-sm">مسح</a>
        </div>
    </form>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>رقم الطلب</th>
                <th>المعرض / الفرع</th>
                <th>عدد الأصناف</th>
                <th>تاريخ الحاجة</th>
                <th>الحالة</th>
                <th>تاريخ الإنشاء</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
        @forelse($requests as $req)
            <tr>
                <td><strong>{{ $req->request_number }}</strong></td>
                <td>{{ $req->requestingLocation?->name ?? '—' }}</td>
                <td>{{ $req->items->count() }} صنف</td>
                <td>{{ $req->needed_by?->format('Y-m-d') ?? '—' }}</td>
                <td>
                    <span class="badge {{ $req->status->badgeClass() }}" style="font-size:.65rem">
                        {{ $req->status->label() }}
                    </span>
                </td>
                <td>{{ $req->created_at->format('Y-m-d') }}</td>
                <td>
                    <div class="actions">
                        <a href="{{ route('showroom-cake-requests.show', $req) }}" class="btn btn-ghost btn-sm">عرض</a>
                        @if(in_array($req->status->value, ['draft', 'cancelled']))
                        <form action="{{ route('showroom-cake-requests.destroy', $req) }}" method="POST"
                              onsubmit="return confirm('هل تريد حذف هذا الطلب؟')">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger btn-sm" type="submit">حذف</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <div class="empty-state-sm">لا توجد طلبات معرض بعد.</div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

<div>
    {{ $requests->withQueryString()->links() }}
</div>
@endsection
