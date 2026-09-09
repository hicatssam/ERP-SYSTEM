@extends('layouts.app')

@section('title', 'أمر إنتاج جديد')
@section('page-title', 'أمر إنتاج جديد')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">أمر إنتاج جديد</h1>
        <p class="page-subheading">الأمر يبدأ كمسودة، ولا يتم صرف أي مادة قبل الاعتماد ثم البدء.</p>
    </div>
    <div class="page-header-actions"><a class="btn btn-ghost" href="{{ route('production.orders.index') }}">رجوع</a></div>
</div>

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        <ul style="margin:0 1rem 0 0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('production.orders.store') }}" style="max-width:1000px">
    @csrf
    <div class="card">
        <div class="card-header"><span class="card-title">بيانات الأمر</span></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">الوصفة الفعّالة *</label>
                    <select class="form-input" name="recipe_id" required>
                        <option value="">اختر الوصفة</option>
                        @foreach($recipes as $recipe)
                            <option value="{{ $recipe->id }}" @selected((string)old('recipe_id')===(string)$recipe->id)>
                                {{ $recipe->product?->name_ar ?: $recipe->product?->name }} — {{ $recipe->name }} v{{ $recipe->version }}
                                (Batch {{ number_format((float)$recipe->yield_quantity,3) }})
                            </option>
                        @endforeach
                    </select>
                    @if($recipes->isEmpty())
                        <small style="color:var(--error)">لا توجد وصفات فعّالة. فعّل وصفة أولًا.</small>
                    @endif
                </div>

                <div class="form-group">
                    <label class="form-label">موقع الإنتاج *</label>
                    <select class="form-input" name="location_id" required>
                        <option value="">اختر الموقع</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((string)old('location_id')===(string)$location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">الكمية المخططة للناتج *</label>
                    <input class="form-input" type="number" step=".001" min=".001" name="planned_output_quantity" value="{{ old('planned_output_quantity') }}" required>
                </div>

                <div class="form-group">
                    <label class="form-label">موعد التخطيط</label>
                    <input class="form-input" type="datetime-local" name="planned_at" value="{{ old('planned_at') }}">
                </div>
            </div>

            <div class="form-group" style="margin-top:1rem">
                <label class="form-label">ملاحظات</label>
                <textarea class="form-input" name="notes" rows="3">{{ old('notes') }}</textarea>
            </div>

            <div style="margin-top:1rem;padding:.8rem;border-radius:8px;background:rgba(201,133,22,.08);font-size:.8rem">
                عند <strong>الاعتماد</strong> يتم تجميد إصدار الوصفة وكميات BOM وتكلفتها المعيارية.
                عند <strong>البدء</strong> يتم فحص المخزون تحت قفل معاملات ثم صرف المواد.
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:.7rem;margin-top:1rem">
        <a class="btn btn-ghost" href="{{ route('production.orders.index') }}">إلغاء</a>
        <button class="btn btn-gold" type="submit" @disabled($recipes->isEmpty() || $locations->isEmpty())>إنشاء المسودة</button>
    </div>
</form>
@endsection
