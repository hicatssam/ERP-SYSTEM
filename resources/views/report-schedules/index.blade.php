@extends('layouts.app')
@section('title', 'جدول التقارير المجدولة')
@section('page-title', 'جدول التقارير المجدولة')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-heading">التقارير المجدولة</h1>
        <p class="page-subheading">إدارة جداول إرسال التقارير التلقائية عبر البريد الإلكتروني</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('report-schedules.create') }}" class="btn btn-gold">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            جدول جديد
        </a>
        <a href="{{ route('reports.index') }}" class="btn btn-ghost btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="15 18 9 12 15 6"/></svg>
            التقارير
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger" style="margin-bottom:1rem">{{ session('error') }}</div>
@endif

@if($schedules->isEmpty())
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <h3>لا توجد جداول مجدولة بعد</h3>
            <p>أنشئ جدولاً لإرسال التقارير تلقائياً في وقت محدد</p>
            <a href="{{ route('report-schedules.create') }}" class="btn btn-gold">إنشاء جدول جديد</a>
        </div>
    </div>
</div>
@else
<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>نوع التقرير</th>
                    <th>التكرار</th>
                    <th>وقت الإرسال</th>
                    <th>النطاق الزمني</th>
                    <th>الفرع</th>
                    <th>المستلمون</th>
                    <th>آخر إرسال</th>
                    <th>الحالة</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($schedules as $schedule)
                <tr>
                    <td style="font-weight:600">{{ $schedule->name }}</td>
                    <td>
                        @php
                        $typeNames = [
                            'orders'=>'الطلبات','cake-orders'=>'طلبات الكيك','inventory'=>'المخزون',
                            'stock-movements'=>'حركات المخزون','low-stock'=>'المخزون المنخفض',
                            'stock-transfers'=>'التحويلات','payments'=>'الدفعات','invoices'=>'الفواتير',
                            'cash-sessions'=>'جلسات الكاشير','daily-sales'=>'المبيعات اليومية',
                            'monthly-sales'=>'المبيعات الشهرية','branch-sales'=>'أداء الفروع',
                            'product-sales'=>'مبيعات المنتجات','collections'=>'التحصيلات',
                            'outstanding'=>'الأرصدة المعلقة','activity-logs'=>'سجل النشاطات',
                        ];
                        @endphp
                        <span class="badge" style="background:var(--gold-light,#fdf8ea);color:var(--gold,#b8860b)">
                            {{ $typeNames[$schedule->report_type] ?? $schedule->report_type }}
                        </span>
                    </td>
                    <td>
                        {{ $schedule->frequencyLabel() }}
                        @if($schedule->frequency === 'weekly')
                        <span style="color:var(--text-muted);font-size:.8rem">— {{ $schedule->dayOfWeekLabel() }}</span>
                        @endif
                    </td>
                    <td>{{ str_pad($schedule->hour, 2, '0', STR_PAD_LEFT) }}:00</td>
                    <td>{{ $schedule->dateRangeLabel() }}</td>
                    <td>{{ $schedule->location?->name ?? 'الكل' }}</td>
                    <td style="font-size:.78rem;color:var(--text-muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $schedule->recipients }}">
                        {{ $schedule->recipients }}
                    </td>
                    <td style="font-size:.8rem;color:var(--text-muted)">
                        {{ $schedule->last_run_at ? $schedule->last_run_at->format('d/m/Y H:i') : '—' }}
                    </td>
                    <td>
                        <form method="POST" action="{{ route('report-schedules.toggle', $schedule) }}">
                            @csrf
                            <button type="submit" class="badge" style="border:none;cursor:pointer;background:{{ $schedule->is_active ? 'var(--success-light,#eaffea)' : 'var(--border)' }};color:{{ $schedule->is_active ? 'var(--success,#2e7d32)' : 'var(--text-muted)' }}">
                                {{ $schedule->is_active ? 'مفعّل' : 'موقوف' }}
                            </button>
                        </form>
                    </td>
                    <td>
                        <div style="display:flex;gap:.4rem;justify-content:flex-end;flex-wrap:wrap">
                            <form method="POST" action="{{ route('report-schedules.send-now', $schedule) }}"
                                  onsubmit="return confirm('هل تريد إرسال هذا التقرير الآن إلى المستلمين؟')">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--gold,#b8860b)">إرسال الآن</button>
                            </form>
                            <a href="{{ route('report-schedules.edit', $schedule) }}" class="btn btn-ghost btn-sm">تعديل</a>
                            <form method="POST" action="{{ route('report-schedules.destroy', $schedule) }}"
                                  onsubmit="return confirm('هل تريد حذف هذا الجدول؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger,#c62828)">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
