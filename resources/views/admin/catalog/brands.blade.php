@extends('layouts.app')
@section('title','العلامات التجارية')
@section('content')
<div class="page-header"><div><h1 class="page-heading">العلامات التجارية</h1><p class="page-subheading">علامة واحدة للمنتج الأساسي، ويمكن استخدامها في الملابس والأحذية والتجزئة.</p></div></div>
@include('admin.catalog._nav')
<div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">إضافة علامة</span></div><div class="card-body"><form method="POST" action="{{ route('catalog.brands.store') }}" enctype="multipart/form-data">@csrf
<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem"><input name="code" class="form-input" placeholder="NIKE" required><input name="name" class="form-input" placeholder="Name" required><input name="name_ar" class="form-input" placeholder="الاسم العربي"><input name="sort_order" type="number" min="0" value="0" class="form-input"></div>
<textarea name="description" class="form-textarea" placeholder="الوصف" style="margin-top:.75rem"></textarea><div style="margin-top:.75rem"><input type="file" name="logo" class="form-input" accept="image/*"></div><button class="btn btn-gold" style="margin-top:.75rem">إضافة</button></form></div></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>العلامة</th><th>الكود</th><th>المنتجات</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>@foreach($brands as $brand)<tr><td><strong>{{ $brand->displayName() }}</strong></td><td><code>{{ $brand->code }}</code></td><td>{{ $brand->products_count }}</td><td><span class="badge {{ $brand->is_active?'badge-active':'badge-inactive' }}">{{ $brand->is_active?'مفعلة':'معطلة' }}</span></td><td><form method="POST" action="{{ route('catalog.brands.toggle',$brand) }}">@csrf<button class="btn btn-ghost btn-sm">{{ $brand->is_active?'تعطيل':'تفعيل' }}</button></form></td></tr>@endforeach</tbody></table></div>
@endsection
