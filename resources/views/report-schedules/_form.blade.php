{{-- Shared form fields for create / edit --}}
@php
$old = fn(string $field, $fallback = null) => old($field, $schedule?->{$field} ?? $fallback);
@endphp

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:1rem">
    <ul style="margin:0;padding-right:1.25rem">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

{{-- Name --}}
<div class="form-group" style="margin-bottom:1rem">
    <label class="form-label">اسم الجدول <span style="color:var(--danger)">*</span></label>
    <input type="text" name="name" class="form-input @error('name') is-invalid @enderror"
           value="{{ $old('name') }}" placeholder="مثال: تقرير المبيعات اليومي الصباحي" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

{{-- Report Type --}}
<div class="form-group" style="margin-bottom:1rem">
    <label class="form-label">نوع التقرير <span style="color:var(--danger)">*</span></label>
    <select name="report_type" class="form-input @error('report_type') is-invalid @enderror" required>
        <option value="">— اختر التقرير —</option>
        @foreach($reportTypes as $value => $label)
        <option value="{{ $value }}" {{ $old('report_type') == $value ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    @error('report_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

{{-- Date Range --}}
<div class="form-group" style="margin-bottom:1rem">
    <label class="form-label">النطاق الزمني للتقرير <span style="color:var(--danger)">*</span></label>
    <select name="date_range" class="form-input @error('date_range') is-invalid @enderror" required>
        @foreach($dateRanges as $value => $label)
        <option value="{{ $value }}" {{ $old('date_range', 'last_7_days') == $value ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    <p style="font-size:.75rem;color:var(--text-muted);margin-top:.3rem">
        يُحسب النطاق الزمني عند وقت الإرسال الفعلي (مثلاً: "آخر 7 أيام" تعني الأيام السبعة التي تسبق يوم الإرسال).
    </p>
    @error('date_range')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

{{-- Location --}}
<div class="form-group" style="margin-bottom:1rem">
    <label class="form-label">الفرع / الموقع</label>
    @if($isAdmin)
    <select name="location_id" class="form-input @error('location_id') is-invalid @enderror">
        <option value="">جميع الفروع</option>
        @foreach($locations as $loc)
        <option value="{{ $loc->id }}" {{ $old('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
        @endforeach
    </select>
    <p style="font-size:.75rem;color:var(--text-muted);margin-top:.3rem">
        كمسؤول يمكنك اختيار فرع محدد أو ترك الحقل فارغاً لتضمين جميع الفروع.
    </p>
    @else
    {{-- Non-admins are locked to their primary location (enforced server-side) --}}
    @php $myLoc = $locations->first(); @endphp
    <input type="hidden" name="location_id" value="{{ $myLoc?->id }}">
    <input type="text" class="form-input" value="{{ $myLoc?->name ?? '—' }}" disabled>
    <p style="font-size:.75rem;color:var(--text-muted);margin-top:.3rem">
        التقرير مقيّد بفرعك الأساسي.
    </p>
    @endif
    @error('location_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

{{-- Frequency --}}
<div class="form-group" style="margin-bottom:1rem">
    <label class="form-label">تكرار الإرسال <span style="color:var(--danger)">*</span></label>
    <select name="frequency" id="frequency" class="form-input @error('frequency') is-invalid @enderror"
            required onchange="toggleWeeklyField()">
        <option value="daily"   {{ $old('frequency', 'daily') == 'daily'   ? 'selected' : '' }}>يومياً</option>
        <option value="weekly"  {{ $old('frequency') == 'weekly'  ? 'selected' : '' }}>أسبوعياً</option>
        <option value="monthly" {{ $old('frequency') == 'monthly' ? 'selected' : '' }}>شهرياً (أول يوم من الشهر)</option>
    </select>
    @error('frequency')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

{{-- Day of week (weekly only) --}}
<div id="weeklyField" class="form-group" style="margin-bottom:1rem;display:none">
    <label class="form-label">يوم الأسبوع</label>
    <select name="day_of_week" class="form-input">
        @php $days = ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت']; @endphp
        @foreach($days as $i => $day)
        <option value="{{ $i }}" {{ $old('day_of_week', 0) == $i ? 'selected' : '' }}>{{ $day }}</option>
        @endforeach
    </select>
</div>

{{-- Hour --}}
<div class="form-group" style="margin-bottom:1rem">
    <label class="form-label">وقت الإرسال (الساعة) <span style="color:var(--danger)">*</span></label>
    <select name="hour" class="form-input @error('hour') is-invalid @enderror" required>
        @for($h = 0; $h <= 23; $h++)
        <option value="{{ $h }}" {{ $old('hour', 8) == $h ? 'selected' : '' }}>
            {{ str_pad($h, 2, '0', STR_PAD_LEFT) }}:00
        </option>
        @endfor
    </select>
    <p style="font-size:.75rem;color:var(--text-muted);margin-top:.3rem">
        يعمل المجدول بتوقيت الخادم (UTC). تأكد من ضبط الساعة وفق التوقيت المحلي المناسب.
    </p>
    @error('hour')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

{{-- Recipients --}}
<div class="form-group" style="margin-bottom:1rem">
    <label class="form-label">المستلمون <span style="color:var(--danger)">*</span></label>
    <input type="text" name="recipients" class="form-input @error('recipients') is-invalid @enderror"
           value="{{ $old('recipients') }}"
           placeholder="example@domain.com, another@domain.com"
           required>
    <p style="font-size:.75rem;color:var(--text-muted);margin-top:.3rem">
        يمكن إدخال أكثر من بريد إلكتروني مفصولة بفاصلة.
    </p>
    @error('recipients')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

{{-- Active --}}
<div class="form-group" style="margin-bottom:1rem">
    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1"
               {{ $old('is_active', $schedule?->is_active ?? true) ? 'checked' : '' }}
               style="width:16px;height:16px">
        <span class="form-label" style="margin:0">مفعّل (سيُرسل تلقائياً في الوقت المحدد)</span>
    </label>
</div>

@push('scripts')
<script>
function toggleWeeklyField() {
    var freq  = document.getElementById('frequency').value;
    var field = document.getElementById('weeklyField');
    field.style.display = (freq === 'weekly') ? '' : 'none';
}
// Run on page load in case of validation failure / edit mode
document.addEventListener('DOMContentLoaded', toggleWeeklyField);
</script>
@endpush
