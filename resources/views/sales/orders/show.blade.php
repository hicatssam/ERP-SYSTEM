@extends('layouts.app')

@section('title', 'طلب: ' . $order->order_number)

@section('content')
@php
    $enumValue = static fn ($value) => $value instanceof \BackedEnum ? (string) $value->value : (string) ($value ?? '');

    $orderStatus = $enumValue($order->status);
    $paymentArrangement = $enumValue($order->payment_arrangement);
    $paymentStatus = $enumValue($order->payment_status);

    $statusLabel = match ($orderStatus) {
        'draft' => 'مسودة',
        'confirmed' => 'مؤكد',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
        default => $orderStatus ?: 'غير محدد',
    };

    $statusClass = match ($orderStatus) {
        'confirmed', 'completed' => 'badge-active',
        'cancelled' => 'badge-inactive',
        default => 'badge-pending',
    };

    $paymentArrangementLabel = match ($paymentArrangement) {
        'pay_now' => 'دفع فوري',
        'deposit' => 'عربون',
        'partial_payment' => 'دفع جزئي',
        'pay_on_pickup' => 'الدفع عند الاستلام',
        'pending_verification' => 'بانتظار التحقق',
        'on_account' => 'على الحساب',
        default => 'غير محدد',
    };

    $paymentStatusMeta = match ($paymentStatus) {
        'paid' => ['label' => 'مدفوع بالكامل', 'class' => 'badge-active'],
        'partially_paid' => ['label' => 'مدفوع جزئياً', 'class' => 'badge-pending'],
        'pending_payment_verification' => ['label' => 'بانتظار التحقق من الدفع', 'class' => 'badge-pending'],
        'partially_refunded' => ['label' => 'مسترد جزئياً', 'class' => 'badge-pending'],
        'refunded' => ['label' => 'مسترد بالكامل', 'class' => 'badge-inactive'],
        default => ['label' => 'غير مدفوع', 'class' => 'badge-secondary'],
    };

    /*
     * Confirmation failures can arrive through the validation error bag or
     * through the explicit flash payload set by OrderController. Keep a
     * normalized array so the admin always sees the reason on this page.
     */
    $stockErrors = collect($errors->get('stock'))
        ->merge((array) session('order_confirm_errors', []))
        ->filter(fn ($message) => filled($message))
        ->map(fn ($message) => (string) $message)
        ->unique()
        ->values()
        ->all();
    $confirmFailed = (bool) session('order_confirm_failed', false) || !empty($stockErrors);
    $confirmErrorTitle = session('order_confirm_error_title', 'تعذر تأكيد الطلب');
    $proofPayments = $order->payments
        ->filter(fn ($payment) => filled($payment->payment_proof))
        ->sortByDesc('id')
        ->values();

    $orderRefundedAmount = (float) \App\Models\Refund::query()
        ->where('order_type', 'order')
        ->where('order_id', $order->id)
        ->sum('amount');

    $invoicePaidAmount = (float) ($order->invoice?->paid_amount ?? $order->confirmedPaidAmount());
    $invoiceRemainingAmount = (float) ($order->invoice?->remaining_amount ?? max(0, (float) $order->total_amount - $invoicePaidAmount));
@endphp

<div class="page-actions">
    <div>
        <div class="page-actions-title">{{ $order->order_number }}</div>
        <div class="order-page-subtitle">
            {{ $order->location?->name ?? 'غير محدد' }}
            <span>•</span>
            {{ $order->customer?->name ?? 'عميل نقدي' }}
        </div>
    </div>

    <div class="action-btns">
        @if($orderStatus === 'draft')
            @can('confirm', $order)
                <button type="button" class="btn btn-gold btn-sm" onclick="openOrderActionModal('confirm')">تأكيد الطلب</button>
            @endcan
        @endif

        @if($orderStatus === 'confirmed')
            @can('complete', $order)
                <button type="button" class="btn btn-gold btn-sm" onclick="openOrderActionModal('complete')">إكمال الطلب</button>
            @endcan
        @endif

        @if($orderStatus === 'draft')
            @can('update', $order)
                <a href="{{ route('orders.edit', $order) }}" class="btn btn-outline btn-sm">تعديل الطلب</a>
            @endcan
        @endif

        @if(in_array($orderStatus, ['draft', 'confirmed'], true))
            @can('cancel', $order)
                <button type="button" class="btn btn-ghost btn-sm" style="color:var(--error)" onclick="openOrderActionModal('cancel')">إلغاء الطلب</button>
            @endcan
        @endif

        <a href="{{ route('orders.index') }}" class="btn btn-ghost btn-sm">رجوع</a>
    </div>
</div>

<x-workflow-toolbar type="order" :record="$order" />

@if($paymentStatus === 'pending_payment_verification' || $proofPayments->isNotEmpty())
    <div class="payment-proof-alert {{ $paymentStatus === 'pending_payment_verification' ? 'is-warning' : 'is-info' }}">
        <div class="payment-proof-alert__icon">💳</div>
        <div class="payment-proof-alert__body">
            <strong>
                {{ $paymentStatus === 'pending_payment_verification' ? 'يوجد إثبات دفع يحتاج مراجعة قبل تأكيد الطلب' : 'يوجد إثبات دفع مرتبط بهذا الطلب' }}
            </strong>
            <p>
                راجع صورة الإشعار وبيانات التحويل ونتيجة التحليل المساعد. لا يتم اعتماد أو رفض الدفعة تلقائياً اعتماداً على AI فقط.
            </p>
        </div>
        @if($proofPayments->isNotEmpty())
            <a class="btn btn-outline btn-sm" href="{{ route('payments.proof-analysis.show', $proofPayments->first()) }}">مراجعة الإثبات</a>
        @endif
    </div>
@endif

<div class="dashboard-row">
    <div class="card">
        <div class="card-header"><span class="card-title">تفاصيل الطلب</span></div>
        <div class="card-body">
            <table class="data-table order-details-table">
                <tbody>
                    <tr><td>رقم الطلب</td><td>{{ $order->order_number }}</td></tr>
                    <tr><td>الفرع</td><td>{{ $order->location?->name ?? 'غير محدد' }}</td></tr>
                    <tr><td>العميل</td><td>{{ $order->customer?->name ?? 'عميل نقدي' }}</td></tr>

                    @if(($order->order_source ?? null) === 'customer_menu')
                        <tr><td>مصدر الطلب</td><td><span class="badge badge-pending">منيو العميل</span></td></tr>
                    @endif

                    @if($order->restaurant_service_type)
                        <tr>
                            <td>نوع خدمة المطعم</td>
                            <td>{{ $order->restaurant_service_type->icon() }} {{ $order->restaurant_service_type->label() }}</td>
                        </tr>
                        @if($order->restaurantTable)
                            <tr>
                                <td>الطاولة</td>
                                <td>{{ $order->restaurantTable->area?->name ? $order->restaurantTable->area->name . ' — ' : '' }}{{ $order->restaurantTable->displayName() }}</td>
                            </tr>
                        @endif
                        @if($order->guest_count)
                            <tr><td>عدد الضيوف</td><td>{{ $order->guest_count }}</td></tr>
                        @endif
                        @if($order->waiter)
                            <tr><td>الموظف / النادل</td><td>{{ $order->waiter?->employee?->full_name ?? $order->waiter?->display_name }}</td></tr>
                        @endif
                    @endif

                    <tr><td>ترتيب الدفع</td><td>{{ $paymentArrangementLabel }}</td></tr>
                    <tr><td>حالة الدفع</td><td><span class="badge {{ $paymentStatusMeta['class'] }}">{{ $paymentStatusMeta['label'] }}</span></td></tr>
                    <tr><td>الإجمالي</td><td><strong>₪{{ number_format((float) $order->total_amount, 2) }}</strong></td></tr>
                    <tr><td>الحالة</td><td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td></tr>

                    @if($order->invoice)
                        <tr>
                            <td>الفاتورة</td>
                            <td class="invoice-action-row">
                                <a href="{{ route('invoices.show', $order->invoice) }}" class="invoice-number-link">{{ $order->invoice->invoice_number }}</a>
                                <a href="{{ route('invoices.print', $order->invoice) }}" target="_blank" rel="noopener" class="btn btn-outline btn-xs">طباعة</a>
                            </td>
                        </tr>
                    @endif
                    <tr><td>صافي المدفوع</td><td><strong style="color:#16845b">₪{{ number_format($invoicePaidAmount, 2) }}</strong></td></tr>
                    <tr><td>المسترد</td><td><strong style="color:#b42318">₪{{ number_format($orderRefundedAmount, 2) }}</strong></td></tr>
                    <tr><td>المتبقي</td><td><strong>₪{{ number_format($invoiceRemainingAmount, 2) }}</strong></td></tr>
                </tbody>
            </table>

            @if($order->notes)
                <div class="order-note"><small>ملاحظات الطلب</small><div>{{ $order->notes }}</div></div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">العناصر</span></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>المنتج</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th>
                        @if($order->isRestaurantOrder())<th>ملاحظة المطبخ</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($order->items as $item)
                        <tr>
                            <td>{{ $item->product?->name_ar ?? $item->product?->name ?? $item->product_name ?? 'غير محدد' }}</td>
                            <td>{{ number_format((float) $item->quantity, 3) }}</td>
                            <td>₪{{ number_format((float) $item->unit_price, 2) }}</td>
                            <td>₪{{ number_format((float) $item->line_total, 2) }}</td>
                            @if($order->isRestaurantOrder())<td>{{ $item->kitchen_notes ?: '—' }}</td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $order->isRestaurantOrder() ? 5 : 4 }}">لا توجد عناصر في الطلب.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($proofPayments->isNotEmpty())
        <div class="card proof-card">
            <div class="card-header">
                <span class="card-title">إثبات الدفع والحركة المالية</span>
                @can('payments.verify')
                    <a href="{{ route('payments.proof-review.index', ['q' => $proofPayments->first()->id]) }}" class="btn btn-ghost btn-sm">كل تفاصيل المراجعة</a>
                @endcan
            </div>
            <div class="card-body proof-list">
                @foreach($proofPayments as $payment)
                    @php
                        $analysis = $payment->latestProofAnalysis;
                        $pStatus = $payment->statusValue();
                        $risk = $analysis?->risk_level ?? 'not_analyzed';
                        $riskMeta = match ($risk) {
                            'low' => ['منخفضة', 'risk-low'],
                            'medium' => ['متوسطة', 'risk-medium'],
                            'high' => ['مرتفعة', 'risk-high'],
                            default => ['غير محلل', 'risk-unknown'],
                        };
                        $proofUrl = asset('storage/' . ltrim((string) $payment->payment_proof, '/'));
                    @endphp
                    <div class="proof-item">
                        <div class="proof-preview">
                            @if(\Illuminate\Support\Str::endsWith(strtolower((string) $payment->payment_proof), ['.jpg','.jpeg','.png','.webp']))
                                <a href="{{ $proofUrl }}" target="_blank" rel="noopener"><img src="{{ $proofUrl }}" alt="إثبات الدفع"></a>
                            @else
                                <a class="proof-file" href="{{ $proofUrl }}" target="_blank" rel="noopener">فتح ملف الإثبات</a>
                            @endif
                        </div>

                        <div class="proof-content">
                            <div class="proof-head">
                                <div>
                                    <strong>{{ $payment->paymentMethod?->name_ar ?? $payment->paymentMethod?->name ?? 'طريقة دفع' }}</strong>
                                    <small>الحركة #{{ $payment->id }} • ₪{{ number_format((float) $payment->amount, 2) }}</small>
                                </div>
                                <span class="risk-pill {{ $riskMeta[1] }}">مخاطرة {{ $riskMeta[0] }}</span>
                            </div>

                            <div class="proof-grid">
                                <div><small>حالة الدفعة</small><b>{{ \App\Support\ArabicDisplay::status($pStatus) }}</b></div>
                                <div><small>المرجع المدخل</small><b>{{ $payment->reference_number ?: '—' }}</b></div>
                                <div><small>اسم المحوّل AI</small><b>{{ $analysis?->sender_name ?: '—' }}</b></div>
                                <div><small>حساب المحوّل AI</small><b>{{ $analysis?->sender_account ?: '—' }}</b></div>
                                <div><small>المرجع المستخرج</small><b>{{ $analysis?->transaction_reference ?: '—' }}</b></div>
                                <div><small>المبلغ المستخرج</small><b>{{ $analysis?->extracted_amount !== null ? '₪'.number_format((float) $analysis->extracted_amount, 2) : '—' }}</b></div>
                                <div><small>الثقة</small><b>{{ $analysis ? ((int) $analysis->confidence . '%') : '—' }}</b></div>
                                <div><small>حالة التحليل</small><b>{{ $analysis?->status ?? 'لم يتم التحليل' }}</b></div>
                            </div>

                            @if($analysis?->risk_signals)
                                <div class="risk-signals">
                                    <strong>مؤشرات تحتاج مراجعة</strong>
                                    <ul>@foreach($analysis->risk_signals as $signal)<li>{{ $signal }}</li>@endforeach</ul>
                                </div>
                            @endif

                            <div class="proof-actions">
                                <a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">عرض الإثبات</a>
                                @can('verify', $payment)
                                    <a href="{{ route('payments.proof-analysis.show', $payment) }}" class="btn btn-gold btn-sm">مراجعة الحركة</a>
                                    @if(config('services.payment_proof_ai.enabled'))
                                        <form method="POST" action="{{ route('payments.proof-analysis.store', $payment) }}" style="display:inline">
                                            @csrf
                                            <button class="btn btn-ghost btn-sm" type="submit">{{ $analysis ? 'إعادة تحليل AI' : 'تحليل AI' }}</button>
                                        </form>
                                    @endif
                                @endcan
                                <a href="{{ route('payments.index', ['order_type' => 'order']) }}" class="btn btn-ghost btn-sm">الحركات المالية</a>
                            </div>

                            <div class="ai-disclaimer">التحليل الآلي مساعد فقط. لا يثبت أن الإشعار مزور أو صحيح بشكل قطعي، ولا يعتمد أو يرفض الدفعة تلقائياً.</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($order->isRestaurantOrder() && $order->kitchenTickets->isNotEmpty())
        <div class="card kitchen-card">
            <div class="card-header"><span class="card-title">المطبخ / KDS</span></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>التذكرة</th><th>المحطة</th><th>الحالة</th><th>وقت الوصول</th><th></th></tr></thead>
                    <tbody>
                    @foreach($order->kitchenTickets->sortBy('id') as $ticket)
                        <tr>
                            <td><strong>{{ $ticket->ticket_number }}</strong></td>
                            <td>{{ $ticket->station?->name ?? '—' }}</td>
                            <td><span class="badge {{ $ticket->status?->badgeClass() ?? 'badge-grey' }}">{{ $ticket->status?->label() ?? \App\Support\ArabicDisplay::status($ticket->status) }}</span></td>
                            <td>{{ $ticket->queued_at?->format('H:i') ?? '—' }}</td>
                            <td>@if(\Illuminate\Support\Facades\Route::has('kitchen.tickets.show'))<a href="{{ route('kitchen.tickets.show', $ticket) }}" class="btn btn-ghost btn-sm">عرض</a>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

@if($orderStatus === 'draft')
    @can('confirm', $order)
        <div id="confirmOrderModal" class="order-modal" aria-hidden="true" onclick="closeOrderActionModalFromBackdrop(event,'confirmOrderModal')">
            <div class="order-modal-dialog">
                <div class="order-modal-header"><div><small>اعتماد الطلب</small><h3>تأكيد الطلب</h3><p>{{ $order->order_number }}</p></div><button type="button" class="order-modal-close" onclick="closeOrderActionModal('confirmOrderModal')">×</button></div>
                <form action="{{ route('orders.confirm', $order) }}" method="POST" id="confirmOrderForm">@csrf
                    <div class="order-modal-body"><div class="order-action-message"><b>✓</b><div><strong>هل تريد اعتماد هذا الطلب؟</strong><p>سيتم خصم المخزون وإنشاء الفاتورة حسب منطق النظام.</p></div></div></div>
                    <div class="order-modal-footer"><button type="button" class="btn btn-ghost" onclick="closeOrderActionModal('confirmOrderModal')">إلغاء</button><button type="submit" class="btn btn-gold" id="confirmOrderSubmitBtn">تأكيد الطلب</button></div>
                </form>
            </div>
        </div>
    @endcan
@endif

@if($orderStatus === 'confirmed')
    @can('complete', $order)
        <div id="completeOrderModal" class="order-modal" aria-hidden="true" onclick="closeOrderActionModalFromBackdrop(event,'completeOrderModal')">
            <div class="order-modal-dialog">
                <div class="order-modal-header"><div><small>تحديث الحالة</small><h3>إكمال الطلب</h3><p>{{ $order->order_number }}</p></div><button type="button" class="order-modal-close" onclick="closeOrderActionModal('completeOrderModal')">×</button></div>
                <form action="{{ route('orders.complete', $order) }}" method="POST">@csrf
                    <div class="order-modal-body"><div class="order-action-message"><b>✓</b><div><strong>هل تم تسليم الطلب؟</strong><p>سيتم تحويل حالة الطلب إلى مكتمل وسيظهر التحديث للعميل.</p></div></div></div>
                    <div class="order-modal-footer"><button type="button" class="btn btn-ghost" onclick="closeOrderActionModal('completeOrderModal')">إلغاء</button><button type="submit" class="btn btn-gold">إكمال الطلب</button></div>
                </form>
            </div>
        </div>
    @endcan
@endif

@if(in_array($orderStatus, ['draft','confirmed'], true))
    @can('cancel', $order)
        <div id="cancelOrderModal" class="order-modal {{ $errors->has('cancellation_reason') ? 'is-open' : '' }}" aria-hidden="{{ $errors->has('cancellation_reason') ? 'false' : 'true' }}" onclick="closeOrderActionModalFromBackdrop(event,'cancelOrderModal')">
            <div class="order-modal-dialog">
                <div class="order-modal-header"><div><small>إلغاء الطلب</small><h3>تأكيد الإلغاء</h3><p>{{ $order->order_number }}</p></div><button type="button" class="order-modal-close" onclick="closeOrderActionModal('cancelOrderModal')">×</button></div>
                <form action="{{ route('orders.cancel', $order) }}" method="POST">@csrf
                    <div class="order-modal-body"><label class="form-label" for="cancellation_reason">سبب الإلغاء *</label><textarea class="form-textarea" name="cancellation_reason" id="cancellation_reason" rows="4" required>{{ old('cancellation_reason') }}</textarea>@error('cancellation_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="order-modal-footer"><button type="button" class="btn btn-ghost" onclick="closeOrderActionModal('cancelOrderModal')">تراجع</button><button class="btn btn-danger" type="submit">تأكيد الإلغاء</button></div>
                </form>
            </div>
        </div>
    @endcan
@endif

@if($confirmFailed)
    <div class="order-confirm-error-banner" role="alert">
        <div class="order-confirm-error-banner__icon">!</div>
        <div class="order-confirm-error-banner__content">
            <strong>{{ $confirmErrorTitle }}</strong>
            <p>لم يتم تأكيد الطلب ولم يتم إنشاء فاتورة أو اعتماد خصم المخزون.</p>
            @if(!empty($stockErrors))
                <ul>@foreach($stockErrors as $error)<li>{{ $error }}</li>@endforeach</ul>
            @endif
        </div>
    </div>

    <div id="stockErrorModal" class="order-modal is-open" aria-hidden="false" onclick="closeOrderActionModalFromBackdrop(event,'stockErrorModal')">
        <div class="order-modal-dialog">
            <div class="order-modal-header"><div><small>تعذر تأكيد الطلب</small><h3>{{ $confirmErrorTitle }}</h3></div><button type="button" class="order-modal-close" onclick="closeOrderActionModal('stockErrorModal')">×</button></div>
            <div class="order-modal-body"><div class="risk-signals"><strong>لم يتم تأكيد الطلب أو إنشاء فاتورة.</strong><ul>@foreach($stockErrors as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
            <div class="order-modal-footer"><button type="button" class="btn btn-ghost" onclick="closeOrderActionModal('stockErrorModal')">إغلاق</button></div>
        </div>
    </div>
@endif

<style>
.order-confirm-error-banner{display:flex;align-items:flex-start;gap:.9rem;margin:0 0 1.25rem;padding:1rem 1.1rem;border:1px solid #f1b7b2;border-radius:15px;background:#fff3f2;color:#7a271a}.order-confirm-error-banner__icon{flex:0 0 38px;width:38px;height:38px;border-radius:50%;display:grid;place-items:center;background:#b42318;color:#fff;font-weight:900;font-size:1.05rem}.order-confirm-error-banner__content{min-width:0}.order-confirm-error-banner__content strong{display:block;font-size:.9rem}.order-confirm-error-banner__content p{margin:.25rem 0;color:#912018;font-size:.73rem;line-height:1.7}.order-confirm-error-banner__content ul{margin:.55rem 0 0;padding-right:1.15rem;font-size:.75rem;line-height:1.85}.order-page-subtitle{margin-top:.25rem;color:var(--text-muted);font-size:.76rem;display:flex;gap:.4rem;align-items:center}.dashboard-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1.25rem}.proof-card,.kitchen-card{grid-column:1/-1}.card{border-radius:16px}.order-details-table td:first-child{width:40%;color:var(--text-muted)}.invoice-action-row{display:flex;align-items:center;gap:.55rem;flex-wrap:wrap}.invoice-number-link{color:var(--gold);font-weight:800}.order-note{margin-top:1rem;padding:1rem;border-radius:12px;background:var(--off-white)}.order-note small{display:block;color:var(--text-muted);margin-bottom:.35rem}.action-btns{display:flex;gap:.5rem;flex-wrap:wrap}.page-actions-title{color:var(--gold);font-weight:900}
.payment-proof-alert{display:flex;align-items:center;gap:1rem;margin:0 0 1.25rem;padding:1rem 1.1rem;border-radius:15px;border:1px solid #d8e3ef;background:#f8fbff}.payment-proof-alert.is-warning{border-color:#f0d69b;background:#fff9e9}.payment-proof-alert__icon{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:#fff;font-size:1.2rem}.payment-proof-alert__body{flex:1}.payment-proof-alert__body strong{display:block;font-size:.88rem}.payment-proof-alert__body p{margin:.25rem 0 0;color:var(--text-muted);font-size:.72rem;line-height:1.7}
.proof-list{display:grid;gap:1rem}.proof-item{display:grid;grid-template-columns:230px 1fr;gap:1rem;padding:1rem;border:1px solid var(--border);border-radius:16px;background:#fff}.proof-preview{min-height:180px;border-radius:13px;background:var(--off-white);overflow:hidden;display:grid;place-items:center}.proof-preview img{width:100%;height:220px;object-fit:contain;background:#f6f6f6}.proof-file{font-weight:800;color:var(--gold)}.proof-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.proof-head strong{display:block;font-size:.92rem}.proof-head small{display:block;margin-top:.2rem;color:var(--text-muted)}.risk-pill{padding:.36rem .65rem;border-radius:999px;font-size:.67rem;font-weight:900;white-space:nowrap}.risk-low{background:#e9f8ef;color:#177245}.risk-medium{background:#fff4d8;color:#9a6700}.risk-high{background:#fdecec;color:#b42318}.risk-unknown{background:#eef1f4;color:#667085}.proof-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem;margin-top:1rem}.proof-grid>div{padding:.7rem;border-radius:11px;background:var(--off-white);min-width:0}.proof-grid small{display:block;color:var(--text-muted);font-size:.63rem}.proof-grid b{display:block;margin-top:.18rem;font-size:.74rem;overflow-wrap:anywhere}.risk-signals{margin-top:.85rem;padding:.8rem .9rem;border-radius:11px;background:#fff7ed;border:1px solid #fed7aa}.risk-signals strong{font-size:.75rem;color:#9a3412}.risk-signals ul{margin:.45rem 0 0;padding-right:1rem;font-size:.7rem;line-height:1.8;color:#7c2d12}.proof-actions{display:flex;gap:.45rem;flex-wrap:wrap;margin-top:.9rem}.ai-disclaimer{margin-top:.75rem;color:var(--text-muted);font-size:.64rem;line-height:1.7}
.order-modal{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;padding:1rem;background:rgba(15,23,42,.58);backdrop-filter:blur(3px);opacity:0;visibility:hidden;pointer-events:none;transition:.18s}.order-modal.is-open{opacity:1;visibility:visible;pointer-events:auto}.order-modal-dialog{width:min(520px,100%);background:#fff;border:1px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.22)}.order-modal-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1.1rem 1.2rem;border-bottom:1px solid var(--border)}.order-modal-header small{color:var(--gold);font-weight:800}.order-modal-header h3{margin:.2rem 0 0}.order-modal-header p{margin:.25rem 0 0;color:var(--text-muted);font-size:.72rem}.order-modal-close{width:34px;height:34px;border:1px solid var(--border);background:#fff;border-radius:50%;font-size:1.3rem;cursor:pointer}.order-modal-body{padding:1.2rem}.order-modal-footer{display:flex;justify-content:flex-end;gap:.6rem;padding:1rem 1.2rem;border-top:1px solid var(--border);background:var(--off-white)}.order-action-message{display:flex;gap:.8rem;align-items:flex-start;padding:.9rem;border-radius:12px;background:#faf7ed;border:1px solid #eee3bf}.order-action-message>b{width:34px;height:34px;display:grid;place-items:center;border-radius:50%;background:#e8f7ee;color:#16845b}.order-action-message p{margin:.25rem 0 0;color:var(--text-muted);font-size:.72rem;line-height:1.7}
@media(max-width:1000px){.proof-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.proof-item{grid-template-columns:180px 1fr}}@media(max-width:800px){.dashboard-row{grid-template-columns:1fr}.proof-item{grid-template-columns:1fr}.proof-preview img{height:260px}.payment-proof-alert{align-items:flex-start;flex-wrap:wrap}.payment-proof-alert .btn{width:100%}}@media(max-width:560px){.page-actions{align-items:flex-start;flex-direction:column;gap:.8rem}.proof-grid{grid-template-columns:1fr 1fr}.order-modal-footer{flex-direction:column-reverse}.order-modal-footer .btn{width:100%}.data-table{min-width:0}.data-table td,.data-table th{padding:.65rem}.proof-preview img{height:220px}}
</style>

<script>
function openOrderActionModal(action){const ids={confirm:'confirmOrderModal',complete:'completeOrderModal',cancel:'cancelOrderModal'};const modal=document.getElementById(ids[action]);if(!modal)return;modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden'}
function closeOrderActionModal(id){const modal=document.getElementById(id);if(!modal)return;modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');document.body.style.overflow=''}
function closeOrderActionModalFromBackdrop(event,id){if(event.target.id===id)closeOrderActionModal(id)}
document.addEventListener('keydown',event=>{if(event.key==='Escape'){document.querySelectorAll('.order-modal.is-open').forEach(modal=>{modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true')});document.body.style.overflow=''}});
document.addEventListener('DOMContentLoaded',()=>{if(document.querySelector('.order-modal.is-open'))document.body.style.overflow='hidden'});
const confirmOrderForm=document.getElementById('confirmOrderForm');if(confirmOrderForm){confirmOrderForm.addEventListener('submit',()=>{const btn=document.getElementById('confirmOrderSubmitBtn');if(btn){btn.disabled=true;btn.textContent='جاري تأكيد الطلب...'}})}
</script>
@endsection
