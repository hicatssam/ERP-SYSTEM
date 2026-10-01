@extends('layouts.app')

@section('title', 'الملف الوظيفي')
@section('page-title', 'الملف الوظيفي')

@section('content')
<div style="max-width:1200px;margin:auto">
    <div class="page-actions">
        <div>
            <h1 class="page-heading">{{ $employee->full_name }}</h1>
            <p class="page-subheading">{{ $employee->employee_number }} · بيانات الطوارئ والمستندات وتاريخ الفرع. الملفات خاصة ولا تُنشر كرابط عام.</p>
        </div>
        @can('employees.manage')
            <a class="btn btn-outline" href="{{ route('employees.show', $employee) }}">العودة إلى الموظف</a>
        @endcan
    </div>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="hr-file-grid">
        <section class="card">
            <div class="card-header"><span class="card-title">الطوارئ ودورة العمل</span></div>
            @can('hr.documents.manage')
                <form class="card-body hr-file-form" action="{{ route('hr.employees.file.update', $employee) }}" method="POST">
                    @csrf @method('PUT')
                    <div><label class="form-label">اسم جهة الاتصال للطوارئ</label><input class="form-input" name="emergency_name" value="{{ old('emergency_name', $employee->hrProfile?->emergency_name) }}" maxlength="190"></div>
                    <div><label class="form-label">هاتف الطوارئ</label><input class="form-input" name="emergency_phone" value="{{ old('emergency_phone', $employee->hrProfile?->emergency_phone) }}" maxlength="40"></div>
                    <div><label class="form-label">صلة القرابة</label><input class="form-input" name="emergency_relationship" value="{{ old('emergency_relationship', $employee->hrProfile?->emergency_relationship) }}" maxlength="100"></div>
                    <div><label class="form-label">نهاية العقد</label><input class="form-input" type="date" name="contract_ends_on" value="{{ old('contract_ends_on', $employee->hrProfile?->contract_ends_on?->toDateString()) }}"></div>
                    <div><label class="form-label">تاريخ إكمال التهيئة</label><input class="form-input" type="date" name="onboarded_on" value="{{ old('onboarded_on', $employee->hrProfile?->onboarded_on?->toDateString()) }}"></div>
                    <div><label class="form-label">تاريخ إنهاء الإجراءات</label><input class="form-input" type="date" name="offboarded_on" value="{{ old('offboarded_on', $employee->hrProfile?->offboarded_on?->toDateString()) }}"></div>
                    <div class="hr-file-wide"><label class="form-label">ملاحظات التهيئة والخروج</label><textarea class="form-input" name="lifecycle_notes" rows="3" maxlength="3000">{{ old('lifecycle_notes', $employee->hrProfile?->lifecycle_notes) }}</textarea></div>
                    <button class="btn btn-gold" type="submit">حفظ الملف</button>
                </form>
            @else
                <div class="card-body">
                    <p>للطوارئ: {{ $employee->hrProfile?->emergency_name ?: '—' }} · {{ $employee->hrProfile?->emergency_phone ?: '—' }} · {{ $employee->hrProfile?->emergency_relationship ?: '—' }}</p>
                    <p>نهاية العقد: {{ $employee->hrProfile?->contract_ends_on?->format('Y-m-d') ?? '—' }}</p>
                    <p>التهيئة: {{ $employee->hrProfile?->onboarded_on?->format('Y-m-d') ?? '—' }} · الخروج: {{ $employee->hrProfile?->offboarded_on?->format('Y-m-d') ?? '—' }}</p>
                    <p>{{ $employee->hrProfile?->lifecycle_notes }}</p>
                </div>
            @endcan
        </section>

        <section class="card">
            <div class="card-header"><span class="card-title">سجل الفروع</span></div>
            <div class="card-body">
                @forelse($employee->employeeLocations->sortByDesc('started_at') as $assignment)
                    <div class="hr-file-row">
                        <strong>{{ $assignment->location?->name ?? 'فرع محذوف' }}</strong>
                        <span>{{ $assignment->started_at?->format('Y-m-d') ?? '—' }} — {{ $assignment->ended_at?->format('Y-m-d') ?? 'الآن' }}</span>
                    </div>
                @empty
                    <p>لا يوجد سجل فروع.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="card">
        <div class="card-header"><span class="card-title">مستندات الموظف</span></div>
        <div class="card-body">
            @can('hr.documents.manage')
                <form class="hr-file-form" method="POST" enctype="multipart/form-data" action="{{ route('hr.employees.documents.store', $employee) }}">
                    @csrf
                    <div><label class="form-label">العنوان</label><input class="form-input" name="title" maxlength="190" required></div>
                    <div><label class="form-label">النوع</label><select class="form-input" name="kind" required><option value="contract">عقد</option><option value="identity">هوية</option><option value="certificate">شهادة</option><option value="other">آخر</option></select></div>
                    <div><label class="form-label">تاريخ انتهاء الصلاحية</label><input class="form-input" type="date" name="expires_on"></div>
                    <div><label class="form-label">الملف (PDF أو صورة، حتى 10 ميجابايت)</label><input class="form-input" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
                    <div class="hr-file-wide"><label class="form-label">ملاحظات</label><textarea class="form-input" name="notes" rows="2" maxlength="1000"></textarea></div>
                    <button class="btn btn-gold" type="submit">حفظ المستند</button>
                </form>
            @endcan
            <div style="margin-top:1rem">
                @forelse($employee->documents->sortByDesc('id') as $document)
                    <div class="hr-file-row">
                        <strong>{{ $document->title }}</strong>
                        <span>{{ $document->original_name }} · انتهاء: {{ $document->expires_on?->format('Y-m-d') ?? 'غير محدد' }}
                            @if($document->expires_on && $document->expires_on->lte(now()->addDays(30))) <b style="color:var(--theme-danger)">يحتاج متابعة</b> @endif
                        </span>
                        <a href="{{ route('hr.employees.documents.download', [$employee, $document]) }}">تنزيل المستند</a>
                    </div>
                @empty
                    <p>لا توجد مستندات محفوظة.</p>
                @endforelse
            </div>
        </div>
    </section>
</div>
<style>
.hr-file-grid{display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1rem}
.hr-file-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem}
.hr-file-form .form-input{width:100%}.hr-file-wide{grid-column:1/-1}
.hr-file-row{display:flex;justify-content:space-between;flex-wrap:wrap;gap:.55rem;padding:.8rem 0;border-bottom:1px solid var(--border)}
@media(max-width:800px){.hr-file-grid,.hr-file-form{grid-template-columns:1fr}}
</style>
@endsection
