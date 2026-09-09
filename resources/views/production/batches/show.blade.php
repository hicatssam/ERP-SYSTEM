@extends('layouts.app')

@section('title', $batch->batch_number)
@section('page-title', 'دفعة الإنتاج')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">{{ $batch->batch_number }}</h1>
        <p class="page-subheading">
            {{ $batch->product?->name_ar ?: $batch->product?->name }}
            · {{ $batch->location?->name }}
            · {{ $batch->status->label() }}
        </p>
    </div>
    <div class="page-header-actions">
        @can('production.release')
            @if($batch->status->value === 'draft')
                <form method="POST" action="{{ route('production.batches.release', $batch) }}">@csrf<button class="btn btn-gold">حجز المواد والإفراج</button></form>
            @endif
        @endcan
        @can('production.start')
            @if($batch->status->value === 'released')
                <form method="POST" action="{{ route('production.batches.start', $batch) }}">@csrf<button class="btn btn-gold">صرف المواد وبدء الإنتاج</button></form>
            @endif
        @endcan
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:1rem">
    <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ number_format((float)$batch->planned_output_quantity, 3) }}</div><div class="stat-label">الناتج المخطط</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ $batch->actual_output_quantity !== null ? number_format((float)$batch->actual_output_quantity, 3) : '—' }}</div><div class="stat-label">الناتج الفعلي</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ number_format((float)$batch->accepted_output_quantity, 3) }}</div><div class="stat-label">المقبول</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ number_format((float)$batch->actual_material_cost, 4) }} ₪</div><div class="stat-label">تكلفة المواد الفعلية</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ number_format((float)$batch->actual_unit_cost, 4) }} ₪</div><div class="stat-label">تكلفة الوحدة المقبولة</div></div></div>
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="card-header"><span class="card-title">المواد</span></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>المادة</th><th>المخطط</th><th>المصروف</th><th>المستهلك فعليًا</th><th>الهدر</th><th>التكلفة الفعلية</th></tr></thead>
            <tbody>
            @foreach($batch->items as $item)
                <tr>
                    <td>{{ $item->ingredient_name_snapshot }}</td>
                    <td>{{ number_format((float)$item->planned_quantity, 3) }} {{ $item->unit_snapshot }}</td>
                    <td>{{ number_format((float)$item->issued_quantity, 3) }}</td>
                    <td>{{ $item->actual_consumed_quantity !== null ? number_format((float)$item->actual_consumed_quantity, 3) : '—' }}</td>
                    <td>{{ number_format((float)$item->waste_quantity, 3) }}</td>
                    <td>{{ number_format((float)$item->actual_cost, 4) }} ₪</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@can('production.finish')
@if($batch->status->value === 'in_progress')
<div class="card" style="margin-bottom:1rem">
    <div class="card-header"><span class="card-title">تسجيل الناتج والاستهلاك الفعلي</span></div>
    <form method="POST" action="{{ route('production.batches.finish', $batch) }}">
        @csrf
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">الناتج الفعلي *</label>
                    <input type="number" step="0.001" min="0.001" class="form-input" name="actual_output_quantity" value="{{ old('actual_output_quantity', $batch->planned_output_quantity) }}" required>
                </div>
                @if($batch->product?->tracks_expiry)
                <div class="form-group">
                    <label class="form-label">تاريخ انتهاء الناتج *</label>
                    <input type="date" class="form-input" name="output_expiry_date" required value="{{ old('output_expiry_date') }}">
                </div>
                @endif
            </div>
            <div class="table-wrap" style="margin-top:1rem">
                <table class="data-table">
                    <thead><tr><th>المادة</th><th>المصروف</th><th>الاستهلاك الفعلي *</th><th>الهدر</th></tr></thead>
                    <tbody>
                    @foreach($batch->items as $item)
                        <tr>
                            <td>{{ $item->ingredient_name_snapshot }}</td>
                            <td>{{ number_format((float)$item->issued_quantity, 3) }}</td>
                            <td>
                                <input class="form-input" type="number" step="0.001" min="0"
                                       name="items[{{ $item->id }}][actual_consumed_quantity]"
                                       value="{{ old("items.{$item->id}.actual_consumed_quantity", $item->issued_quantity) }}" required>
                            </td>
                            <td>
                                <input class="form-input" type="number" step="0.001" min="0"
                                       name="items[{{ $item->id }}][waste_quantity]"
                                       value="{{ old("items.{$item->id}.waste_quantity", 0) }}">
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer" style="text-align:left"><button class="btn btn-gold">حفظ الكميات وإنهاء التشغيل</button></div>
    </form>
</div>
@endif
@endcan

@if($batch->status->value === 'awaiting_quality')
<div class="alert alert-warning" style="margin-bottom:1rem">تم استهلاك المواد. الناتج لم يدخل المخزون بعد لأنه بانتظار قرار الجودة.</div>

@can('quality_control.decide')
<div class="card" style="margin-bottom:1rem">
    <div class="card-header"><span class="card-title">قرار فحص الجودة</span></div>
    <form method="POST" action="{{ route('production.quality.decide', $batch) }}">
        @csrf
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group"><label class="form-label">كمية مقبولة *</label><input class="form-input" type="number" step="0.001" min="0" name="accepted_quantity" value="{{ old('accepted_quantity', $batch->actual_output_quantity) }}" required></div>
                <div class="form-group"><label class="form-label">كمية مرفوضة *</label><input class="form-input" type="number" step="0.001" min="0" name="rejected_quantity" value="{{ old('rejected_quantity', 0) }}" required></div>
            </div>

            @php
                $defaultCriteria = old('criteria', [
                    ['criterion' => 'المظهر العام', 'result' => 'pass', 'notes' => ''],
                    ['criterion' => 'الوزن / الكمية', 'result' => 'pass', 'notes' => ''],
                    ['criterion' => 'الجودة الحسية / المطابقة', 'result' => 'pass', 'notes' => ''],
                ]);
            @endphp

            <div class="table-wrap" style="margin-top:1rem">
                <table class="data-table">
                    <thead><tr><th>المعيار</th><th>النتيجة</th><th>ملاحظات</th></tr></thead>
                    <tbody>
                    @foreach($defaultCriteria as $i => $criterion)
                        <tr>
                            <td><input class="form-input" name="criteria[{{ $i }}][criterion]" value="{{ $criterion['criterion'] }}" required></td>
                            <td>
                                <select class="form-input" name="criteria[{{ $i }}][result]" required>
                                    <option value="pass" @selected(($criterion['result'] ?? '') === 'pass')>ناجح</option>
                                    <option value="fail" @selected(($criterion['result'] ?? '') === 'fail')>غير ناجح</option>
                                    <option value="na" @selected(($criterion['result'] ?? '') === 'na')>غير منطبق</option>
                                </select>
                            </td>
                            <td><input class="form-input" name="criteria[{{ $i }}][notes]" value="{{ $criterion['notes'] ?? '' }}"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="form-group" style="margin-top:1rem"><label class="form-label">ملاحظات قرار الجودة</label><textarea class="form-input" name="notes" rows="3">{{ old('notes') }}</textarea></div>
        </div>
        <div class="card-footer" style="text-align:left"><button class="btn btn-gold">اعتماد قرار الجودة</button></div>
    </form>
</div>
@endcan
@endif

@can('production.cancel')
@if(in_array($batch->status->value, ['draft','released'], true))
<div class="card">
    <div class="card-header"><span class="card-title">إلغاء الدفعة</span></div>
    <form method="POST" action="{{ route('production.batches.cancel', $batch) }}">
        @csrf
        <div class="card-body"><input class="form-input" name="reason" required minlength="3" placeholder="سبب الإلغاء"></div>
        <div class="card-footer" style="text-align:left"><button class="btn btn-danger">إلغاء الدفعة</button></div>
    </form>
</div>
@endif
@endcan
@endsection
