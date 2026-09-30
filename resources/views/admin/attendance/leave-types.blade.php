@extends('layouts.app')

@section('title', 'أنواع الإجازات')

@section('content')
<div style="max-width:1100px;margin:0 auto">
    <div class="page-actions">
        <div>
            <h1 class="page-heading">إعداد أنواع الإجازات</h1>
            <p class="page-subheading">حدد سياسات الإجازات المناسبة لنشاطك. الحد السنوي يُحسب بالأيام التقويمية، وتركه فارغًا يعني عدم تحديد سقف. أيام الدوام في الإجازات غير المدفوعة تُخصم عند مزامنة الحضور مع الراتب.</p>
        </div>
        @can('attendance.leaves.view')
            <a class="btn btn-outline" href="{{ route('attendance.leaves.index') }}">العودة إلى الطلبات</a>
        @endcan
    </div>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card" style="margin-bottom:1rem">
        <div class="card-header"><span class="card-title">إضافة نوع إجازة</span></div>
        <form method="POST" action="{{ route('attendance.leave-types.store') }}" class="card-body">
            @csrf
            <div class="leave-type-fields">
                <div class="form-group"><label class="form-label" for="newLeaveCode">الرمز</label><input class="form-input" id="newLeaveCode" name="code" value="{{ old('code') }}" maxlength="40" required placeholder="ANNUAL"></div>
                <div class="form-group"><label class="form-label" for="newLeaveName">الاسم</label><input class="form-input" id="newLeaveName" name="name" value="{{ old('name') }}" maxlength="190" required placeholder="إجازة سنوية"></div>
                <div class="form-group"><label class="form-label" for="newLeaveDays">الحد السنوي بالأيام</label><input class="form-input" id="newLeaveDays" name="annual_days" type="number" min="0" max="366" step="0.5" value="{{ old('annual_days') }}" placeholder="دون سقف"></div>
                <div class="form-group"><label class="form-label" for="newLeavePaid">الأجر</label><select class="form-input" id="newLeavePaid" name="is_paid"><option value="1">مدفوعة</option><option value="0">غير مدفوعة</option></select></div>
            </div>
            <input type="hidden" name="is_active" value="1">
            <div class="form-group" style="margin-top:.75rem"><label class="form-label" for="newLeaveNotes">ملاحظات السياسة</label><textarea class="form-input" id="newLeaveNotes" name="notes" rows="2" maxlength="1500">{{ old('notes') }}</textarea></div>
            <button type="submit" class="btn btn-gold">إضافة النوع</button>
        </form>
    </div>

    <h2 class="page-heading" style="font-size:1.1rem">الأنواع المسجلة</h2>
    @forelse($leaveTypes as $type)
        <div class="card" style="margin-bottom:.75rem">
            <form method="POST" action="{{ route('attendance.leave-types.update', $type) }}" class="card-body">
                @csrf
                @method('PUT')
                <div class="leave-type-fields">
                    <div class="form-group"><label class="form-label" for="code-{{ $type->id }}">الرمز</label><input class="form-input" id="code-{{ $type->id }}" name="code" value="{{ $type->code }}" maxlength="40" required></div>
                    <div class="form-group"><label class="form-label" for="name-{{ $type->id }}">الاسم</label><input class="form-input" id="name-{{ $type->id }}" name="name" value="{{ $type->name }}" maxlength="190" required></div>
                    <div class="form-group"><label class="form-label" for="days-{{ $type->id }}">الحد السنوي بالأيام</label><input class="form-input" id="days-{{ $type->id }}" name="annual_days" type="number" min="0" max="366" step="0.5" value="{{ $type->annual_days }}" placeholder="دون سقف"></div>
                    <div class="form-group"><label class="form-label" for="paid-{{ $type->id }}">الأجر</label><select class="form-input" id="paid-{{ $type->id }}" name="is_paid"><option value="1" @selected($type->is_paid)>مدفوعة</option><option value="0" @selected(! $type->is_paid)>غير مدفوعة</option></select></div>
                </div>
                <div class="form-group" style="margin-top:.75rem"><label class="form-label" for="notes-{{ $type->id }}">ملاحظات السياسة</label><textarea class="form-input" id="notes-{{ $type->id }}" name="notes" rows="2" maxlength="1500">{{ $type->notes }}</textarea></div>
                <div class="leave-type-actions">
                    <input type="hidden" name="is_active" value="0">
                    <label><input type="checkbox" name="is_active" value="1" @checked($type->is_active)> متاح للطلبات الجديدة</label>
                    <button type="submit" class="btn btn-outline">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    @empty
        <div class="card card-body">لا توجد أنواع إجازات. أضف النوع الأول لتتمكن من استقبال الطلبات.</div>
    @endforelse
</div>

<style>
.leave-type-fields{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}
.leave-type-fields .form-group{min-width:0}
.leave-type-fields .form-input{width:100%}
.leave-type-actions{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:.75rem}
@media(max-width:850px){.leave-type-fields{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:530px){.leave-type-fields{grid-template-columns:1fr}.leave-type-actions{flex-wrap:wrap}}
</style>
@endsection
