@extends('layouts.app')

@section('title', 'مراجعة إثباتات الدفع بالذكاء الاصطناعي')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">مراجعة إثباتات الدفع</h1>
        <p class="page-subheading">فرز إشعارات الدفع حسب حالة التحليل ومستوى المخاطرة ثم فتح المراجعة التفصيلية.</p>
    </div>

    <a href="{{ route('payments.index') }}" class="btn btn-ghost">الحركات المالية</a>
</div>

@if(!$aiEnabled)
    <div class="alert alert-warning" style="margin-bottom:1rem">
        التحليل الآلي غير مفعّل حاليًا. يمكن الاستمرار بالمراجعة اليدوية، أو تفعيل PAYMENT_PROOF_AI_ENABLED من البيئة.
    </div>
@endif

<div class="card" style="margin-bottom:1rem">
    <div class="card-body">
        <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.75rem;align-items:end">
            <div>
                <label class="form-label">بحث</label>
                <input class="form-input" name="q" value="{{ request('q') }}" placeholder="ID / المرجع / اسم المحوّل / الحساب">
            </div>

            <div>
                <label class="form-label">حالة الدفعة</label>
                <select class="form-select" name="payment_status">
                    <option value="">الكل</option>
                    <option value="pending_verification" @selected(request('payment_status') === 'pending_verification')>بانتظار التحقق</option>
                    <option value="confirmed" @selected(request('payment_status') === 'confirmed')>مؤكدة</option>
                    <option value="rejected" @selected(request('payment_status') === 'rejected')>مرفوضة</option>
                    <option value="corrected" @selected(request('payment_status') === 'corrected')>مصححة</option>
                    <option value="refunded" @selected(request('payment_status') === 'refunded')>مستردة</option>
                </select>
            </div>

            <div>
                <label class="form-label">حالة التحليل</label>
                <select class="form-select" name="analysis_status">
                    <option value="">الكل</option>
                    <option value="completed" @selected(request('analysis_status') === 'completed')>مكتمل</option>
                    <option value="processing" @selected(request('analysis_status') === 'processing')>جاري التحليل</option>
                    <option value="failed" @selected(request('analysis_status') === 'failed')>فشل التحليل</option>
                </select>
            </div>

            <div>
                <label class="form-label">مستوى المخاطرة</label>
                <select class="form-select" name="risk_level">
                    <option value="">الكل</option>
                    <option value="not_analyzed" @selected(request('risk_level') === 'not_analyzed')>لم يُحلل</option>
                    <option value="low" @selected(request('risk_level') === 'low')>منخفضة</option>
                    <option value="medium" @selected(request('risk_level') === 'medium')>متوسطة</option>
                    <option value="high" @selected(request('risk_level') === 'high')>مرتفعة</option>
                    <option value="unknown" @selected(request('risk_level') === 'unknown')>غير محددة</option>
                </select>
            </div>

            @if($canViewAll && $locations->isNotEmpty())
                <div>
                    <label class="form-label">الفرع</label>
                    <select class="form-select" name="location_id">
                        <option value="">كل الفروع</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>
                                {{ $location->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <button class="btn btn-primary" type="submit">تصفية</button>
                <a href="{{ route('payments.proof-review.index') }}" class="btn btn-ghost">مسح</a>
            </div>
        </form>
    </div>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>الفرع</th>
                <th>طريقة الدفع</th>
                <th>المبلغ</th>
                <th>حالة الدفعة</th>
                <th>اسم المحوّل</th>
                <th>المرجع</th>
                <th>AI</th>
                <th>الثقة</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
        @forelse($payments as $payment)
            @php
                $analysis = $payment->latestProofAnalysis;
                $status = $payment->statusValue();
                $risk = $analysis?->risk_level ?? 'not_analyzed';

                $riskMeta = match($risk) {
                    'low' => ['label' => 'منخفضة', 'class' => 'badge-active'],
                    'medium' => ['label' => 'متوسطة', 'class' => 'badge-warning'],
                    'high' => ['label' => 'مرتفعة', 'class' => 'badge-inactive'],
                    'unknown' => ['label' => 'غير محددة', 'class' => 'badge-secondary'],
                    default => ['label' => 'لم يُحلل', 'class' => 'badge-secondary'],
                };

                $statusMeta = match($status) {
                    'pending_verification' => ['label' => 'بانتظار التحقق', 'class' => 'badge-warning'],
                    'confirmed' => ['label' => 'مؤكدة', 'class' => 'badge-active'],
                    'rejected' => ['label' => 'مرفوضة', 'class' => 'badge-inactive'],
                    'corrected' => ['label' => 'مصححة', 'class' => 'badge-info'],
                    'refunded' => ['label' => 'مستردة', 'class' => 'badge-secondary'],
                    default => ['label' => $status ?: '—', 'class' => 'badge-secondary'],
                };
            @endphp
            <tr>
                <td>{{ $payment->id }}</td>
                <td>{{ $payment->location?->name ?? '—' }}</td>
                <td>{{ $payment->paymentMethod?->name_ar ?? $payment->paymentMethod?->name ?? '—' }}</td>
                <td>₪{{ number_format((float) $payment->amount, 2) }}</td>
                <td><span class="badge {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span></td>
                <td>
                    {{ $analysis?->sender_name ?: '—' }}
                    @if($analysis?->sender_account)
                        <br><small class="text-muted">{{ $analysis->sender_account }}</small>
                    @endif
                </td>
                <td>
                    {{ $analysis?->transaction_reference ?: ($payment->reference_number ?: '—') }}
                </td>
                <td>
                    <span class="badge {{ $riskMeta['class'] }}">{{ $riskMeta['label'] }}</span>
                    @if($analysis?->status === 'failed')
                        <br><small class="text-danger">فشل التحليل</small>
                    @endif
                </td>
                <td>{{ $analysis ? ((int) $analysis->confidence . '%') : '—' }}</td>
                <td>
                    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                        <a href="{{ route('payments.proof-analysis.show', $payment) }}" class="btn btn-outline btn-xs">
                            مراجعة
                        </a>

                        @if($payment->payment_proof)
                            <a
                                href="{{ asset('storage/' . ltrim($payment->payment_proof, '/')) }}"
                                target="_blank"
                                rel="noopener"
                                class="btn btn-ghost btn-xs"
                            >
                                الإثبات
                            </a>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="text-center text-muted" style="padding:2rem">
                    لا توجد إثباتات دفع مطابقة للفلاتر الحالية.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($payments->hasPages())
    <div style="margin-top:1rem">{{ $payments->links() }}</div>
@endif
@endsection
