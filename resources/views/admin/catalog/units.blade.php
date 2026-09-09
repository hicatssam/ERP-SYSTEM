@extends('layouts.app')
@section('title','وحدات القياس')
@section('content')
<div class="page-header"><div><h1 class="page-heading">وحدات القياس</h1><p class="page-subheading">الوحدات المستخدمة في المنتجات الحالية والمستقبلية.</p></div></div>
@include('admin.catalog._nav')
<div class="dashboard-row">
<div class="card"><div class="card-header"><span class="card-title">إضافة وحدة</span></div><div class="card-body">
<form method="POST" action="{{ route('catalog.units.store') }}">@csrf
<div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
<div class="form-group"><label class="form-label">الكود *</label><input name="code" class="form-input" required placeholder="piece"></div>
<div class="form-group"><label class="form-label">الاسم الإنجليزي *</label><input name="name" class="form-input" required></div>
<div class="form-group"><label class="form-label">الاسم العربي</label><input name="name_ar" class="form-input"></div>
<div class="form-group"><label class="form-label">الرمز</label><input name="symbol" class="form-input"></div>
<div class="form-group"><label class="form-label">الدقة العشرية</label><input name="precision" type="number" min="0" max="6" value="0" class="form-input"></div>
<div class="form-group"><label class="form-label">الترتيب</label><input name="sort_order" type="number" min="0" value="0" class="form-input"></div>
</div>
<label style="display:flex;gap:.5rem;align-items:center;margin:.8rem 0"><input type="checkbox" name="allow_decimal" value="1"> السماح بالكميات العشرية</label>
<button class="btn btn-gold">إضافة الوحدة</button>
</form></div></div>
<div class="card"><div class="card-header"><span class="card-title">الوحدات الحالية</span></div><div class="table-wrap" style="border:0"><table class="data-table"><thead><tr><th>الوحدة</th><th>الكود</th><th>الرمز</th><th>الدقة</th><th>منتجات</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>
@foreach($units as $unit)<tr><td><strong>{{ $unit->displayName() }}</strong></td><td><code>{{ $unit->code }}</code></td><td>{{ $unit->symbol ?: '—' }}</td><td>{{ $unit->allow_decimal ? $unit->precision : 0 }}</td><td>{{ $unit->products_count }}</td><td><span class="badge {{ $unit->is_active?'badge-active':'badge-inactive' }}">{{ $unit->is_active?'مفعلة':'معطلة' }}</span></td><td><form method="POST" action="{{ route('catalog.units.toggle',$unit) }}">@csrf<button class="btn btn-ghost btn-sm">{{ $unit->is_active?'تعطيل':'تفعيل' }}</button></form></td></tr>@endforeach
</tbody></table></div></div>
</div>
@endsection
