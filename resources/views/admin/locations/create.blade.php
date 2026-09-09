@extends('layouts.app')
@section('title', 'إضافة موقع جديد')
@section('content')
<div class="page-header">
    <h1 class="page-heading">إضافة موقع جديد</h1>
    <p class="page-subheading"><a href="{{ route('locations.index') }}">المواقع</a> &laquo; إضافة</p>
</div>

<div class="card" style="max-width:600px">
    <div class="card-body">
        <form action="{{ route('locations.store') }}" method="POST">
            @csrf
            <div style="display:grid;gap:1.25rem">
                <div class="form-group">
                    <label class="form-label">اسم الموقع <span style="color:var(--error)">*</span></label>
                    <input name="name" class="form-input @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                    @error('name')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">الرمز <span style="color:var(--error)">*</span></label>
                    <input name="code" class="form-input @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="B01" required>
                    @error('code')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">النوع <span style="color:var(--error)">*</span></label>
                    <select name="type" class="form-select" required>
                        <option value="branch" {{ old('type') == 'branch' ? 'selected' : '' }}>فرع</option>
                        <option value="factory" {{ old('type') == 'factory' ? 'selected' : '' }}>مصنع</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">الهاتف</label>
                    <input name="phone" class="form-input" value="{{ old('phone') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">العنوان</label>
                    <textarea name="address" class="form-textarea">{{ old('address') }}</textarea>
                </div>
                <div style="display:flex;gap:.75rem">
                    <button class="btn btn-gold" type="submit">حفظ</button>
                    <a href="{{ route('locations.index') }}" class="btn btn-ghost">إلغاء</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
