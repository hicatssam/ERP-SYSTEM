@extends('layouts.app')

@section('title', 'إنشاء دفعة إنتاج')
@section('page-title', 'إنشاء دفعة إنتاج')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">دفعة إنتاج جديدة</h1>
        <p class="page-subheading">الإنشاء لا يغيّر المخزون. حجز المواد يبدأ عند الإفراج.</p>
    </div>
</div>

<form method="POST" action="{{ route('production.batches.store') }}" style="max-width:1000px">
    @csrf
    <div class="card">
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">الوصفة المعتمدة *</label>
                    <select name="recipe_id" class="form-input" required>
                        <option value="">اختر الوصفة</option>
                        @foreach($recipes as $recipe)
                            <option value="{{ $recipe->id }}" @selected((string)old('recipe_id') === (string)$recipe->id)>
                                {{ $recipe->product?->name_ar ?: $recipe->product?->name }} — {{ $recipe->name }} V{{ $recipe->version }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">موقع الإنتاج *</label>
                    <select name="location_id" class="form-input" required>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((string)old('location_id', $selectedLocation->id) === (string)$location->id)>
                                {{ $location->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">كمية الناتج المخططة *</label>
                    <input type="number" step="0.001" min="0.001" name="planned_output_quantity" class="form-input" required value="{{ old('planned_output_quantity') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">تاريخ الإنتاج المخطط</label>
                    <input type="date" name="planned_date" class="form-input" value="{{ old('planned_date', now()->toDateString()) }}">
                </div>
            </div>
            <div class="form-group" style="margin-top:1rem">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-input" rows="4">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="card-footer" style="display:flex;justify-content:flex-end;gap:.75rem">
            <a class="btn btn-ghost" href="{{ route('production.batches.index') }}">إلغاء</a>
            <button class="btn btn-gold">إنشاء المسودة</button>
        </div>
    </div>
</form>
@endsection
