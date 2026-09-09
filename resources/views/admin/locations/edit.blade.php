@extends('layouts.app')
@section('title', 'تعديل موقع')
@section('content')
<div class="page-header">
    <h1 class="page-heading">تعديل: {{ $location->name }}</h1>
    <p class="page-subheading"><a href="{{ route('locations.index') }}">المواقع</a> &laquo; تعديل</p>
</div>

<div class="card" style="max-width:600px">
    <div class="card-body">
        <form action="{{ route('locations.update', $location) }}" method="POST">
            @csrf @method('PUT')
            <div style="display:grid;gap:1.25rem">
                <div class="form-group">
                    <label class="form-label">اسم الموقع *</label>
                    <input name="name" class="form-input @error('name') is-invalid @enderror" value="{{ old('name', $location->name) }}" required>
                    @error('name')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">الرمز *</label>
                    <input name="code" class="form-input @error('code') is-invalid @enderror" value="{{ old('code', $location->code) }}" required>
                    @error('code')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">النوع *</label>
                    <select name="type" class="form-select" required>
                        <option value="branch" {{ old('type', $location->type) == 'branch' ? 'selected' : '' }}>فرع</option>
                        <option value="factory" {{ old('type', $location->type) == 'factory' ? 'selected' : '' }}>مصنع</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">الهاتف</label>
                    <input name="phone" class="form-input" value="{{ old('phone', $location->phone) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">العنوان</label>
                    <textarea name="address" class="form-textarea">{{ old('address', $location->address) }}</textarea>
                </div>
                <div style="display:flex;gap:.75rem">
                    <button class="btn btn-gold" type="submit">حفظ التغييرات</button>
                    <a href="{{ route('locations.show', $location) }}" class="btn btn-ghost">إلغاء</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
