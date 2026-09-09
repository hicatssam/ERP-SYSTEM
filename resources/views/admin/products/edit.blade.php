@extends('layouts.app')
@section('title','تعديل المنتج')
@section('content')
<div class="page-header"><div><h1 class="page-heading">تعديل {{ $product->name_ar ?? $product->name }}</h1><p class="page-subheading"><a href="{{ route('products.show',$product) }}">المنتج</a> &laquo; تعديل</p></div>@if($variantsEnabled && Route::has('products.variants.index'))<a href="{{ route('products.variants.index',$product) }}" class="btn btn-outline btn-sm">إدارة المتغيرات</a>@endif</div>
<div class="card" style="max-width:1000px"><div class="card-body"><form action="{{ route('products.update',$product) }}" method="POST" enctype="multipart/form-data">@csrf @method('PUT') @include('admin.products._form')<div style="display:flex;gap:.75rem;margin-top:1.25rem"><button class="btn btn-gold">حفظ التغييرات</button><a href="{{ route('products.show',$product) }}" class="btn btn-ghost">إلغاء</a></div></form></div></div>
@endsection
