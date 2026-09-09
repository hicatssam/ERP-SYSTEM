@extends('layouts.app')
@section('title', 'تعديل فئة')
@section('content')
<div class="page-header">
    <h1 class="page-heading">تعديل: {{ $category->name }}</h1>
    <p class="page-subheading"><a href="{{ route('categories.index') }}">الفئات</a> &laquo; تعديل</p>
</div>
<div class="card" style="max-width:540px">
    <div class="card-body">
        <form action="{{ route('categories.update', $category) }}" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div style="display:grid;gap:1.25rem">
                <div class="form-group">
                    <label class="form-label">الاسم (إنجليزي) *</label>
                    <input name="name" class="form-input @error('name') is-invalid @enderror" value="{{ old('name', $category->name) }}" required>
                    @error('name')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">الاسم (عربي)</label>
                    <input name="name_ar" class="form-input" value="{{ old('name_ar', $category->name_ar) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">ترتيب العرض</label>
                    <input name="sort_order" type="number" class="form-input" value="{{ old('sort_order', $category->sort_order) }}" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">الوصف</label>
                    <textarea name="description" class="form-textarea">{{ old('description', $category->description) }}</textarea>
                </div>

                {{-- Image upload --}}
                <div class="form-group">
                    <label class="form-label">صورة الفئة</label>
                    <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
                        <div style="width:100px;height:100px;border:2px dashed var(--border);border-radius:10px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:var(--surface);flex-shrink:0">
                            @if($category->image)
                                <img id="imgPreview" src="{{ Storage::url($category->image) }}" alt="" style="width:100%;height:100%;object-fit:cover">
                            @else
                                <img id="imgPreview" src="" alt="" style="width:100%;height:100%;object-fit:cover;display:none">
                                <svg id="imgIcon" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5" style="width:36px;height:36px"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                            @endif
                        </div>
                        <div style="flex:1;min-width:180px;display:grid;gap:.5rem">
                            <div>
                                <label for="imageInput" style="display:inline-block;cursor:pointer;padding:.5rem 1rem;background:var(--surface);border:1px solid var(--border);border-radius:8px;font-size:.875rem;color:var(--text)">
                                    {{ $category->image ? 'تغيير الصورة' : 'اختر صورة' }}
                                </label>
                                <input type="file" id="imageInput" name="image" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="previewImg(this,'imgPreview','imgIcon')">
                            </div>
                            @if($category->image)
                            <label style="display:flex;align-items:center;gap:.4rem;font-size:.8rem;color:var(--text-muted);cursor:pointer">
                                <input type="checkbox" name="remove_image" value="1"> حذف الصورة الحالية
                            </label>
                            @endif
                            <p style="font-size:.75rem;color:var(--text-muted)">JPEG · PNG · WebP — حتى 2MB</p>
                            @error('image')<span class="form-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:.75rem">
                    <button class="btn btn-gold" type="submit">حفظ التغييرات</button>
                    <a href="{{ route('categories.index') }}" class="btn btn-ghost">إلغاء</a>
                </div>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
function previewImg(input, previewId, iconId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.getElementById(previewId);
            const icon = document.getElementById(iconId);
            img.src = e.target.result;
            img.style.display = 'block';
            if (icon) icon.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endpush
@endsection
