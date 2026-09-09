@extends('layouts.app')
@section('title', 'بدء جرد')
@section('content')
<div class="page-header">
    <h1 class="page-heading">بدء جرد مخزون جديد</h1>
    <p class="page-subheading"><a href="{{ route('stock-counts.index') }}">الجرد</a> &laquo; بدء جديد</p>
</div>
<div class="card" style="max-width:500px">
    <div class="card-body">
        <form action="{{ route('stock-counts.store') }}" method="POST">
            @csrf
            <div style="display:grid;gap:1.25rem">
                <div class="form-group">
                    <label class="form-label">الموقع *</label>
                    <select name="location_id" class="form-select" required>
                        <option value="">اختر موقعاً</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="background:var(--warning-bg);border-radius:var(--radius);padding:.875rem;font-size:.875rem;color:var(--warning)">
                    سيتم تحميل جميع عناصر المخزون الحالية في الموقع المحدد. أدخل الكميات الفعلية لكل منتج.
                </div>
                <div style="display:flex;gap:.75rem">
                    <button class="btn btn-gold" type="submit">بدء الجرد</button>
                    <a href="{{ route('stock-counts.index') }}" class="btn btn-ghost">إلغاء</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
