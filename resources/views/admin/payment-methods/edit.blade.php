@extends('layouts.app')
@section('title', 'تعديل طريقة الدفع')
@section('page-title', 'تعديل طريقة الدفع')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-heading">تعديل: {{ $paymentMethod->name_ar }}</h1>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('payment-methods.index') }}" class="btn btn-ghost btn-sm">العودة</a>
    </div>
</div>

<div class="card" style="max-width:680px">
    <div class="card-header">
        <span class="card-title">بيانات طريقة الدفع</span>
    </div>
    <div class="card-body">
        <form action="{{ route('payment-methods.update', $paymentMethod) }}" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.payment-methods._form', ['method' => $paymentMethod])
            <div style="display:flex;gap:.75rem;margin-top:1.5rem">
                <button type="submit" class="btn btn-gold">حفظ التعديلات</button>
                <a href="{{ route('payment-methods.index') }}" class="btn btn-ghost">إلغاء</a>
            </div>
        </form>
    </div>
</div>
@endsection
