@extends('layouts.app')

@section('title', 'مرتجع شراء ' . $purchaseReturn->return_number)

@section('content')
    @include('procurement.partials.flash')

    @php
        $statusValue = $purchaseReturn->status instanceof \BackedEnum
            ? $purchaseReturn->status->value
            : (string) $purchaseReturn->status;

        $statusLabels = [
            'draft' => 'مسودة',
            'posted' => 'مرحّل',
            'cancelled' => 'ملغى',
        ];

        $statusClasses = [
            'draft' => 'badge-warning',
            'posted' => 'badge-success',
            'cancelled' => 'badge-inactive',
        ];

        $currencyCode = strtoupper((string) ($purchaseReturn->currency?->code ?? ''));
        $currencyLabel = match ($currencyCode) {
            'ILS' => 'شيكل',
            'USD' => 'دولار أمريكي',
            'JOD' => 'دينار أردني',
            'EUR' => 'يورو',
            'GBP' => 'جنيه إسترليني',
            'SAR' => 'ريال سعودي',
            'AED' => 'درهم إماراتي',
            default => $purchaseReturn->currency?->name_ar
                ?? 'غير محددة',
        };

        $actorName = static function ($user): string {
            if (! $user) {
                return 'غير مسجل';
            }

            return (string) (
                data_get($user, 'employee.full_name')
                ?? data_get($user, 'display_name')
                ?? 'مستخدم رقم ' . data_get($user, 'id', '—')
            );
        };
    @endphp

    <div class="page-actions">
        <div class="page-actions-title">
            مرتجع شراء {{ $purchaseReturn->return_number }}
            <span class="badge {{ $statusClasses[$statusValue] ?? 'badge-muted' }}" style="margin-inline-start:.5rem">
                {{ $statusLabels[$statusValue] ?? \App\Support\ArabicDisplay::status($statusValue) }}
            </span>
        </div>

        <div class="action-btns">
            <a class="btn btn-ghost" href="{{ route('purchase-returns.index') }}">
                رجوع
            </a>

            @if ($statusValue === 'draft')
                @can('post', $purchaseReturn)
                    <form
                        action="{{ route('purchase-returns.post', $purchaseReturn) }}"
                        method="POST"
                        style="display:inline"
                        onsubmit="return confirm('هل أنت متأكد من ترحيل المرتجع؟ سيتم إخراج الكميات من المخزون ولا يمكن تعديل العملية بعد ذلك.')"
                    >
                        @csrf
                        <button class="btn btn-gold" type="submit">ترحيل المرتجع</button>
                    </form>
                @endcan

                @can('cancel', $purchaseReturn)
                    <form
                        action="{{ route('purchase-returns.cancel', $purchaseReturn) }}"
                        method="POST"
                        style="display:inline"
                        onsubmit="return confirm('هل أنت متأكد من إلغاء مسودة المرتجع؟')"
                    >
                        @csrf
                        <button class="btn btn-ghost" style="color:var(--error)" type="submit">
                            إلغاء المرتجع
                        </button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    <div class="dashboard-row">
        <div class="card">
            <div class="card-header">
                <span class="card-title">بيانات المرتجع</span>
            </div>

            <div class="card-body">
                <div class="detail-list">
                    <div class="detail-row">
                        <span class="detail-label">رقم المرتجع</span>
                        <span class="detail-value" dir="ltr">{{ $purchaseReturn->return_number }}</span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">المورد</span>
                        <span class="detail-value">
                            @if ($purchaseReturn->supplier)
                                <a href="{{ route('suppliers.show', $purchaseReturn->supplier) }}">
                                    {{ $purchaseReturn->supplier->name }}
                                </a>
                            @else
                                —
                            @endif
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">الموقع</span>
                        <span class="detail-value">{{ $purchaseReturn->location?->name ?? '—' }}</span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">تاريخ المرتجع</span>
                        <span class="detail-value">
                            {{ $purchaseReturn->returned_at?->format('Y/m/d H:i') ?? '—' }}
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">أنشأه</span>
                        <span class="detail-value">
                            {{ $actorName($purchaseReturn->returner) }}
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">رُحّل بواسطة</span>
                        <span class="detail-value">
                            {{ $purchaseReturn->poster
                                ? $actorName($purchaseReturn->poster)
                                : 'لم يُرحّل بعد' }}
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">تاريخ الترحيل</span>
                        <span class="detail-value">
                            {{ $purchaseReturn->posted_at?->format('Y/m/d H:i') ?? '—' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">المستندات والقيمة</span>
            </div>

            <div class="card-body">
                <div class="detail-list">
                    <div class="detail-row">
                        <span class="detail-label">سند الاستلام</span>
                        <span class="detail-value">
                            @if ($purchaseReturn->goodsReceipt)
                                <a href="{{ route('goods-receipts.show', $purchaseReturn->goodsReceipt) }}">
                                    {{ $purchaseReturn->goodsReceipt->receipt_number }}
                                </a>
                            @else
                                —
                            @endif
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">فاتورة المورد</span>
                        <span class="detail-value">
                            @if ($purchaseReturn->supplierInvoice)
                                <a href="{{ route('supplier-invoices.show', $purchaseReturn->supplierInvoice) }}">
                                    {{ $purchaseReturn->supplierInvoice->invoice_number }}
                                </a>
                            @else
                                غير مرتبطة
                            @endif
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">العملة</span>
                        <span class="detail-value">{{ $currencyLabel }}</span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">سعر الصرف</span>
                        <span class="detail-value" dir="ltr">
                            {{ number_format((float) $purchaseReturn->exchange_rate, 8, '.', '') }}
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label"><strong>إجمالي المرتجع</strong></span>
                        <span class="detail-value" dir="ltr">
                            <strong>
                                {{ number_format((float) $purchaseReturn->grand_total, 2) }}
                                {{ $currencyLabel }}
                            </strong>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">الإجمالي بالعملة الأساسية</span>
                        <span class="detail-value" dir="ltr">
                            {{ number_format((float) $purchaseReturn->base_grand_total, 2) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-wrap" style="margin-top:1rem">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الصنف</th>
                    <th>التشغيلة</th>
                    <th>كمية المرتجع</th>
                    <th>الكمية بوحدة المخزون</th>
                    <th>معامل التحويل</th>
                    <th>تكلفة الوحدة</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($purchaseReturn->items as $item)
                    @php
                        $returnQuantity = (float) data_get($item, 'return_quantity', 0);
                        $baseQuantity = (float) data_get($item, 'return_base_quantity', $returnQuantity);
                        $conversionFactor = (float) data_get($item, 'conversion_factor', 1);
                        $unitCost = (float) data_get(
                            $item,
                            'unit_cost',
                            data_get($item, 'goodsReceiptItem.unit_cost', 0)
                        );
                        $lineTotal = (float) data_get($item, 'line_total', $returnQuantity * $unitCost);
                        $batchNumber = data_get($item, 'inventoryBatch.batch_number')
                            ?? data_get($item, 'inventoryBatch.lot_number')
                            ?? '—';
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            <strong>
                                {{ $item->product?->name_ar ?? $item->product?->name ?? 'صنف محذوف' }}
                            </strong>
                            @if (data_get($item, 'product.sku'))
                                <small style="display:block;color:var(--text-muted)" dir="ltr">
                                    {{ data_get($item, 'product.sku') }}
                                </small>
                            @endif
                        </td>
                        <td dir="ltr">{{ $batchNumber }}</td>
                        <td dir="ltr">{{ number_format($returnQuantity, 3) }}</td>
                        <td dir="ltr">{{ number_format($baseQuantity, 3) }}</td>
                        <td dir="ltr">{{ number_format($conversionFactor, 6) }}</td>
                        <td dir="ltr">{{ number_format($unitCost, 4) }}</td>
                        <td dir="ltr">
                            {{ number_format($lineTotal, 2) }} {{ $currencyLabel }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center">لا توجد أصناف في هذا المرتجع.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($purchaseReturn->notes)
        <div class="card" style="margin-top:1rem">
            <div class="card-header">
                <span class="card-title">ملاحظات</span>
            </div>
            <div class="card-body">
                {{ $purchaseReturn->notes }}
            </div>
        </div>
    @endif
@endsection
