@extends('layouts.app')
@section('title', 'جلسات الكاشير')
@section('content')
<div class="page-actions"><div class="page-actions-title">جلسات الكاشير</div>
    <div class="action-btns">
        @if(!$activeSession)
        <button type="button" class="btn btn-gold btn-sm" data-open-dialog="cash-open-dialog">فتح جلسة جديدة</button>
        @endif
    </div>
</div>

@if($activeSession)
<div class="alert alert-banner alert-banner-warning mt-4" style="margin-bottom:1rem">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span>جلسة كاشير مفتوحة منذ {{ $activeSession->opened_at->diffForHumans() }}</span>
    <button type="button" class="btn btn-gold btn-sm alert-link" data-open-dialog="cash-close-dialog">إغلاق الجلسة</button>
</div>
@endif

<x-action-dialog id="cash-open-dialog" title="فتح جلسة كاشير" description="أدخل النقد الفعلي الموجود عند بداية الجلسة.">
            <form action="{{ route('cash-sessions.open') }}" method="POST">
                @csrf
                <input type="hidden" name="_modal" value="cash-open-dialog">
                <div style="display:grid;gap:1rem">
                    <div class="form-group"><label class="form-label">رصيد الفتح (₪) *</label><input type="number" name="opening_balance" class="form-input" min="0" step="0.01" value="{{ old('_modal') === 'cash-open-dialog' ? old('opening_balance', 0) : 0 }}" required></div>
                    <div style="display:flex;gap:.75rem"><button class="btn btn-gold" type="submit">فتح</button><button type="button" class="btn btn-ghost" data-close-dialog>إلغاء</button></div>
                </div>
            </form>
</x-action-dialog>

@if($activeSession)
<x-action-dialog id="cash-close-dialog" title="إغلاق جلسة الكاشير" description="راجع النقد الفعلي قبل تأكيد الإغلاق.">
            <form action="{{ route('cash-sessions.close', $activeSession) }}" method="POST">
                @csrf 
                <input type="hidden" name="_modal" value="cash-close-dialog">
                <div style="display:grid;gap:1rem">
                    <div class="form-group"><label class="form-label">النقد الفعلي عند الإغلاق (₪) *</label><input type="number" name="actual_cash" class="form-input" min="0" step="0.01" value="{{ old('_modal') === 'cash-close-dialog' ? old('actual_cash') : '' }}" required></div>
                    <div class="form-group"><label class="form-label">ملاحظات</label><textarea name="closing_note" class="form-textarea">{{ old('_modal') === 'cash-close-dialog' ? old('closing_note') : '' }}</textarea></div>
                    <div style="display:flex;gap:.75rem"><button class="btn btn-gold" type="submit">إغلاق الجلسة</button><button type="button" class="btn btn-ghost" data-close-dialog>إلغاء</button></div>
                </div>
            </form>
</x-action-dialog>
@endif

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>الموظف</th><th>الموقع</th><th>وقت الفتح</th><th>رصيد الفتح</th><th>المستلم</th><th>وقت الإغلاق</th><th>الفارق</th><th>الحالة</th></tr></thead>
        <tbody>
        @forelse($sessions as $s)
            <tr>
                <td>{{ $s->employee?->full_name }}</td>
                <td>{{ $s->location?->name }}</td>
                <td>{{ $s->opened_at?->format('Y-m-d H:i') }}</td>
                <td>₪{{ number_format($s->opening_balance, 2) }}</td>
                <td>₪{{ number_format($s->cash_received ?? 0, 2) }}</td>
                <td>{{ $s->closed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                <td @if($s->variance && $s->variance != 0) style="color:{{ $s->variance > 0 ? 'var(--success)' : 'var(--error)' }};font-weight:700" @endif>{{ $s->variance !== null ? (($s->variance >= 0 ? '+' : '') . number_format($s->variance, 2)) : '—' }}</td>
                <td><span class="badge {{ $s->status === 'open' ? 'badge-active' : 'badge-inactive' }}">{{ $s->status === 'open' ? 'مفتوحة' : 'مغلقة' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty-state-sm">لا توجد جلسات.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>

 <div>
    {{ $sessions->withQueryString()->links() }}
    </div>
@endsection
