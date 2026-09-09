@extends('layouts.app')

@section('title', 'صلاحية المخزون')

@section('content')
<style>
    .expiry-page{max-width:1400px;margin:0 auto}
    .expiry-header{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1rem}
    .expiry-cards{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.8rem;margin-bottom:1rem}
    .expiry-card{display:block;padding:1rem;border:1px solid var(--border);border-radius:14px;background:var(--surface);color:var(--text);text-decoration:none}
    .expiry-card small{display:block;color:var(--text-muted);margin-bottom:.35rem}
    .expiry-card strong{font-size:1.35rem}
    .expiry-filters{display:grid;grid-template-columns:2fr repeat(3,1fr) auto;gap:.7rem;align-items:end;padding:1rem;border:1px solid var(--border);border-radius:14px;background:var(--surface);margin-bottom:1rem}
    .expiry-table-card{border:1px solid var(--border);border-radius:14px;overflow:hidden;background:var(--surface)}
    .expiry-table{width:100%;border-collapse:collapse}
    .expiry-table th,.expiry-table td{padding:.8rem;border-bottom:1px solid var(--border);text-align:right;vertical-align:middle}
    .expiry-table th{font-size:.75rem;color:var(--text-muted);background:color-mix(in srgb,var(--surface) 92%,var(--background))}
    .expiry-badge{display:inline-flex;padding:.3rem .55rem;border-radius:999px;font-size:.7rem;font-weight:800}
    .expiry-badge.expired{background:#fee2e2;color:#991b1b}
    .expiry-badge.critical{background:#fff1f2;color:#be123c}
    .expiry-badge.warning{background:#fff7ed;color:#c2410c}
    .expiry-badge.soon{background:#fefce8;color:#a16207}
    .expiry-badge.safe{background:#ecfdf5;color:#047857}
    @media(max-width:1050px){.expiry-cards{grid-template-columns:repeat(2,1fr)}.expiry-filters{grid-template-columns:1fr 1fr}.expiry-table-card{overflow:auto}}
    @media(max-width:650px){.expiry-header{flex-direction:column}.expiry-cards,.expiry-filters{grid-template-columns:1fr}}
</style>

<div class="expiry-page">
    <div class="expiry-header">
        <div>
            <h1 class="page-heading">صلاحية المخزون</h1>
            <p class="page-subheading">متابعة الدفعات وتواريخ الصلاحية والكميات المتبقية حسب الموقع.</p>
        </div>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a href="{{ route('inventory.expiry.print', request()->query()) }}" target="_blank" class="btn btn-outline">طباعة التقرير</a>
            @can('inventory.expiry-alerts.run')
                <form method="POST" action="{{ route('inventory.expiry.scan') }}">
                    @csrf
                    <button class="btn btn-gold" type="submit">فحص وإرسال التنبيهات</button>
                </form>
            @endcan
            <a href="{{ url('/inventory') }}" class="btn btn-outline">العودة للمخزون</a>
        </div>
    </div>

    <div class="expiry-cards">
        <a class="expiry-card" href="{{ route('inventory.expiry.index', ['status' => 'expired']) }}">
            <small>منتهي الصلاحية</small>
            <strong>{{ number_format($stats['expired']) }}</strong>
        </a>

        <a class="expiry-card" href="{{ route('inventory.expiry.index', ['status' => '7']) }}">
            <small>خلال 7 أيام</small>
            <strong>{{ number_format($stats['days_7']) }}</strong>
        </a>

        <a class="expiry-card" href="{{ route('inventory.expiry.index', ['status' => '30']) }}">
            <small>خلال 30 يوم</small>
            <strong>{{ number_format($stats['days_30']) }}</strong>
        </a>

        <a class="expiry-card" href="{{ route('inventory.expiry.index', ['status' => '60']) }}">
            <small>خلال 60 يوم</small>
            <strong>{{ number_format($stats['days_60']) }}</strong>
        </a>

        <a class="expiry-card" href="{{ route('inventory.expiry.index') }}">
            <small>كل الدفعات ذات الصلاحية</small>
            <strong>{{ number_format($stats['all']) }}</strong>
        </a>
    </div>

    <form method="GET" action="{{ route('inventory.expiry.index') }}" class="expiry-filters">
        <div class="form-group">
            <label class="form-label">بحث</label>
            <input class="form-input" name="q" value="{{ $search }}" placeholder="اسم المنتج أو رقم Batch">
        </div>

        <div class="form-group">
            <label class="form-label">الحالة</label>
            <select class="form-input" name="status">
                <option value="">الكل</option>
                <option value="expired" @selected($status === 'expired')>منتهي</option>
                <option value="7" @selected($status === '7')>خلال 7 أيام</option>
                <option value="30" @selected($status === '30')>خلال 30 يوم</option>
                <option value="60" @selected($status === '60')>خلال 60 يوم</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">الموقع</label>
            <select class="form-input" name="location_id">
                <option value="">كل المواقع</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected((int)$locationId === (int)$location->id)>
                        {{ $location->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">المنتج</label>
            <select class="form-input" name="product_id">
                <option value="">كل المنتجات</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" @selected((int)$productId === (int)$product->id)>
                        {{ $product->name_ar ?: $product->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <button class="btn btn-gold" type="submit">تصفية</button>
    </form>

    <div class="expiry-table-card">
        <table class="expiry-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>المنتج</th>
                    <th>Batch</th>
                    <th>الموقع</th>
                    <th>تاريخ الإنتاج</th>
                    <th>تاريخ الصلاحية</th>
                    <th>المتبقي</th>
                    <th>الأيام</th>
                    <th>الحالة</th>
                </tr>
            </thead>

            <tbody>
                @forelse($batches as $batch)
                    <tr>
                        <td>{{ $batch->id }}</td>
                        <td><strong>{{ $batch->product_name ?? ('#'.$batch->product_id) }}</strong></td>
                        <td dir="ltr">{{ $batch->batch_number ?: '—' }}</td>
                        <td>{{ $batch->location_name ?? '—' }}</td>
                        <td dir="ltr">{{ $batch->manufacturing_date ?: '—' }}</td>
                        <td dir="ltr"><strong>{{ $batch->expiry_date }}</strong></td>
                        <td>{{ number_format((float)$batch->available_quantity, 3) }}</td>
                        <td>
                            @if($batch->days_left < 0)
                                منتهي منذ {{ abs($batch->days_left) }} يوم
                            @elseif($batch->days_left === 0)
                                اليوم
                            @else
                                {{ $batch->days_left }} يوم
                            @endif
                        </td>
                        <td>
                            <span class="expiry-badge {{ $batch->expiry_status }}">
                                @switch($batch->expiry_status)
                                    @case('expired') منتهي @break
                                    @case('critical') حرج @break
                                    @case('warning') قريب @break
                                    @case('soon') تنبيه مبكر @break
                                    @default آمن
                                @endswitch
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:2rem;color:var(--text-muted)">
                            لا توجد دفعات مطابقة.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1rem">
        {{ $batches->links() }}
    </div>
</div>
@endsection
