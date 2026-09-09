@extends('layouts.app')

@section('title', 'طلب مخزون ' . $stockRequest->request_number)
@section('page-title', 'طلب مخزون')

@section('content')
@php
    $statusValue = $stockRequest->status instanceof \BackedEnum
        ? $stockRequest->status->value
        : (string) $stockRequest->status;

    $labels = [
        'draft' => 'مسودة',
        'pending_factory_review' => 'بانتظار مراجعة المصنع',
        'accepted' => 'مقبول بالكامل',
        'partially_accepted' => 'مقبول جزئياً',
        'rejected' => 'مرفوض',
        'dispatched' => 'تم الإرسال',
        'in_transit' => 'قيد النقل',
        'received' => 'تم الاستلام',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
        'canceled' => 'ملغي',
    ];

    $classes = [
        'draft' => 'badge-pending',
        'pending_factory_review' => 'badge-pending',
        'accepted' => 'badge-active',
        'partially_accepted' => 'badge-pending',
        'rejected' => 'badge-inactive',
        'dispatched' => 'badge-active',
        'in_transit' => 'badge-pending',
        'received' => 'badge-active',
        'completed' => 'badge-active',
        'cancelled' => 'badge-inactive',
        'canceled' => 'badge-inactive',
    ];

    $statusLabel = $labels[$statusValue] ?? 'غير محدد';
    $statusClass = $classes[$statusValue] ?? 'badge-pending';

    $canReview = $statusValue === 'pending_factory_review'
        && auth()->check()
        && auth()->user()->can('review', $stockRequest);
@endphp

<div class="page-actions">
    <div class="page-actions-title">
        طلب: {{ $stockRequest->request_number }}
    </div>

    <div class="action-btns">
        <a href="{{ route('stock-requests.index') }}" class="btn btn-ghost btn-sm">رجوع</a>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        <strong>يرجى تصحيح البيانات التالية:</strong>
        <ul style="margin:.55rem 0 0;padding-right:1.25rem">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="dashboard-row">
    <div class="card">
        <div class="card-header"><span class="card-title">تفاصيل الطلب</span></div>
        <div class="card-body">
            <table class="data-table">
                <tbody>
                    <tr>
                        <td style="color:var(--text-muted)">رقم الطلب</td>
                        <td><strong>{{ $stockRequest->request_number }}</strong></td>
                    </tr>
                    <tr>
                        <td style="color:var(--text-muted)">الفرع الطالب</td>
                        <td>{{ $stockRequest->branch?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="color:var(--text-muted)">المصنع</td>
                        <td>{{ $stockRequest->factory?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="color:var(--text-muted)">الحالة</td>
                        <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                    </tr>
                    <tr>
                        <td style="color:var(--text-muted)">أنشئ بواسطة</td>
                        <td>{{ $stockRequest->creator?->display_name ?? 'غير مسجل' }}</td>
                    </tr>
                    <tr>
                        <td style="color:var(--text-muted)">التاريخ</td>
                        <td dir="ltr">{{ $stockRequest->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    </tr>

                    @if($stockRequest->reviewed_at)
                        <tr>
                            <td style="color:var(--text-muted)">راجع بواسطة</td>
                            <td>{{ $stockRequest->reviewer?->display_name ?? 'غير مسجل' }}</td>
                        </tr>
                        <tr>
                            <td style="color:var(--text-muted)">وقت المراجعة</td>
                            <td dir="ltr">{{ $stockRequest->reviewed_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            @if($stockRequest->notes)
                <div style="margin-top:1rem">
                    <div style="color:var(--text-muted);margin-bottom:.4rem">ملاحظات الطلب</div>
                    <div style="padding:.8rem 1rem;border:1px solid var(--border);border-radius:8px">
                        {{ $stockRequest->notes }}
                    </div>
                </div>
            @endif

            @if($stockRequest->rejection_reason)
                <div style="margin-top:1rem">
                    <div style="color:var(--error);margin-bottom:.4rem;font-weight:700">سبب الرفض</div>
                    <div style="padding:.8rem 1rem;border:1px solid rgba(220,38,38,.25);border-radius:8px;background:rgba(220,38,38,.06)">
                        {{ $stockRequest->rejection_reason }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">العناصر المطلوبة</span></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>المنتج</th>
                        <th>الكمية المطلوبة</th>
                        <th>الكمية المعتمدة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stockRequest->items as $item)
                        <tr>
                            <td>{{ $item->product?->name ?? 'منتج غير متاح' }}</td>
                            <td><strong>{{ number_format((float) $item->requested_quantity, 2) }}</strong></td>
                            <td>
                                @if($item->approved_quantity !== null)
                                    <strong>{{ number_format((float) $item->approved_quantity, 2) }}</strong>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3">لا توجد عناصر في هذا الطلب.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($canReview)
    <div class="card" style="margin-top:1.25rem">
        <div class="card-header">
            <div>
                <span class="card-title">مراجعة طلب المخزون</span>
                <div style="margin-top:.3rem;color:var(--text-muted);font-size:.76rem">
                    القبول هنا لا يخصم المخزون. يتم الخصم فقط عند إرسال التحويل.
                </div>
            </div>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('stock-requests.review', $stockRequest) }}">
                @csrf

                <div style="margin-bottom:1rem">
                    <div style="font-weight:700;margin-bottom:.65rem">الكميات عند القبول الجزئي</div>

                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>المنتج</th>
                                    <th>المطلوب</th>
                                    <th>المعتمد</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stockRequest->items as $item)
                                    <tr>
                                        <td>{{ $item->product?->name ?? '—' }}</td>
                                        <td>{{ number_format((float) $item->requested_quantity, 2) }}</td>
                                        <td>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="{{ (float) $item->requested_quantity }}"
                                                name="items[{{ $item->id }}][approved_quantity]"
                                                value="{{ old("items.{$item->id}.approved_quantity", $item->requested_quantity) }}"
                                                class="form-input"
                                                style="max-width:180px;margin:auto"
                                            >
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="margin-bottom:1rem">
                    <label class="form-label" for="rejection_reason">سبب الرفض</label>
                    <textarea
                        id="rejection_reason"
                        name="rejection_reason"
                        rows="3"
                        class="form-input"
                        placeholder="مطلوب عند رفض الطلب فقط..."
                    >{{ old('rejection_reason') }}</textarea>
                </div>

                <div style="display:flex;gap:.65rem;flex-wrap:wrap">
                    <button
                        type="submit"
                        name="action"
                        value="accept"
                        class="btn btn-gold"
                        onclick="return confirm('اعتماد الطلب بالكامل وإنشاء تحويل مخزون؟')"
                    >
                        قبول كامل
                    </button>

                    <button
                        type="submit"
                        name="action"
                        value="partially_accept"
                        class="btn btn-outline"
                        onclick="return confirm('اعتماد الكميات المدخلة وإنشاء تحويل مخزون بالكميات المعتمدة؟')"
                    >
                        قبول جزئي
                    </button>

                    <button
                        type="submit"
                        name="action"
                        value="reject"
                        class="btn btn-ghost"
                        style="color:var(--error)"
                        onclick="
                            const reason = document.getElementById('rejection_reason').value.trim();
                            if (!reason) {
                                alert('سبب الرفض مطلوب.');
                                return false;
                            }
                            return confirm('هل تريد رفض طلب المخزون؟');
                        "
                    >
                        رفض الطلب
                    </button>
                </div>
            </form>
        </div>
    </div>
@elseif($statusValue === 'pending_factory_review')
    <div class="card" style="margin-top:1.25rem">
        <div class="card-body">
            الطلب بانتظار مراجعة المصنع، ولا يملك المستخدم الحالي صلاحية اتخاذ القرار.
        </div>
    </div>
@endif

@endsection
