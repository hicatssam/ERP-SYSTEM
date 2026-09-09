@extends('layouts.app')
@section('title','تعديل المتغير')
@section('content')
<div class="page-header"><div><h1 class="page-heading">تعديل المتغير — {{ $product->name_ar ?? $product->name }}</h1></div></div>
<div class="card" style="max-width:1000px"><div class="card-body"><form method="POST" action="{{ route('products.variants.update',[$product,$variant]) }}" enctype="multipart/form-data">@csrf @method('PUT') @include('admin.products.variants._form')<div style="display:flex;gap:.75rem;margin-top:1.2rem"><button class="btn btn-gold">حفظ التغييرات</button><a href="{{ route('products.variants.index',$product) }}" class="btn btn-ghost">إلغاء</a></div></form></div></div>
@endsection
