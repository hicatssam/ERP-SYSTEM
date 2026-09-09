@extends('layouts.app')

@section('title', 'الفترة المالية')

@section('content')

@php
    $statusValue = $financialPeriod->status instanceof \BackedEnum
        ? (string) $financialPeriod->status->value
        : (string) $financialPeriod->status;

    $isOpen = $statusValue === 'open';

    $rows = collect($summaryRows ?? []);

    $totals = [
        'gross_sales' =>
            (float) $rows->sum('gross_sales'),
        'discounts' =>
            (float) $rows->sum('discounts'),
        'net_sales' =>
            (float) $rows->sum('net_sales'),
        'confirmed_collections' =>
            (float) $rows->sum('confirmed_collections'),
        'refunds' =>
            (float) $rows->sum('refunds'),
        'outstanding_amount' =>
            (float) $rows->sum('outstanding_amount'),
        'invoice_count' =>
            (int) $rows->sum('invoice_count'),
        'order_count' =>
            (int) $rows->sum('order_count'),
    ];
@endphp

<div class="page-header">
    <div>
        <h1 class="page-heading">
            فترة:
            {{ $financialPeriod->year }}/{{ $financialPeriod->month }}
        </h1>

        <p class="page-subheading">
            {{
                $financialPeriod->start_date?->format('Y/m/d')
            }}
            —
            {{
                $financialPeriod->end_date?->format('Y/m/d')
            }}
        </p>
    </div>

    <div class="page-header-actions">
        <a
            href="{{ route('financial-periods.index') }}"
            class="btn btn-ghost"
        >
            رجوع
        </a>

        @if($isOpen)
            @can('financial.periods.close')
                <form
                    action="{{
                        route(
                            'financial-periods.close',
                            $financialPeriod
                        )
                    }}"
                    method="POST"
                    onsubmit="
                        return confirm(
                            'هل تريد إغلاق الفترة المالية وتثبيت الملخص الحالي؟'
                        );
                    "
                >
                    @csrf

                    <button
                        type="submit"
                        class="btn btn-gold"
                    >
                        إغلاق الفترة
                    </button>
                </form>
            @endcan
        @else
            @can('financial.periods.open')
                <form
                    action="{{
                        route(
                            'financial-periods.open',
                            $financialPeriod
                        )
                    }}"
                    method="POST"
                    onsubmit="
                        return confirm(
                            'إعادة فتح الفترة؟ سيتم حذف Snapshot الإغلاق السابق وإعادة حسابه عند الإغلاق القادم.'
                        );
                    "
                >
                    @csrf

                    <button
                        type="submit"
                        class="btn btn-outline"
                    >
                        إعادة فتح
                    </button>
                </form>
            @endcan
        @endif
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">
        {{ session('success') }}
    </div>
@endif

@if(session('info'))
    <div class="alert alert-info" style="margin-bottom:1rem">
        {{ session('info') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger" style="margin-bottom:1rem">
        {{ session('error') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        <strong>تعذر تنفيذ العملية:</strong>
        <ul style="margin:.5rem 1rem 0 0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div
    style="
        display:grid;
        grid-template-columns:minmax(300px,.75fr) minmax(0,1.4fr);
        gap:1rem;
        align-items:start
    "
    class="period-show-grid"
>
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                بيانات الفترة
            </span>
        </div>

        <div class="card-body">
            <div class="period-details">
                <div>
                    <span>السنة / الشهر</span>
                    <strong>
                        {{ $financialPeriod->year }}/{{ $financialPeriod->month }}
                    </strong>
                </div>

                <div>
                    <span>من</span>
                    <strong dir="ltr">
                        {{
                            $financialPeriod
                                ->start_date
                                ?->format('Y-m-d')
                        }}
                    </strong>
                </div>

                <div>
                    <span>إلى</span>
                    <strong dir="ltr">
                        {{
                            $financialPeriod
                                ->end_date
                                ?->format('Y-m-d')
                        }}
                    </strong>
                </div>

                <div>
                    <span>الحالة</span>
                    <strong>
                        <span
                            class="badge {{
                                $isOpen
                                    ? 'badge-active'
                                    : 'badge-inactive'
                            }}"
                        >
                            {{
                                $isOpen
                                    ? 'مفتوحة'
                                    : 'مغلقة'
                            }}
                        </span>
                    </strong>
                </div>

                <div>
                    <span>الرصيد الافتتاحي</span>
                    <strong>
                        ₪ {{
                            number_format(
                                (float)
                                $financialPeriod
                                    ->opening_balance,
                                2
                            )
                        }}
                    </strong>
                </div>

                <div>
                    <span>الرصيد الختامي</span>
                    <strong>
                        @if($financialPeriod->closing_balance !== null)
                            ₪ {{
                                number_format(
                                    (float)
                                    $financialPeriod
                                        ->closing_balance,
                                    2
                                )
                            }}
                        @else
                            —
                        @endif
                    </strong>
                </div>

                <div>
                    <span>فتحت بواسطة</span>
                    <strong>
                        {{
                            $financialPeriod
                                ->openedBy
                                ?->display_name
                            ?? $financialPeriod
                                ->openedBy
                                ?->name
                            ?? '—'
                        }}
                    </strong>
                </div>

                <div>
                    <span>أغلقت بواسطة</span>
                    <strong>
                        {{
                            $financialPeriod
                                ->closedBy
                                ?->display_name
                            ?? $financialPeriod
                                ->closedBy
                                ?->name
                            ?? '—'
                        }}
                    </strong>
                </div>
            </div>

            @if($financialPeriod->notes)
                <div
                    style="
                        margin-top:1rem;
                        padding:.8rem;
                        border:1px solid var(--border);
                        border-radius:10px
                    "
                >
                    <strong
                        style="
                            display:block;
                            margin-bottom:.35rem
                        "
                    >
                        ملاحظات
                    </strong>

                    <div
                        style="
                            color:var(--text-muted);
                            white-space:pre-line
                        "
                    >
                        {{ $financialPeriod->notes }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">
                    ملخص الفروع
                </div>

                <div
                    style="
                        margin-top:.2rem;
                        font-size:.76rem;
                        color:var(--text-muted)
                    "
                >
                    @if($isLivePreview)
                        معاينة محسوبة مباشرة من السجلات الحالية —
                        لم يتم حفظ Snapshot لهذه الفترة بعد.
                    @else
                        Snapshot محفوظ وقت إغلاق الفترة.
                    @endif
                </div>
            </div>

            <span
                class="badge {{
                    $isLivePreview
                        ? 'badge-warning'
                        : 'badge-active'
                }}"
            >
                {{
                    $isLivePreview
                        ? 'معاينة حية'
                        : 'مثبت'
                }}
            </span>
        </div>

        <div class="card-body">
            <div class="period-kpis">
                <div>
                    <span>إجمالي المبيعات</span>
                    <strong>
                        ₪ {{
                            number_format(
                                $totals['net_sales'],
                                2
                            )
                        }}
                    </strong>
                </div>

                <div>
                    <span>التحصيلات</span>
                    <strong>
                        ₪ {{
                            number_format(
                                $totals[
                                    'confirmed_collections'
                                ],
                                2
                            )
                        }}
                    </strong>
                </div>

                <div>
                    <span>المستحقات</span>
                    <strong>
                        ₪ {{
                            number_format(
                                $totals[
                                    'outstanding_amount'
                                ],
                                2
                            )
                        }}
                    </strong>
                </div>

                <div>
                    <span>الفواتير</span>
                    <strong>
                        {{
                            number_format(
                                $totals['invoice_count']
                            )
                        }}
                    </strong>
                </div>
            </div>
        </div>

        <div
            class="table-wrap"
            style="border:none;border-radius:0"
        >
            <table class="data-table">
                <thead>
                    <tr>
                        <th>الموقع</th>
                        <th>إجمالي المبيعات</th>
                        <th>الخصومات</th>
                        <th>صافي المبيعات</th>
                        <th>التحصيلات</th>
                        <th>الاستردادات</th>
                        <th>المستحقات</th>
                        <th>الفواتير</th>
                        <th>الطلبات</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>
                                {{
                                    $row->location?->name
                                    ?? 'موقع غير محدد'
                                }}
                            </td>

                            <td>
                                ₪ {{
                                    number_format(
                                        (float)
                                        $row->gross_sales,
                                        2
                                    )
                                }}
                            </td>

                            <td>
                                ₪ {{
                                    number_format(
                                        (float)
                                        $row->discounts,
                                        2
                                    )
                                }}
                            </td>

                            <td>
                                <strong>
                                    ₪ {{
                                        number_format(
                                            (float)
                                            $row->net_sales,
                                            2
                                        )
                                    }}
                                </strong>
                            </td>

                            <td>
                                ₪ {{
                                    number_format(
                                        (float)
                                        $row
                                            ->confirmed_collections,
                                        2
                                    )
                                }}
                            </td>

                            <td>
                                ₪ {{
                                    number_format(
                                        (float)
                                        $row->refunds,
                                        2
                                    )
                                }}
                            </td>

                            <td>
                                ₪ {{
                                    number_format(
                                        (float)
                                        $row
                                            ->outstanding_amount,
                                        2
                                    )
                                }}
                            </td>

                            <td>
                                {{
                                    number_format(
                                        (int)
                                        $row->invoice_count
                                    )
                                }}
                            </td>

                            <td>
                                {{
                                    number_format(
                                        (int)
                                        $row->order_count
                                    )
                                }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state-sm">
                                    لا توجد مواقع فعالة أو بيانات
                                    قابلة للحساب لهذه الفترة.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <th>الإجمالي</th>
                            <th>
                                ₪ {{
                                    number_format(
                                        $totals[
                                            'gross_sales'
                                        ],
                                        2
                                    )
                                }}
                            </th>
                            <th>
                                ₪ {{
                                    number_format(
                                        $totals[
                                            'discounts'
                                        ],
                                        2
                                    )
                                }}
                            </th>
                            <th>
                                ₪ {{
                                    number_format(
                                        $totals[
                                            'net_sales'
                                        ],
                                        2
                                    )
                                }}
                            </th>
                            <th>
                                ₪ {{
                                    number_format(
                                        $totals[
                                            'confirmed_collections'
                                        ],
                                        2
                                    )
                                }}
                            </th>
                            <th>
                                ₪ {{
                                    number_format(
                                        $totals['refunds'],
                                        2
                                    )
                                }}
                            </th>
                            <th>
                                ₪ {{
                                    number_format(
                                        $totals[
                                            'outstanding_amount'
                                        ],
                                        2
                                    )
                                }}
                            </th>
                            <th>
                                {{
                                    number_format(
                                        $totals[
                                            'invoice_count'
                                        ]
                                    )
                                }}
                            </th>
                            <th>
                                {{
                                    number_format(
                                        $totals[
                                            'order_count'
                                        ]
                                    )
                                }}
                            </th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

@if($financialPeriod->adjustments->isNotEmpty())
    <div class="card" style="margin-top:1rem">
        <div class="card-header">
            <span class="card-title">
                التسويات المالية
            </span>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>النوع</th>
                        <th>المبلغ</th>
                        <th>السبب</th>
                        <th>الحالة</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($financialPeriod->adjustments as $adjustment)
                        @php
                            $adjustmentStatus =
                                $adjustment->status
                                instanceof \BackedEnum
                                    ? $adjustment
                                        ->status
                                        ->value
                                    : (string)
                                        $adjustment
                                            ->status;
                        @endphp

                        <tr>
                            <td>
                                #{{ $adjustment->id }}
                            </td>

                            <td>
                                {{
                                    $adjustment
                                        ->adjustment_type
                                }}
                            </td>

                            <td>
                                ₪ {{
                                    number_format(
                                        (float)
                                        $adjustment->amount,
                                        2
                                    )
                                }}
                            </td>

                            <td>
                                {{ $adjustment->reason }}
                            </td>

                            <td>
                                {{ $adjustmentStatus }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<style>
.period-details {
    display:grid;
    gap:0;
}

.period-details > div {
    display:flex;
    justify-content:space-between;
    gap:1rem;
    padding:.75rem 0;
    border-bottom:1px solid var(--border);
}

.period-details > div:last-child {
    border-bottom:0;
}

.period-details span {
    color:var(--text-muted);
}

.period-kpis {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:.7rem;
}

.period-kpis > div {
    padding:.8rem;
    border:1px solid var(--border);
    border-radius:10px;
    background:var(--card-bg);
}

.period-kpis span,
.period-kpis strong {
    display:block;
}

.period-kpis span {
    color:var(--text-muted);
    font-size:.75rem;
    margin-bottom:.25rem;
}

@media(max-width:1100px) {
    .period-show-grid {
        grid-template-columns:1fr !important;
    }

    .period-kpis {
        grid-template-columns:1fr 1fr;
    }
}

@media(max-width:600px) {
    .period-kpis {
        grid-template-columns:1fr;
    }
}
</style>

@endsection
