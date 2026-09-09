@extends('layouts.app')
@section('title','إضافة منتج')
@section('content')
<div class="page-header"><div><h1 class="page-heading">إضافة منتج جديد</h1><p class="page-subheading"><a href="{{ route('products.index') }}">المنتجات</a> &laquo; إضافة</p></div>
@can('products.update')
@if(Route::has('catalog.index'))<a href="{{ route('catalog.index') }}" class="btn btn-outline btn-sm">إعدادات الكتالوج</a>
@endif
@endcan</div>
<div class="card" style="max-width:1000px"><div class="card-body">
    <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
        @csrf 
        @include('admin.products._form')<div style="display:flex;gap:.75rem;margin-top:1.25rem">
            <button class="btn btn-gold">حفظ المنتج</button><a href="{{ route('products.index') }}" class="btn btn-ghost">إلغاء</a></div></form></div></div>
@endsection
