@extends('layouts.app')
@section('title', 'تعديل طلب الكيك')

@section('content')
@php
    $orderNumber = $cakeOrder->order_number ?? $cakeOrder->id;
@endphp

<div class="page-header cake-page-header">
    <div>
        <h1 class="page-heading">تعديل طلب الكيك</h1>
        <p class="page-subheading">
            <a href="{{ route('cake-orders.index') }}">طلبات الكيك</a>
            <span>‹</span>
            <a href="{{ route('cake-orders.show', $cakeOrder) }}">#{{ $orderNumber }}</a>
            <span>‹ تعديل</span>
        </p>
    </div>
    <a href="{{ route('cake-orders.show', $cakeOrder) }}" class="btn btn-ghost btn-sm">العودة للطلب</a>
</div>

@if($errors->any())
    <div class="cake-alert cake-alert-danger">
        <strong>تعذر حفظ التعديلات:</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
@if(session('error'))<div class="cake-alert cake-alert-danger">{{ session('error') }}</div>@endif
@if(session('success'))<div class="cake-alert cake-alert-success">{{ session('success') }}</div>@endif

<form action="{{ route('cake-orders.update', $cakeOrder) }}" method="POST" id="cakeEditForm">
    @csrf
    @method('PUT')

    <div class="cake-form-wrap">
        <section class="card">
            <div class="card-header"><span class="card-title">بيانات العميل والتسليم</span></div>
            <div class="card-body cake-grid cake-grid-3">
                <div class="form-group">
                    <label class="form-label">العميل</label>
                    <input class="form-input" value="{{ $cakeOrder->customer?->name }} — {{ $cakeOrder->customer?->phone }}" disabled>
                    <small class="form-help">لا يمكن تغيير العميل بعد إنشاء الطلب.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">تاريخ التسليم *</label>
                    <input type="date" name="required_date" class="form-input @error('required_date') is-invalid @enderror"
                           value="{{ old('required_date', $cakeOrder->required_date?->format('Y-m-d')) }}" required>
                    @error('required_date')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">وقت التسليم</label>
                    <input type="time" name="required_time" class="form-input @error('required_time') is-invalid @enderror"
                           value="{{ old('required_time', $cakeOrder->required_time ? \Carbon\Carbon::parse($cakeOrder->required_time)->format('H:i') : '') }}">
                    @error('required_time')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><span class="card-title">مواصفات الكيك</span></div>
            <div class="card-body">
                <div class="cake-grid cake-grid-3">
                    <div class="form-group">
                        <label class="form-label">نوع الكيك</label>
                        <select name="cake_type" class="form-select @error('cake_type') is-invalid @enderror">
                            <option value="">اختر النوع</option>
                            @foreach(['chocolate'=>'شوكولاتة','vanilla'=>'فانيلا','red_velvet'=>'ريد فيلفيت','caramel'=>'كراميل','fruit'=>'فاكهة','other'=>'أخرى'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('cake_type', $cakeOrder->cake_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('cake_type')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">الحجم</label>
                        <input name="cake_size" class="form-input @error('cake_size') is-invalid @enderror" value="{{ old('cake_size', $cakeOrder->cake_size) }}">
                        @error('cake_size')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوزن (كغم)</label>
                        <input type="number" min="0" step="0.1" name="cake_weight" class="form-input @error('cake_weight') is-invalid @enderror" value="{{ old('cake_weight', $cakeOrder->cake_weight) }}">
                        @error('cake_weight')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">عدد الأشخاص</label>
                        <input type="number" min="1" name="persons_count" class="form-input @error('persons_count') is-invalid @enderror" value="{{ old('persons_count', $cakeOrder->persons_count) }}">
                        @error('persons_count')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">النكهة</label>
                        <input name="flavor" class="form-input @error('flavor') is-invalid @enderror" value="{{ old('flavor', $cakeOrder->flavor) }}">
                        @error('flavor')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">الحشو</label>
                        <input name="filling" class="form-input @error('filling') is-invalid @enderror" value="{{ old('filling', $cakeOrder->filling) }}">
                        @error('filling')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">الشكل</label>
                        <select name="shape" class="form-select @error('shape') is-invalid @enderror">
                            <option value="">اختر الشكل</option>
                            @foreach(['round'=>'دائري','slab'=>'بلاطة','wedding_tiers'=>'طوابق أفراح','standard_tiers'=>'طوابق ستاندر'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('shape', $cakeOrder->shape) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('shape')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">اللون</label>
                        <input name="color" class="form-input @error('color') is-invalid @enderror" value="{{ old('color', $cakeOrder->color) }}">
                        @error('color')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">الثيم / الموضوع</label>
                        <input name="theme" class="form-input @error('theme') is-invalid @enderror" value="{{ old('theme', $cakeOrder->theme) }}">
                        @error('theme')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-group cake-space-top">
                    <label class="form-label">النص على الكيك</label>
                    <input name="cake_text" class="form-input @error('cake_text') is-invalid @enderror" value="{{ old('cake_text', $cakeOrder->cake_text) }}">
                    @error('cake_text')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group cake-space-top">
                    <label class="form-label">تعليمات خاصة</label>
                    <textarea name="special_instructions" class="form-textarea @error('special_instructions') is-invalid @enderror" rows="4">{{ old('special_instructions', $cakeOrder->special_instructions) }}</textarea>
                    @error('special_instructions')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><span class="card-title">السعر</span></div>
            <div class="card-body cake-grid cake-grid-2">
                <div class="form-group">
                    <label class="form-label">السعر الإجمالي (₪) *</label>
                    <input type="number" min="0" step="0.01" name="total_price" class="form-input @error('total_price') is-invalid @enderror"
                           value="{{ old('total_price', $cakeOrder->total_price) }}" required>
                    @error('total_price')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="price-summary">
                    <span>السعر الحالي</span>
                    <strong>₪{{ number_format((float) $cakeOrder->total_price, 2) }}</strong>
                </div>
            </div>
        </section>

        <div class="sticky-actions">
            <a href="{{ route('cake-orders.show', $cakeOrder) }}" class="btn btn-ghost">إلغاء</a>
            <button class="btn btn-gold" type="submit">حفظ التعديلات</button>
        </div>
    </div>
</form>

<style>
.cake-page-header{display:flex;align-items:center;justify-content:space-between;gap:1rem}.cake-form-wrap{display:grid;gap:1.5rem;max-width:1000px}.cake-grid{display:grid;gap:1rem}.cake-grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}.cake-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}.cake-space-top{margin-top:1rem}.form-help{display:block;margin-top:.35rem;color:var(--text-muted);font-size:.78rem}.form-error{display:block;margin-top:.35rem;color:#dc3545;font-size:.82rem}.is-invalid{border-color:#dc3545!important}.cake-alert{max-width:1000px;margin-bottom:1.25rem;padding:1rem 1.2rem;border-radius:12px}.cake-alert ul{margin:.6rem 0 0;padding-inline-start:1.2rem}.cake-alert-danger{color:#dc3545;background:rgba(220,53,69,.1);border:1px solid rgba(220,53,69,.45)}.cake-alert-success{color:#198754;background:rgba(25,135,84,.1);border:1px solid rgba(25,135,84,.4)}.price-summary{display:flex;align-items:center;justify-content:space-between;padding:1rem;color:var(--text-muted);background:rgba(212,175,55,.08);border:1px solid rgba(212,175,55,.3);border-radius:10px}.price-summary strong{color:var(--gold);font-size:1.15rem}.sticky-actions{position:sticky;bottom:0;z-index:20;display:flex;justify-content:flex-end;gap:.75rem;padding:1rem;background:color-mix(in srgb,var(--surface) 94%,transparent);border:1px solid var(--border);border-radius:12px;backdrop-filter:blur(10px)}
@media(max-width:800px){.cake-page-header{align-items:flex-start;flex-direction:column}.cake-grid-3,.cake-grid-2{grid-template-columns:1fr}.sticky-actions{position:static}}
</style>
@endsection