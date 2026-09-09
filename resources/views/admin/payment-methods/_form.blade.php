@php $m = $method ?? null; @endphp

@if($errors->any())
<div class="alert alert-error" style="margin-bottom:1rem">
    <ul style="margin:0;padding-right:1.2rem">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
    <div class="form-group">
        <label class="form-label">الاسم (بالإنجليزية) <span style="color:var(--danger)">*</span></label>
        <input type="text" name="name" class="form-input" value="{{ old('name', $m?->name) }}" required>
    </div>
    <div class="form-group">
        <label class="form-label">الاسم بالعربية <span style="color:var(--danger)">*</span></label>
        <input type="text" name="name_ar" class="form-input" value="{{ old('name_ar', $m?->name_ar) }}" required>
    </div>
    <div class="form-group">
        <label class="form-label">الكود (فريد) <span style="color:var(--danger)">*</span></label>
        <input type="text" name="code" class="form-input" value="{{ old('code', $m?->code) }}" required placeholder="مثال: CASH, PAL_PAY">
        <small style="color:var(--text-muted)">يُستخدم داخلياً — يُحوَّل للحروف الكبيرة تلقائياً</small>
    </div>
    <div class="form-group">
        <label class="form-label">النوع <span style="color:var(--danger)">*</span></label>
        <select name="type" class="form-input form-select" required>
            @foreach(['cash'=>'نقداً','electronic_wallet'=>'محفظة إلكترونية','bank_transfer'=>'تحويل بنكي','card_pos'=>'بطاقة POS','other'=>'أخرى'] as $val => $label)
            <option value="{{ $val }}" {{ old('type', $m?->type) == $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label class="form-label">ترتيب العرض</label>
        <input type="number" name="sort_order" class="form-input" value="{{ old('sort_order', $m?->sort_order ?? 99) }}" min="0">
    </div>
    <div class="form-group">
        <label class="form-label">الشعار</label>
        <input type="file" name="logo" class="form-input" accept="image/*" onchange="previewLogo(this)">
        @if($m?->logo_path || $m?->logo)
        <div style="margin-top:.5rem">
            <img id="logoPreview" src="{{ $m->logo_path ? Storage::url($m->logo_path) : $m->logo }}" style="width:60px;height:60px;object-fit:contain;border:1px solid var(--border);border-radius:6px">
        </div>
        @else
        <img id="logoPreview" style="display:none;width:60px;height:60px;object-fit:contain;border:1px solid var(--border);border-radius:6px;margin-top:.5rem">
        @endif
    </div>
</div>

<div class="form-group" style="margin-top:.5rem">
    <label class="form-label">الوصف</label>
    <textarea name="description" class="form-input" rows="2">{{ old('description', $m?->description) }}</textarea>
</div>

<div class="form-group">
    <label class="form-label">API Key (اختياري)</label>
    <input type="text" name="api_key" class="form-input" value="{{ old('api_key', $m?->api_key) }}" placeholder="مفتاح API إن وجد">
</div>

<div class="form-group">
    <label class="form-label">ملاحظات</label>
    <textarea name="notes" class="form-input" rows="2">{{ old('notes', $m?->notes) }}</textarea>
</div>

<div style="display:flex;flex-wrap:wrap;gap:1.5rem;margin-top:.5rem">
    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $m?->is_active ?? true) ? 'checked' : '' }}>
        <span>مفعّل</span>
    </label>
    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
        <input type="checkbox" name="requires_verification" value="1" {{ old('requires_verification', $m?->requires_verification) ? 'checked' : '' }}>
        <span>يتطلب تحقق</span>
    </label>
    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
        <input type="checkbox" name="requires_reference" value="1" {{ old('requires_reference', $m?->requires_reference) ? 'checked' : '' }}>
        <span>يتطلب رقم مرجعي</span>
    </label>
</div>

@push('scripts')
<script>
function previewLogo(input) {
    const preview = document.getElementById('logoPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endpush
