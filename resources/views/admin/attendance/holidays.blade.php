@extends('layouts.app')

@section('title', 'تقويم العطل')
@section('page-title', 'تقويم العطل')

@section('content')
<div style="max-width:980px;margin:auto">
    <div class="page-actions">
        <div><h1 class="page-heading">تقويم العطل</h1><p class="page-subheading">العطلة العامة تشمل الجميع، وعطلة الفرع تخص موظفيه. تؤثر في الطلبات الجديدة المحسوبة بأيام الدوام، ولا تعيد احتساب طلبات الإجازة السابقة.</p></div>
        <a class="btn btn-outline" href="{{ route('attendance.leave-types.index') }}">سياسات الإجازات</a>
    </div>
    @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <section class="card" style="margin-bottom:1rem">
        <div class="card-header"><span class="card-title">إضافة عطلة</span></div>
        <form class="card-body" method="POST" action="{{ route('attendance.holidays.store') }}" style="display:flex;flex-wrap:wrap;gap:.7rem;align-items:end">
            @csrf
            <div><label class="form-label">التاريخ</label><input class="form-input" name="holiday_date" type="date" value="{{ old('holiday_date') }}" required></div>
            <div><label class="form-label">الاسم</label><input class="form-input" name="name" value="{{ old('name') }}" maxlength="190" required></div>
            <div><label class="form-label">النطاق</label><select class="form-input" name="location_id"><option value="">كل الفروع</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>{{ $location->name }}</option>@endforeach</select></div>
            <button class="btn btn-gold" type="submit">إضافة العطلة</button>
        </form>
    </section>
    <section class="card">
        <div class="card-header"><span class="card-title">العطل المسجلة</span></div>
        <div class="card-body">
            @forelse($holidays as $holiday)
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;padding:.7rem 0;border-bottom:1px solid var(--border)">
                    <span><strong>{{ $holiday->name }}</strong> · {{ $holiday->holiday_date?->format('Y-m-d') }} · {{ $holiday->location?->name ?? 'كل الفروع' }}</span>
                    <form method="POST" action="{{ route('attendance.holidays.destroy', $holiday) }}">@csrf @method('DELETE')<button class="btn btn-outline" type="submit">حذف</button></form>
                </div>
            @empty
                <p>لا توجد عطلات مسجلة.</p>
            @endforelse
            {{ $holidays->links() }}
        </div>
    </section>
</div>
@endsection
