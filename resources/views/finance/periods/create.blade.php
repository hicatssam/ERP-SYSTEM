@extends('layouts.app')
@section('title', 'فترة مالية جديدة')
@section('content')
<div class="page-header"><h1 class="page-heading">فتح فترة مالية جديدة</h1></div>
<div class="card" style="max-width:500px"><div class="card-body">
    <form action="{{ route('financial-periods.store') }}" method="POST">
        @csrf
        <div style="display:grid;gap:1.25rem">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group"><label class="form-label">السنة *</label><input type="number" name="year" class="form-input" value="{{ old('year', now()->year) }}" min="2020" max="2100" required></div>
                <div class="form-group"><label class="form-label">الشهر *</label>
                    <select name="month" class="form-select" required>
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ (old('month', now()->month) == $m) ? 'selected' : '' }}>{{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</option>
                        @endfor
                    </select>
                </div>
            </div>
            <div class="form-group"><label class="form-label">رصيد افتتاحي (₪)</label><input type="number" name="opening_balance" class="form-input" step="0.01" min="0" value="{{ old('opening_balance', 0) }}"></div>
            <div class="form-group"><label class="form-label">ملاحظات</label><textarea name="notes" class="form-textarea">{{ old('notes') }}</textarea></div>
            <div style="display:flex;gap:.75rem"><button class="btn btn-gold" type="submit">فتح الفترة</button><a href="{{ route('financial-periods.index') }}" class="btn btn-ghost">إلغاء</a></div>
        </div>
    </form>
</div></div>
@endsection
