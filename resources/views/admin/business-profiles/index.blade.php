@extends('layouts.app')

@section('title', 'نوع النشاط')
@section('page-title', 'نوع النشاط')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">ملف النشاط التجاري</h1>
        <p class="page-subheading">اختيار النشاط لا يحذف بيانات أو جداول. يتم فقط ضبط الوحدات المقترحة بأمان.</p>
    </div>
    <a href="{{ route('modules.index') }}" class="btn btn-outline">إدارة الوحدات</a>
</div>

@if($errors->any())
    <div class="alert-banner alert-banner-danger" style="margin-bottom:1rem">{{ $errors->first() }}</div>
@endif

<div class="alert-banner" style="margin-bottom:1rem;background:rgba(212,160,23,.08);border:1px solid rgba(212,160,23,.25)">
    الملف الحالي:
    <strong>{{ $currentProfile?->name ?? 'مخبز وحلويات' }}</strong>
    — الوحدات التي لم تُنفذ بعد تبقى «مسجلة للمستقبل» ولا يتم تشغيلها شكليًا.
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:1rem">
    @foreach($profiles as $profile)
        @php($isCurrent = $currentProfile?->id === $profile->id)
        <div class="card" style="border:{{ $isCurrent ? '2px solid var(--gold)' : '1px solid var(--border)' }}">
            <div class="card-header">
                <div>
                    <span class="card-title">{{ $profile->name }}</span>
                    @if($isCurrent)<span class="badge badge-active" style="margin-right:.4rem">الحالي</span>@endif
                </div>
            </div>
            <div class="card-body">
                <p style="color:var(--text-muted);font-size:.78rem;margin-top:0">{{ $profile->description }}</p>

                <div style="display:flex;flex-wrap:wrap;gap:.3rem;margin:1rem 0">
                    @foreach($profile->modules->sortBy('pivot.sort_order') as $module)
                        <span class="badge {{ $module->isImplemented() ? ($module->is_active ? 'badge-active' : 'badge-pending') : 'badge-inactive' }}" title="{{ $module->isImplemented() ? 'منفذة' : 'مسجلة للمستقبل' }}">
                            {{ $module->name }}
                        </span>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('business-profiles.apply', $profile) }}">
                    @csrf
                    <label style="display:flex;gap:.45rem;align-items:flex-start;font-size:.72rem;color:var(--text-muted);margin-bottom:.8rem">
                        <input type="checkbox" name="deactivate_other_industry" value="1">
                        <span>تعطيل الوحدات القطاعية/الاختيارية الأخرى إن أمكن. لا يتم حذف أي بيانات.</span>
                    </label>
                    <button class="btn {{ $isCurrent ? 'btn-outline' : 'btn-gold' }}" type="submit">
                        {{ $isCurrent ? 'إعادة تطبيق الملف' : 'اعتماد هذا النشاط' }}
                    </button>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endsection
