@extends('layouts.app')
@section('title', 'تحويل مخزون جديد')
@section('content')
<div class="page-header">
    <h1 class="page-heading">تحويل مخزون جديد</h1>
    <p class="page-subheading"><a href="{{ route('stock-transfers.index') }}">تحويلات المخزون</a> &laquo; جديد</p>
</div>

<div style="max-width:640px">
    <div class="card">
        <div class="card-body" style="display:flex;flex-direction:column;gap:1.25rem;align-items:flex-start">
            <div style="display:flex;align-items:flex-start;gap:.75rem">
                <span style="font-size:2rem;line-height:1">📦</span>
                <div>
                    <p style="margin:0 0 .5rem;font-size:1.05rem;font-weight:600">كيف تعمل تحويلات المخزون؟</p>
                    <p style="margin:0;color:var(--muted)">
                        تُنشأ تحويلات المخزون تلقائياً عندما يوافق المخزن على طلب مخزون مقدَّم من أحد الفروع.
                        لا يمكن إنشاء تحويل يدوي مباشرة في الوقت الحالي.
                    </p>
                </div>
            </div>

            <div style="background:var(--surface-2,#f8f6f1);border-radius:8px;padding:1rem;width:100%">
                <p style="margin:0 0 .75rem;font-weight:600">لبدء تحويل، اتبع الخطوات التالية:</p>
                <ol style="margin:0;padding-right:1.25rem;color:var(--muted);line-height:1.8">
                    <li>يرفع موظف الفرع <strong>طلب مخزون</strong> يحدد فيه المنتجات والكميات المطلوبة.</li>
                    <li>يراجع المخزن الطلب ويوافق عليه.</li>
                    <li>يُنشأ تحويل مخزون تلقائياً وتبدأ عملية الشحن.</li>
                </ol>
            </div>

            <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                <a href="{{ route('stock-requests.create') }}" class="btn btn-gold">رفع طلب مخزون</a>
                <a href="{{ route('stock-transfers.index') }}" class="btn btn-ghost">عرض التحويلات</a>
            </div>
        </div>
    </div>
</div>
@endsection
