@extends('layouts.app')
@section('title', 'إدخال كميات الجرد')
@section('content')
<div class="page-header">
    <h1 class="page-heading">إدخال كميات الجرد — {{ $stockCount->location?->name }}</h1>
</div>
<form action="{{ route('stock-counts.update', $stockCount) }}" method="POST">
    @csrf @method('PUT')
    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>المنتج</th><th>SKU</th><th>الكمية في النظام</th><th>الكمية الفعلية</th></tr></thead>
                <tbody>
                @foreach($stockCount->items as $item)
                    <tr>
                        <td>{{ $item->product?->name_ar ?? $item->product?->name }}</td>
                        <td><code>{{ $item->product?->sku }}</code></td>
                        <td>{{ number_format($item->system_quantity, 2) }}</td>
                        <td>
                            <input type="number" name="items[{{ $item->id }}][actual_quantity]"
                                class="form-input" style="max-width:120px"
                                value="{{ $item->actual_quantity ?? $item->system_quantity }}"
                                min="0" step="0.001">
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div style="margin-top:1rem;display:flex;gap:.75rem">
        <button class="btn btn-gold" type="submit">حفظ الكميات</button>
        <a href="{{ route('stock-counts.show', $stockCount) }}" class="btn btn-ghost">إلغاء</a>
    </div>
</form>
@endsection
