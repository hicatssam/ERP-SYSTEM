@extends('layouts.app')
@section('title','المقاسات')
@section('content')
<div class="page-header"><div><h1 class="page-heading">المقاسات</h1><p class="page-subheading">مقاسات مرنة: S/M/L أو 38/40/42 أو أي نظام آخر.</p></div></div>
@include('admin.catalog._nav')
<div class="card" style="margin-bottom:1rem"><div class="card-body"><form method="POST" action="{{ route('catalog.sizes.store') }}">@csrf<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem"><input name="code" class="form-input" placeholder="M أو 40" required><input name="name" class="form-input" placeholder="Medium" required><input name="name_ar" class="form-input" placeholder="متوسط"><input name="sort_order" type="number" value="0" min="0" class="form-input"></div><button class="btn btn-gold" style="margin-top:.75rem">إضافة مقاس</button></form></div></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>المقاس</th><th>الكود</th><th>متغيرات</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>@foreach($sizes as $size)<tr><td><strong>{{ $size->displayName() }}</strong></td><td><code>{{ $size->code }}</code></td><td>{{ $size->variants_count }}</td><td><span class="badge {{ $size->is_active?'badge-active':'badge-inactive' }}">{{ $size->is_active?'مفعل':'معطل' }}</span></td><td><form method="POST" action="{{ route('catalog.sizes.toggle',$size) }}">@csrf<button class="btn btn-ghost btn-sm">{{ $size->is_active?'تعطيل':'تفعيل' }}</button></form></td></tr>@endforeach</tbody></table></div>
@endsection
