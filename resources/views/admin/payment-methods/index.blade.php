@extends('layouts.app')
@section('title', 'طرق الدفع')
@section('page-title', 'طرق الدفع')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-heading">طرق الدفع</h1>
        <p class="page-subheading">إدارة طرق الدفع المتاحة في النظام</p>
    </div>
    <div class="page-header-actions">
        @can('payment_methods.manage')
        <a href="{{ route('payment-methods.create') }}" class="btn btn-gold btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            إضافة طريقة دفع
        </a>
        @endcan
    </div>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-error">{{ session('error') }}</div>
@endif

<div class="card">
    <div class="card-header">
        <span class="card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            قائمة طرق الدفع
        </span>
        <span class="badge badge-grey">{{ $methods->count() }} طريقة</span>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>الشعار</th>
                    <th>الاسم</th>
                    <th>الاسم بالعربية</th>
                    <th>الكود</th>
                    <th>النوع</th>
                    <th>الترتيب</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($methods as $method)
                <tr style="{{ $method->trashed() ? 'opacity:.5' : '' }}">
                    <td>
                        @if($method->logo_path)
                            <img src="{{ Storage::url($method->logo_path) }}" alt="{{ $method->name }}" style="width:38px;height:38px;object-fit:contain;border-radius:6px;border:1px solid var(--border)">
                        @elseif($method->logo)
                            <img src="{{ $method->logo }}" alt="{{ $method->name }}" style="width:38px;height:38px;object-fit:contain;border-radius:6px;border:1px solid var(--border)">
                        @else
                            <div style="width:38px;height:38px;background:var(--gold-ultra);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:var(--gold)">
                                {{ mb_substr($method->name_ar, 0, 2) }}
                            </div>
                        @endif
                    </td>
                    <td><strong>{{ $method->name }}</strong></td>
                    <td>{{ $method->name_ar }}</td>
                    <td><code style="font-size:.8rem;background:var(--surface);padding:.2rem .5rem;border-radius:4px">{{ $method->code }}</code></td>
                    <td>
                        @php
                            $typeLabels = ['cash'=>'نقداً','electronic_wallet'=>'محفظة إلكترونية','bank_transfer'=>'تحويل بنكي','card_pos'=>'بطاقة POS','other'=>'أخرى'];
                        @endphp
                        <span class="badge badge-blue">{{ $typeLabels[$method->type] ?? $method->type }}</span>
                    </td>
                    <td>{{ $method->sort_order }}</td>
                    <td>
                        @if($method->trashed())
                            <span class="badge badge-grey">محذوف</span>
                        @elseif($method->is_active)
                            <span class="badge badge-active">مفعّل</span>
                        @else
                            <span class="badge badge-grey">معطّل</span>
                        @endif
                    </td>
                    <td>
                        @if(!$method->trashed())
                        <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                            @can('payment_methods.manage')
                            <a href="{{ route('payment-methods.edit', $method) }}" class="btn btn-ghost btn-sm" title="تعديل">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <form action="{{ route('payment-methods.toggle', $method) }}" method="POST" style="display:inline">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm" title="{{ $method->is_active ? 'تعطيل' : 'تفعيل' }}">
                                    @if($method->is_active)
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                                    @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><polyline points="10 15 12 17 16 13"/></svg>
                                    @endif
                                </button>
                            </form>
                            <form action="{{ route('payment-methods.destroy', $method) }}" method="POST" style="display:inline" onsubmit="return confirm('حذف طريقة الدفع؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)" title="حذف">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                                </button>
                            </form>
                            @endcan
                        </div>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center" style="padding:2rem;color:var(--text-muted)">لا توجد طرق دفع</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
