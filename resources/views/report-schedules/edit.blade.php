@extends('layouts.app')
@section('title', 'تعديل جدول التقرير')
@section('page-title', 'تعديل جدول التقرير')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-heading">تعديل: {{ $reportSchedule->name }}</h1>
        <p class="page-subheading">تحديث إعدادات الجدول الزمني للتقرير</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('report-schedules.index') }}" class="btn btn-ghost btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="15 18 9 12 15 6"/></svg>
            رجوع
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:1rem;max-width:720px">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger" style="margin-bottom:1rem;max-width:720px">{{ session('error') }}</div>
@endif

<div class="card" style="max-width:720px">
    <div class="card-header">
        <span class="card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            إعدادات الجدول
        </span>
    </div>
    <div class="card-body">
        <form id="scheduleForm" method="POST" action="{{ route('report-schedules.update', $reportSchedule) }}">
            @csrf @method('PUT')

            @include('report-schedules._form', ['schedule' => $reportSchedule])

            <div style="display:flex;gap:.75rem;margin-top:1.5rem;flex-wrap:wrap">
                <button type="submit" class="btn btn-gold">حفظ التغييرات</button>
                <button type="button" class="btn btn-ghost" style="color:var(--gold,#b8860b)"
                        onclick="submitAsTestSend('{{ route('report-schedules.test-send') }}')">
                    إرسال تجريبي الآن
                </button>
                <a href="{{ route('report-schedules.index') }}" class="btn btn-ghost">إلغاء</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function submitAsTestSend(url) {
    if (!confirm('سيتم إرسال التقرير الآن إلى المستلمين المحددين. هل تريد المتابعة؟')) return;
    var form = document.getElementById('scheduleForm');
    form.action = url;
    // Switch to POST and remove the PUT override so the test-send route is matched correctly.
    var method = form.querySelector('input[name="_method"]');
    if (method) method.remove();
    form.submit();
}
</script>
@endpush
@endsection
