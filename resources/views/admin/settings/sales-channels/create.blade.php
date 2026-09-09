@extends('layouts.app')
@section('title', 'إضافة قناة بيع')
@section('content')
<div class="page-header"><div><h1 class="page-heading">إضافة قناة بيع</h1><p class="page-subheading"><a href="{{ route('settings.sales-channels.index') }}">قنوات البيع</a> &laquo; إضافة</p></div></div>
<form action="{{ route('settings.sales-channels.store') }}" method="POST" enctype="multipart/form-data" style="max-width:1100px">
    @csrf
    @include('admin.settings.sales-channels._form')
    <div class="form-actions" style="display:flex;justify-content:flex-end;gap:.75rem;margin-top:1.25rem"><a class="btn btn-ghost" href="{{ route('settings.sales-channels.index') }}">إلغاء</a><button class="btn btn-gold" type="submit">حفظ قناة البيع</button></div>
</form>
@endsection
