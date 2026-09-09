@extends('layouts.app')

@section('title', 'سند استلام مشتريات')

@section('content')

    @include('procurement.partials.flash')


    <div class="page-actions">

        <div class="page-actions-title">

            {{ $goodsReceipt->receipt_number }}

            <small style="font-weight:400">
                —
                {{ $goodsReceipt->statusLabel() }}
            </small>

        </div>


        <div class="action-btns">

            <a
                class="btn btn-ghost"
                href="{{ route('goods-receipts.index') }}"
            >
                رجوع
            </a>


            @if ($goodsReceipt->statusValue() === 'draft')

                @can('goods_receipts.approve')

                    <form
                        id="post-goods-receipt-form"
                        action="{{ route(
                            'goods-receipts.post',
                            $goodsReceipt
                        ) }}"
                        method="POST"
                        style="display:inline"
                        onsubmit="
                            return confirm(
                                'هل أنت متأكد من ترحيل سند الاستلام إلى المخزون؟ بعد الترحيل ستتحدث كميات المخزون.'
                            )
                        "
                    >
                        @csrf

                        <button
                            class="btn btn-gold"
                            type="submit"
                        >
                            ترحيل إلى المخزون
                        </button>

                    </form>

                @endcan

            @endif


            @if ($goodsReceipt->statusValue() === 'posted')

                @can('supplier_invoices.create')

                    <a
                        class="btn btn-outline"
                        href="{{ route(
                            'supplier-invoices.create',
                            [
                                'goods_receipt_id'
                                    => $goodsReceipt->id
                            ]
                        ) }}"
                    >
                        تسجيل فاتورة مورد
                    </a>

                @endcan


                @can('purchase_returns.create')

                    <a
                        class="btn btn-outline"
                        href="{{ route(
                            'purchase-returns.create',
                            [
                                'goods_receipt_id'
                                    => $goodsReceipt->id
                            ]
                        ) }}"
                    >
                        مرتجع مورد
                    </a>

                @endcan

            @endif

        </div>

    </div>



    {{-- Receipt Information --}}
    <div class="dashboard-row">

        <div class="card">

            <div class="card-header">

                <span class="card-title">
                    بيانات السند
                </span>

            </div>


            <div class="card-body">

                <div class="detail-list">

                    <div class="detail-row">

                        <span class="detail-label">
                            أمر الشراء
                        </span>

                        <span class="detail-value">

                            <a
                                href="{{ route(
                                    'purchase-orders.show',
                                    $goodsReceipt->purchaseOrder
                                ) }}"
                            >
                                {{ $goodsReceipt->purchaseOrder?->purchase_order_number }}
                            </a>

                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="detail-label">
                            المورد
                        </span>

                        <span class="detail-value">
                            {{ $goodsReceipt->supplier?->name }}
                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="detail-label">
                            الموقع
                        </span>

                        <span class="detail-value">
                            {{ $goodsReceipt->location?->name }}
                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="detail-label">
                            المستلم
                        </span>

                        <span class="detail-value">
                            {{ $goodsReceipt->receiver?->display_name ?? "غير مسجل" }}
                        </span>

                    </div>

                </div>

            </div>

        </div>


        <div class="card">

            <div class="card-header">

                <span class="card-title">
                    التتبع
                </span>

            </div>


            <div class="card-body">

                <div class="detail-list">

                    <div class="detail-row">

                        <span class="detail-label">
                            تاريخ الاستلام
                        </span>

                        <span class="detail-value">
                            {{ $goodsReceipt->received_at?->format('Y/m/d H:i') }}
                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="detail-label">
                            تاريخ الترحيل
                        </span>

                        <span class="detail-value">
                            {{ $goodsReceipt->posted_at?->format('Y/m/d H:i') ?? '—' }}
                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="detail-label">
                            الملاحظات
                        </span>

                        <span class="detail-value">
                            {{ $goodsReceipt->notes ?: '—' }}
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>



    @if($goodsReceipt->statusValue() === 'draft')

        <div class="traceability-notice">

            <div class="traceability-notice-icon">
                📦
            </div>

            <div>

                <strong>
                    أكمل بيانات تتبع المنتجات قبل الترحيل
                </strong>

                <div>
                    المنتجات التي تتبع الدفعات تحتاج رقم دفعة،
                    والمنتجات التي تتبع الصلاحية تحتاج تاريخ صلاحية.
                </div>

            </div>

        </div>

    @endif



    {{-- Items --}}
    <div
        class="table-wrap"
        style="margin-top:1rem"
    >

        <table class="data-table">

            <thead>

                <tr>

                    <th>
                        المنتج
                    </th>

                    <th>
                        المطلوب
                    </th>

                    <th>
                        المستلم
                    </th>

                    <th>
                        المقبول
                    </th>

                    <th>
                        المرفوض
                    </th>

                    <th>
                        التكلفة
                    </th>

                    <th>
                        رقم الدفعة
                    </th>

                    <th>
                        تاريخ الإنتاج
                    </th>

                    <th>
                        تاريخ الصلاحية
                    </th>

                </tr>

            </thead>


            <tbody>

                @foreach ($goodsReceipt->items as $item)

                    @php
                        $tracksBatch =
                            (bool) $item->product?->tracks_batch;

                        $tracksExpiry =
                            (bool) $item->product?->tracks_expiry;

                        $hasAcceptedQuantity =
                            (float) $item->accepted_quantity > 0;
                    @endphp


                    <tr>

                        {{-- Product --}}
                        <td>

                            <div class="receipt-product-name">

                                {{ $item->product?->name_ar
                                    ?: $item->product?->name }}

                            </div>


                            <div class="tracking-badges">

                                @if($tracksBatch)

                                    <span class="tracking-badge">
                                        يتتبع الدفعات
                                    </span>

                                @endif


                                @if($tracksExpiry)

                                    <span class="tracking-badge">
                                        يتتبع الصلاحية
                                    </span>

                                @endif

                            </div>

                        </td>


                        {{-- Ordered --}}
                        <td class="numeric-cell">

                            {{ number_format(
                                $item->ordered_quantity,
                                3
                            ) }}

                        </td>


                        {{-- Received --}}
                        <td class="numeric-cell">

                            {{ number_format(
                                $item->received_quantity,
                                3
                            ) }}

                        </td>


                        {{-- Accepted --}}
                        <td class="numeric-cell">

                            {{ number_format(
                                $item->accepted_quantity,
                                3
                            ) }}

                        </td>


                        {{-- Rejected --}}
                        <td class="numeric-cell">

                            {{ number_format(
                                $item->rejected_quantity,
                                3
                            ) }}

                        </td>


                        {{-- Cost --}}
                        <td class="numeric-cell">

                            {{ number_format(
                                $item->unit_cost,
                                4
                            ) }}

                            {{ $goodsReceipt->currency?->displayName() }}

                        </td>


                        {{-- Batch --}}
                        <td>

                            @if(
                                $goodsReceipt->statusValue()
                                ===
                                'draft'
                            )

                                <input
                                    class="form-input trace-input"
                                    type="text"

                                    name="items[{{ $item->id }}][batch_number]"

                                    form="post-goods-receipt-form"

                                    value="{{ old(
                                        "items.{$item->id}.batch_number",
                                        $item->batch_number
                                    ) }}"

                                    placeholder="{{ $tracksBatch
                                        ? 'رقم الدفعة *'
                                        : 'اختياري'
                                    }}"

                                    @if(
                                        $tracksBatch
                                        &&
                                        $hasAcceptedQuantity
                                    )
                                        required
                                    @endif
                                >


                                @error(
                                    "items.{$item->id}.batch_number"
                                )

                                    <span class="form-error">
                                        {{ $message }}
                                    </span>

                                @enderror

                            @else

                                <span class="tracking-value">

                                    {{ $item->batch_number ?: '—' }}

                                </span>

                            @endif

                        </td>


                        {{-- Manufacturing --}}
                        <td>

                            @if(
                                $goodsReceipt->statusValue()
                                ===
                                'draft'
                            )

                                <input
                                    class="form-input trace-input"
                                    type="date"

                                    name="items[{{ $item->id }}][manufacturing_date]"

                                    form="post-goods-receipt-form"

                                    value="{{ old(
                                        "items.{$item->id}.manufacturing_date",
                                        $item->manufacturing_date?->format('Y-m-d')
                                    ) }}"
                                >


                                @error(
                                    "items.{$item->id}.manufacturing_date"
                                )

                                    <span class="form-error">
                                        {{ $message }}
                                    </span>

                                @enderror

                            @else

                                <span class="tracking-value">

                                    {{ $item->manufacturing_date?->format('Y/m/d') ?? '—' }}

                                </span>

                            @endif

                        </td>


                        {{-- Expiry --}}
                        <td>

                            @if(
                                $goodsReceipt->statusValue()
                                ===
                                'draft'
                            )

                                <input
                                    class="form-input trace-input"
                                    type="date"

                                    name="items[{{ $item->id }}][expiry_date]"

                                    form="post-goods-receipt-form"

                                    value="{{ old(
                                        "items.{$item->id}.expiry_date",
                                        $item->expiry_date?->format('Y-m-d')
                                    ) }}"

                                    @if(
                                        $tracksExpiry
                                        &&
                                        $hasAcceptedQuantity
                                    )
                                        required
                                    @endif
                                >


                                @error(
                                    "items.{$item->id}.expiry_date"
                                )

                                    <span class="form-error">
                                        {{ $message }}
                                    </span>

                                @enderror

                            @else

                                <span class="tracking-value">

                                    {{ $item->expiry_date?->format('Y/m/d') ?? '—' }}

                                </span>

                            @endif

                        </td>

                    </tr>

                @endforeach

            </tbody>

        </table>

    </div>



    @if($goodsReceipt->statusValue() === 'draft')

        <div class="draft-post-footer">

            <div class="draft-post-help">

                <strong>
                    جاهز للترحيل؟
                </strong>

                <span>
                    تأكد من رقم الدفعة والصلاحية قبل إضافة الكميات للمخزون.
                </span>

            </div>


            @can('goods_receipts.approve')

                <button
                    class="btn btn-gold"
                    type="submit"
                    form="post-goods-receipt-form"
                >
                    ترحيل إلى المخزون
                </button>

            @endcan

        </div>

    @endif



    <style>

        .traceability-notice {
            display: flex;
            align-items: center;
            gap: .85rem;

            margin-top: 1rem;
            padding: .9rem 1rem;

            border: 1px solid #ead99b;
            border-radius: .75rem;

            background: #fffbea;

            color: #6f5811;
        }


        .traceability-notice-icon {
            width: 42px;
            height: 42px;

            display: flex;
            align-items: center;
            justify-content: center;

            flex-shrink: 0;

            border-radius: .65rem;

            background: #fff2b5;

            font-size: 1.25rem;
        }


        .traceability-notice strong {
            display: block;
            margin-bottom: .15rem;
        }


        .traceability-notice div div {
            font-size: .8rem;
            color: #8a742e;
        }


        .receipt-product-name {
            font-weight: 700;
            min-width: 150px;
        }


        .tracking-badges {
            display: flex;
            gap: .3rem;
            flex-wrap: wrap;

            margin-top: .35rem;
        }


        .tracking-badge {
            display: inline-flex;

            padding: .18rem .4rem;

            border-radius: 20px;

            background: #f5f1df;

            color: #8c721d;

            font-size: .66rem;
            font-weight: 700;
        }


        .trace-input {
            min-width: 145px;
        }


        .tracking-value {
            white-space: nowrap;
        }


        .numeric-cell {
            direction: ltr;
            text-align: center;
            white-space: nowrap;

            font-variant-numeric:
                tabular-nums;
        }


        .draft-post-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 1rem;

            margin-top: 1rem;
            padding: 1rem 1.15rem;

            border: 1px solid var(--border-light);
            border-radius: .75rem;

            background: #fff;
        }


        .draft-post-help {
            display: flex;
            flex-direction: column;
            gap: .15rem;
        }


        .draft-post-help span {
            color: var(--text-muted);
            font-size: .78rem;
        }


        @media (max-width: 768px) {

            .draft-post-footer {
                align-items: stretch;
                flex-direction: column;
            }


            .draft-post-footer .btn {
                width: 100%;
                justify-content: center;
            }

        }

    </style>

@endsection