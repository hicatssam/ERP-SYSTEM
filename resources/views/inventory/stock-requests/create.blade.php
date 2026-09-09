@extends('layouts.app')
@section('title', 'طلب مخزون جديد')
@section('content')
<div class="page-header">
    <h1 class="page-heading">طلب مخزون جديد</h1>
</div>
<form action="{{ route('stock-requests.store') }}" method="POST">
    @csrf
    <div class="card" style="max-width:800px">
        <div class="card-header"><span class="card-title">العناصر المطلوبة</span></div>
        <div class="card-body">
            <div id="items-container">
                <div class="item-row" style="display:grid;grid-template-columns:1fr 150px 40px;gap:.75rem;margin-bottom:.75rem;align-items:center">
                    <select name="items[0][product_id]" class="form-select" required>
                        <option value="">اختر منتجاً</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}">{{ $p->name_ar ?? $p->name }} ({{ $p->sku }})</option>
                        @endforeach
                    </select>
                    <input type="number" name="items[0][quantity]" class="form-input" placeholder="الكمية" min="0.001" step="0.001" required>
                    <span></span>
                </div>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" id="addItem">+ إضافة منتج</button>
        </div>
    </div>

    <div class="card mt-4" style="max-width:800px">
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-textarea"></textarea>
            </div>
            <div style="margin-top:1rem;display:flex;gap:.75rem">
                <button class="btn btn-gold" type="submit">إرسال الطلب</button>
                <a href="{{ route('stock-requests.index') }}" class="btn btn-ghost">إلغاء</a>
            </div>
        </div>
    </div>
</form>

<script>
let idx = 1;
document.getElementById('addItem').addEventListener('click', function() {
    const c = document.getElementById('items-container');
    const div = document.createElement('div');
    div.className = 'item-row';
    div.style.cssText = 'display:grid;grid-template-columns:1fr 150px 40px;gap:.75rem;margin-bottom:.75rem;align-items:center';
    div.innerHTML = `<select name="items[${idx}][product_id]" class="form-select" required>
        <option value="">اختر منتجاً</option>
        @foreach($products as $p)
            <option value="{{ $p->id }}">{{ addslashes($p->name_ar ?? $p->name) }}</option>
        @endforeach
    </select>
    <input type="number" name="items[${idx}][quantity]" class="form-input" placeholder="الكمية" min="0.001" step="0.001" required>
    <button type="button" onclick="this.closest('.item-row').remove()" style="background:none;border:none;color:var(--error);font-size:1.25rem;cursor:pointer">×</button>`;
    c.appendChild(div);
    idx++;
});
</script>
@endsection
