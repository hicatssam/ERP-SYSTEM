@extends('layouts.app')
@section('title', 'تفاصيل التحويل')
@section('content')
<div class="page-actions">
    <div class="page-actions-title">تحويل: {{ $stockTransfer->transfer_number }}</div>
    <div class="action-btns">
        @if($stockTransfer->status === 'pending_dispatch')
            @can('stock_transfers.dispatch')
            <form action="{{ route('stock-transfers.dispatch', $stockTransfer) }}" method="POST" style="display:inline">@csrf
                <button class="btn btn-gold btn-sm" data-confirm="إرسال التحويل؟">إرسال</button>
            </form>
            @endcan
        @endif
        @if($stockTransfer->status === 'dispatched')
            @can('stock_transfers.receive')
            <button class="btn btn-gold btn-sm" onclick="document.getElementById('receiveForm').style.display='block'">تأكيد الاستلام</button>
            @endcan
        @endif
        <a href="{{ route('stock-transfers.index') }}" class="btn btn-ghost btn-sm">رجوع</a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>المنتج</th><th>الكمية المرسلة</th><th>الكمية المستلمة</th><th>التالف</th></tr></thead>
            <tbody>
            @foreach($stockTransfer->items as $item)
                <tr>
                    <td>{{ $item->product?->name_ar ?? $item->product?->name }}</td>
                    <td>{{ number_format($item->sent_quantity, 2) }}</td>
                    <td>{{ $item->received_quantity !== null ? number_format($item->received_quantity, 2) : '—' }}</td>
                    <td>{{ $item->damaged_quantity ? number_format($item->damaged_quantity, 2) : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@can('stock_transfers.receive')
@if($stockTransfer->status === 'dispatched')
<div class="card mt-4" id="receiveForm" style="display:none">
    <div class="card-header"><span class="card-title">تأكيد الاستلام</span></div>
    <div class="card-body">
        <form action="{{ route('stock-transfers.receive', $stockTransfer) }}" method="POST">
            @csrf @method('PATCH')
            @foreach($stockTransfer->items as $item)
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;margin-bottom:.75rem;align-items:center">
                <span>{{ $item->product?->name_ar ?? $item->product?->name }}</span>
                <div class="form-group">
                    <label class="form-label" style="font-size:.75rem">الكمية المستلمة</label>
                    <input type="number" name="items[{{ $item->id }}][received_quantity]" class="form-input" value="{{ $item->sent_quantity }}" min="0" step="0.001">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size:.75rem">التالف</label>
                    <input type="number" name="items[{{ $item->id }}][damaged_quantity]" class="form-input" value="0" min="0" step="0.001">
                </div>
            </div>
            @endforeach
            <div class="form-group" style="margin-top:1rem">
                <label class="form-label">ملاحظات الاستلام</label>
                <textarea name="receiving_notes" class="form-textarea"></textarea>
            </div>
            <div style="margin-top:1rem;display:flex;gap:.75rem">
                <button class="btn btn-gold" type="submit">تأكيد الاستلام</button>
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('receiveForm').style.display='none'">إلغاء</button>
            </div>
        </form>
    </div>
</div>
@endif
@endcan
@endsection
