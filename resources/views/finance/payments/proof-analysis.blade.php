@extends('layouts.app')

@section('title', 'مراجعة إثبات الدفع')

@section('content')
@php
    $statusValue = $payment->statusValue();
    $proofUrl = $payment->payment_proof
        ? asset('storage/' . ltrim($payment->payment_proof, '/'))
        : null;

    $account = $payment->locationPaymentAccount;
    $expectedAccount = $account?->account_number
        ?? $account?->wallet_number
        ?? $account?->phone_number;

    $risk = $analysis?->risk_level ?? 'unknown';
    $riskMeta = match($risk) {
        'low' => ['label' => 'مخاطرة منخفضة', 'class' => 'badge-active'],
        'medium' => ['label' => 'مخاطرة متوسطة', 'class' => 'badge-warning'],
        'high' => ['label' => 'مخاطرة مرتفعة', 'class' => 'badge-inactive'],
        default => ['label' => 'غير محدد', 'class' => 'badge-secondary'],
    };

    $analysisStatusMeta = match($analysis?->status) {
        'completed' => ['label' => 'اكتمل التحليل', 'class' => 'badge-active'],
        'processing' => ['label' => 'جاري التحليل', 'class' => 'badge-warning'],
        'failed' => ['label' => 'فشل التحليل', 'class' => 'badge-inactive'],
        default => ['label' => 'لم يتم التحليل', 'class' => 'badge-secondary'],
    };
@endphp

<div class="page-header">
    <div>
        <h1 class="page-heading">مراجعة إثبات الدفع</h1>
        <p class="page-subheading">
            الدفعة #{{ $payment->id }} · {{ $payment->location?->name ?? '—' }}
        </p>
    </div>

    <a href="{{ route('payments.index') }}" class="btn btn-ghost">العودة للحركات المالية</a>
</div>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger" style="margin-bottom:1rem">{{ session('error') }}</div>
@endif

<div class="card" style="margin-bottom:1rem">
    <div class="card-body">
        <strong>تنبيه مهم:</strong>
        تحليل الذكاء الاصطناعي أداة مساعدة فقط. لا يثبت وحده أن الحوالة صحيحة أو مزورة، ولا يعتمد أو يرفض الدفعة تلقائيًا.
    </div>
</div>

<div style="display:grid;grid-template-columns:minmax(320px,1fr) minmax(360px,1fr);gap:1rem;align-items:start">

    <div class="card">
        <div class="card-header">
            <span class="card-title">إثبات الدفع</span>
        </div>

        <div class="card-body">
            @if($proofUrl)
                <a href="{{ $proofUrl }}" target="_blank" rel="noopener">
                    <img
                        src="{{ $proofUrl }}"
                        alt="إثبات الدفع"
                        style="display:block;width:100%;max-height:720px;object-fit:contain;border-radius:12px;background:#111"
                    >
                </a>
            @else
                <div class="text-muted">لا يوجد إثبات دفع مرفوع.</div>
            @endif

            <div style="margin-top:1rem;display:grid;gap:.7rem">
                <div><strong>طريقة الدفع:</strong> {{ $payment->paymentMethod?->name_ar ?? $payment->paymentMethod?->name ?? '—' }}</div>
                <div><strong>مبلغ الدفعة:</strong> ₪{{ number_format((float) $payment->amount, 2) }}</div>
                <div><strong>المرجع المدخل:</strong> {{ $payment->reference_number ?: '—' }}</div>
                <div><strong>حساب الفرع المتوقع:</strong> {{ $expectedAccount ?: '—' }}</div>
                <div><strong>اسم صاحب الحساب:</strong> {{ $account?->account_holder_name ?: '—' }}</div>
                <div><strong>مزود الخدمة:</strong> {{ $account?->provider_name ?: '—' }}</div>
            </div>
        </div>
    </div>

    <div style="display:grid;gap:1rem">
        <div class="card">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem">
                <span class="card-title">تحليل الذكاء الاصطناعي</span>
                <span class="badge {{ $analysisStatusMeta['class'] }}">{{ $analysisStatusMeta['label'] }}</span>
            </div>

            <div class="card-body">
                @if(!$aiEnabled)
                    <div class="alert alert-warning" style="margin-bottom:1rem">
                        التحليل الآلي غير مفعّل حاليًا. فعّل PAYMENT_PROOF_AI_ENABLED واضبط مفتاح API أولًا.
                    </div>
                @endif

                <form method="POST" action="{{ route('payments.proof-analysis.store', $payment) }}" style="margin-bottom:1rem">
                    @csrf
                    <button
                        type="submit"
                        class="btn btn-primary"
                        {{ !$proofUrl || !$aiEnabled ? 'disabled' : '' }}
                    >
                        {{ $analysis ? 'إعادة تحليل الإثبات' : 'تحليل الإثبات بالذكاء الاصطناعي' }}
                    </button>
                </form>

                @if($analysis)
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem">
                        <span class="badge {{ $riskMeta['class'] }}">{{ $riskMeta['label'] }}</span>
                        <span class="badge badge-info">الثقة: {{ (int) $analysis->confidence }}%</span>
                    </div>

                    @if($analysis->status === 'failed')
                        <div class="alert alert-danger">
                            {{ $analysis->failure_reason ?: 'فشل التحليل بدون سبب واضح.' }}
                        </div>
                    @else
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem">
                            <div><strong>اسم المحوّل</strong><br>{{ $analysis->sender_name ?: '—' }}</div>
                            <div><strong>حساب المحوّل</strong><br>{{ $analysis->sender_account ?: '—' }}</div>
                            <div><strong>اسم المستفيد</strong><br>{{ $analysis->recipient_name ?: '—' }}</div>
                            <div><strong>حساب المستفيد</strong><br>{{ $analysis->recipient_account ?: '—' }}</div>
                            <div><strong>رقم العملية</strong><br>{{ $analysis->transaction_reference ?: '—' }}</div>
                            <div><strong>المبلغ المستخرج</strong><br>{{ $analysis->extracted_amount !== null ? number_format((float) $analysis->extracted_amount, 2) : '—' }} {{ $analysis->extracted_currency ?: '' }}</div>
                            <div><strong>وقت العملية</strong><br>{{ $analysis->transaction_at?->format('Y-m-d H:i:s') ?? '—' }}</div>
                            <div><strong>بصمة الملف</strong><br><small style="word-break:break-all">{{ $analysis->proof_sha256 ?: '—' }}</small></div>
                        </div>

                        <hr style="margin:1rem 0">

                        <h3 style="font-size:1rem;margin-bottom:.6rem">مؤشرات الاشتباه والمطابقة</h3>
                        @if(!empty($analysis->risk_signals))
                            <ul style="margin:0;padding-right:1.2rem;display:grid;gap:.4rem">
                                @foreach($analysis->risk_signals as $signal)
                                    <li>{{ $signal }}</li>
                                @endforeach
                            </ul>
                        @else
                            <div class="text-muted">لم يسجل التحليل مؤشرات اشتباه واضحة.</div>
                        @endif

                        @if($analysis->raw_text)
                            <details style="margin-top:1rem">
                                <summary style="cursor:pointer;font-weight:700">النص المستخرج من الإشعار</summary>
                                <pre style="white-space:pre-wrap;margin-top:.75rem;background:#111;padding:1rem;border-radius:10px">{{ $analysis->raw_text }}</pre>
                            </details>
                        @endif
                    @endif
                @else
                    <div class="text-muted">لم يتم تحليل هذا الإثبات بعد.</div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">قرار الموظف</span>
            </div>
            <div class="card-body">
                <div style="margin-bottom:1rem">
                    حالة الدفعة الحالية:
                    <strong>{{ $statusValue }}</strong>
                </div>

                @if($statusValue === 'pending_verification')
                    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                        <form method="POST" action="{{ route('payments.verify', $payment) }}">
                            @csrf
                            <input type="hidden" name="action" value="verify">
                            <button type="submit" class="btn btn-success" onclick="return confirm('تأكيد هذه الدفعة بعد المراجعة؟')">
                                اعتماد الدفعة
                            </button>
                        </form>

                        <form method="POST" action="{{ route('payments.verify', $payment) }}" style="display:flex;gap:.5rem;flex-wrap:wrap">
                            @csrf
                            <input type="hidden" name="action" value="reject">
                            <input
                                type="text"
                                name="rejection_reason"
                                class="form-input"
                                required
                                maxlength="500"
                                placeholder="سبب الرفض"
                            >
                            <button type="submit" class="btn btn-danger" onclick="return confirm('رفض هذه الدفعة؟')">
                                رفض الدفعة
                            </button>
                        </form>
                    </div>
                @else
                    <div class="text-muted">هذه الدفعة ليست بانتظار التحقق حاليًا.</div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
@media (max-width: 960px) {
    div[style*="grid-template-columns:minmax(320px,1fr) minmax(360px,1fr)"] {
        grid-template-columns: 1fr !important;
    }
}
</style>
@endsection
