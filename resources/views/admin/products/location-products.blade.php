@extends('layouts.app')
@section('title', 'توفر المنتج في الفروع')
@section('content')
<div class="page-header">
    <h1 class="page-heading">توفر: {{ $product->name_ar ?? $product->name }}</h1>
    <p class="page-subheading"><a href="{{ route('products.index') }}">المنتجات</a> &laquo; <a href="{{ route('products.show', $product) }}">{{ $product->name }}</a> &laquo; الفروع</p>
</div>
<div class="card">
    <div class="card-header"><span class="card-title">الفروع الحالية</span></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>الموقع</th><th>متوفر</th><th>السعر المحلي</th><th>الحد الأدنى للمخزون</th><th>الإجراءات</th></tr></thead>
            <tbody>
            @forelse($locationProducts as $lp)
                <tr>
                    <td>{{ $lp->location?->name }}</td>
                    <td><span class="badge {{ $lp->is_available ? 'badge-active' : 'badge-inactive' }}">{{ $lp->is_available ? 'نعم' : 'لا' }}</span></td>
                    <td>{{ $lp->local_selling_price ? '₪' . number_format($lp->local_selling_price, 2) : '—' }}</td>
                    <td>{{ $lp->minimum_stock_level ?? '—' }}</td>
                    <td>
                        <form action="{{ route('location-products.update', $lp) }}" method="POST" style="display:inline-flex;gap:.5rem">
                            @csrf @method('PUT')
                            <input type="number" name="local_selling_price" class="form-input" style="width:100px" step="0.01" value="{{ $lp->local_selling_price }}">
                            <input type="number" name="minimum_stock_level" class="form-input" style="width:80px" step="0.001" value="{{ $lp->minimum_stock_level }}">
                            <button class="btn btn-outline btn-sm" type="submit">حفظ</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state-sm">لا توجد فروع.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
