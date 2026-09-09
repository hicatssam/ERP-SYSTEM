@extends('layouts.app')

@section('title', 'المخزون')

@section('content')

<style>
    .inventory-page{display:flex;flex-direction:column;gap:1rem}
    .inventory-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap}
    .inventory-header h1{margin:0;font-size:1.2rem;font-weight:850}
    .inventory-header p{margin:.25rem 0 0;color:var(--text-muted);font-size:.78rem}
    .inventory-scope-badge{display:inline-flex;align-items:center;gap:.35rem;margin-top:.4rem;padding:.28rem .55rem;border-radius:999px;background:color-mix(in srgb,var(--theme-primary) 10%,transparent);color:var(--theme-primary);font-size:.68rem;font-weight:800}
    .expiry-summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}
    .expiry-summary-card{position:relative;overflow:hidden;padding:1rem;border:1px solid var(--border);border-radius:14px;background:var(--surface)}
    .expiry-summary-card::before{content:"";position:absolute;inset-inline-start:0;top:0;bottom:0;width:4px;background:var(--theme-primary)}
    .expiry-summary-card.is-expired::before,.expiry-summary-card.is-critical::before{background:var(--danger)}
    .expiry-summary-card.is-warning::before{background:var(--warning)}
    .expiry-summary-label{color:var(--text-muted);font-size:.72rem}
    .expiry-summary-value{margin-top:.35rem;font-size:1.5rem;font-weight:900;line-height:1}
    .expiry-summary-note{margin-top:.35rem;color:var(--text-muted);font-size:.68rem}
    .inventory-section{border:1px solid var(--border);border-radius:16px;background:var(--surface);overflow:hidden}
    .inventory-section-header{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.1rem;border-bottom:1px solid var(--border)}
    .inventory-section-header h2{margin:0;font-size:.95rem;font-weight:850}
    .inventory-section-header p{margin:.2rem 0 0;color:var(--text-muted);font-size:.7rem}
    .inventory-filter{padding:.9rem 1rem;border:1px solid var(--border);border-radius:14px;background:var(--surface)}
    .expiry-product{display:flex;flex-direction:column;gap:.15rem}
    .expiry-product strong{font-size:.78rem}
    .expiry-product small{color:var(--text-muted);font-size:.66rem}
    .expiry-date{font-weight:850;white-space:nowrap}
    .expiry-days{display:block;margin-top:.15rem;font-size:.66rem;font-weight:750}
    .expiry-status{display:inline-flex;align-items:center;justify-content:center;min-width:82px;padding:.32rem .55rem;border-radius:999px;font-size:.66rem;font-weight:850}
    .expiry-status.expired,.expiry-status.critical{color:var(--danger);background:color-mix(in srgb,var(--danger) 10%,transparent)}
    .expiry-status.warning{color:var(--warning);background:color-mix(in srgb,var(--warning) 11%,transparent)}
    .expiry-status.soon{color:var(--theme-primary);background:color-mix(in srgb,var(--theme-primary) 10%,transparent)}
    .inventory-pagination{padding:.8rem 1rem;border-top:1px solid var(--border)}
    @media(max-width:900px){.expiry-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:560px){.expiry-summary-grid{grid-template-columns:1fr}}
</style>

<div class="inventory-page">

    <div class="inventory-header">
        <div>
            <h1>المخزون</h1>

            <p>
                متابعة الكميات الحالية والدفعات القريبة
                من انتهاء الصلاحية.
            </p>

            <div class="inventory-scope-badge">
                @if($showAllLocations)
                    كل المواقع والفروع
                @else
                    {{ $location?->name ?? 'لا يوجد موقع' }}
                @endif
            </div>
        </div>
    </div>

    @if($locations->count() > 1)
        <div class="inventory-filter">
            <form method="GET" class="filter-grid">
                <div class="filter-group">
                    <label class="filter-label">الموقع</label>

                    <select
                        name="location_id"
                        class="form-select"
                        onchange="this.form.submit()"
                    >
                        @if(auth()->user()?->isAdmin())
                            <option value="" @selected($showAllLocations)>
                                كل المواقع والفروع
                            </option>
                        @endif

                        @foreach($locations as $loc)
                            <option
                                value="{{ $loc->id }}"
                                @selected(
                                    ! $showAllLocations
                                    && $loc->id == $location?->id
                                )
                            >
                                {{ $loc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    @endif

    <div class="expiry-summary-grid">
        <div class="expiry-summary-card is-expired">
            <div class="expiry-summary-label">منتهي الصلاحية</div>
            <div class="expiry-summary-value">{{ number_format($expirySummary['expired']) }}</div>
            <div class="expiry-summary-note">دفعات ما زالت تحتوي كمية متاحة</div>
        </div>

        <div class="expiry-summary-card is-critical">
            <div class="expiry-summary-label">خلال 7 أيام</div>
            <div class="expiry-summary-value">{{ number_format($expirySummary['within_7_days']) }}</div>
            <div class="expiry-summary-note">تنبيه حرج</div>
        </div>

        <div class="expiry-summary-card is-warning">
            <div class="expiry-summary-label">خلال 30 يومًا</div>
            <div class="expiry-summary-value">{{ number_format($expirySummary['within_30_days']) }}</div>
            <div class="expiry-summary-note">تنبيه مهم</div>
        </div>

        <div class="expiry-summary-card">
            <div class="expiry-summary-label">خلال 60 يومًا</div>
            <div class="expiry-summary-value">{{ number_format($expirySummary['within_60_days']) }}</div>
            <div class="expiry-summary-note">تنبيه مبكر قبل شهرين</div>
        </div>
    </div>

    <section class="inventory-section">
        <div class="inventory-section-header">
            <div>
                <h2>الدفعات القريبة من انتهاء الصلاحية</h2>
                <p>يعرض الدفعات المنتهية أو التي ستنتهي خلال 60 يومًا ولديها كمية متاحة.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>المنتج</th>
                        @if($showAllLocations)<th>الموقع</th>@endif
                        <th>Batch</th>
                        <th>تاريخ الإنتاج</th>
                        <th>تاريخ الصلاحية</th>
                        <th>الكمية المتاحة</th>
                        <th>الحالة</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($expiryBatches as $batch)
                        @php
                            $expiryDate = \Carbon\Carbon::parse($batch->expiry_date)->startOfDay();
                            $daysLeft = (int) now()->startOfDay()->diffInDays($expiryDate, false);

                            if ($daysLeft < 0) {
                                $expiryClass = 'expired';
                                $expiryLabel = 'منتهي';
                                $daysLabel = 'منتهي منذ ' . abs($daysLeft) . ' يوم';
                            } elseif ($daysLeft === 0) {
                                $expiryClass = 'critical';
                                $expiryLabel = 'ينتهي اليوم';
                                $daysLabel = 'اليوم';
                            } elseif ($daysLeft <= 7) {
                                $expiryClass = 'critical';
                                $expiryLabel = 'حرج';
                                $daysLabel = 'متبقي ' . $daysLeft . ' يوم';
                            } elseif ($daysLeft <= 30) {
                                $expiryClass = 'warning';
                                $expiryLabel = 'قريب';
                                $daysLabel = 'متبقي ' . $daysLeft . ' يوم';
                            } else {
                                $expiryClass = 'soon';
                                $expiryLabel = 'مبكر';
                                $daysLabel = 'متبقي ' . $daysLeft . ' يوم';
                            }
                        @endphp

                        <tr>
                            <td>
                                <div class="expiry-product">
                                    <strong>{{ $batch->name_ar ?: $batch->name ?: 'منتج' }}</strong>
                                    <small>{{ $batch->sku ?: '—' }}</small>
                                </div>
                            </td>

                            @if($showAllLocations)
                                <td>
                                    <strong>{{ $batch->location_name ?? '—' }}</strong>
                                    @if($batch->location_code)
                                        <div style="font-size:.68rem;color:var(--text-muted)">
                                            {{ $batch->location_code }}
                                        </div>
                                    @endif
                                </td>
                            @endif

                            <td><strong dir="ltr">{{ $batch->batch_number ?: '—' }}</strong></td>

                            <td dir="ltr">
                                {{ $batch->manufacturing_date
                                    ? \Carbon\Carbon::parse($batch->manufacturing_date)->format('Y-m-d')
                                    : '—' }}
                            </td>

                            <td>
                                <span class="expiry-date" dir="ltr">{{ $expiryDate->format('Y-m-d') }}</span>
                                <small
                                    class="expiry-days"
                                    style="color:{{
                                        $daysLeft <= 7
                                            ? 'var(--danger)'
                                            : ($daysLeft <= 30 ? 'var(--warning)' : 'var(--text-muted)')
                                    }}"
                                >
                                    {{ $daysLabel }}
                                </small>
                            </td>

                            <td><strong>{{ number_format((float) $batch->available_quantity, 3) }}</strong></td>

                            <td>
                                <span class="expiry-status {{ $expiryClass }}">
                                    {{ $expiryLabel }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $showAllLocations ? 7 : 6 }}">
                                <div class="empty-state-sm">
                                    لا توجد دفعات منتهية أو قريبة من الانتهاء خلال 60 يومًا في النطاق المحدد.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($expiryBatches instanceof \Illuminate\Contracts\Pagination\Paginator)
            <div class="inventory-pagination">
                {{ $expiryBatches->withQueryString()->links() }}
            </div>
        @endif
    </section>

    <section class="inventory-section">
        <div class="inventory-section-header">
            <div>
                <h2>مستوى المخزون الحالي</h2>
                <p>الكميات المتوفرة والحد الأدنى لكل منتج.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>المنتج</th>
                        @if($showAllLocations)<th>الموقع</th>@endif
                        <th>الفئة</th>
                        <th>الكمية</th>
                        <th>الحد الأدنى</th>
                        <th>الحالة</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($inventories as $inv)
                        @php
                            $rowLocationId = (int) $inv->location_id;

                            $lp = $inv->product
                                ?->locationProducts
                                ->where('location_id', $rowLocationId)
                                ->first();

                            $minStock = $lp?->minimum_stock_level ?? 0;
                            $isLow = $inv->quantity < $minStock;
                        @endphp

                        <tr>
                            <td>
                                <strong>{{ $inv->product?->name_ar ?? $inv->product?->name }}</strong>
                                <div style="font-size:.75rem;color:var(--text-muted)">
                                    {{ $inv->product?->sku }}
                                </div>
                            </td>

                            @if($showAllLocations)
                                <td>{{ $inv->location?->name ?? '—' }}</td>
                            @endif

                            <td>
                                {{ $inv->product?->category?->name_ar
                                    ?? $inv->product?->category?->name
                                    ?? '—' }}
                            </td>

                            <td>
                                <strong style="{{ $isLow ? 'color:var(--danger)' : '' }}">
                                    {{ number_format((float) $inv->quantity, 2) }}
                                </strong>
                            </td>

                            <td>{{ number_format((float) $minStock, 2) }}</td>

                            <td>
                                @if($isLow)
                                    <span class="badge badge-inactive">منخفض</span>
                                @else
                                    <span class="badge badge-active">جيد</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $showAllLocations ? 6 : 5 }}">
                                <div class="empty-state-sm">لا توجد بيانات مخزون.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($inventories instanceof \Illuminate\Contracts\Pagination\Paginator)
            <div class="inventory-pagination">
                {{ $inventories->withQueryString()->links() }}
            </div>
        @endif
    </section>
</div>

@endsection