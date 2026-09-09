@extends('layouts.app')

@section('title', $recipe->name)
@section('page-title', 'تفاصيل الوصفة')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">{{ $recipe->name }} — V{{ $recipe->version }}</h1>
        <p class="page-subheading">
            {{ $recipe->code }} · {{ $recipe->product?->name_ar ?: $recipe->product?->name }}
            @if($recipe->productVariant)
                · {{ $recipe->productVariant->displayName() }}
            @endif
        </p>
    </div>
    <div class="page-header-actions">
        @can('recipes.manage')
            @if($recipe->isEditable())
                <a class="btn btn-outline" href="{{ route('production.recipes.edit', $recipe) }}">تعديل المسودة</a>
            @else
                <form method="POST" action="{{ route('production.recipes.new-version', $recipe) }}">
                    @csrf
                    <button class="btn btn-outline">إصدار جديد</button>
                </form>
            @endif
        @endcan
        @can('recipes.approve')
            @if($recipe->isEditable())
                <form method="POST" action="{{ route('production.recipes.approve', $recipe) }}">
                    @csrf
                    <button class="btn btn-gold">اعتماد الوصفة</button>
                </form>
            @endif
        @endcan
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1rem">
    <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ $recipe->status->label() }}</div><div class="stat-label">الحالة</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ number_format((float)$recipe->yield_quantity, 3) }}</div><div class="stat-label">ناتج الوصفة</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ $recipe->items->count() }}</div><div class="stat-label">عدد المكونات</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ $recipe->is_active ? 'الفعال' : 'غير فعال' }}</div><div class="stat-label">الإصدار الحالي</div></div></div>
</div>

@if($estimate)
<div class="card" style="margin-bottom:1rem">
    <div class="card-header"><span class="card-title">تكلفة المواد الحالية</span></div>
    <div class="card-body">
        <form method="GET" style="display:flex;gap:.75rem;align-items:end;margin-bottom:1rem">
            <div class="form-group">
                <label class="form-label">الموقع</label>
                <select name="location_id" class="form-input">
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected($selectedLocation?->id === $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-outline">تحديث التكلفة</button>
        </form>

        <div style="display:flex;gap:2rem;flex-wrap:wrap">
            <div><strong>{{ number_format($estimate['material_cost'], 4) }} ₪</strong><br><small>تكلفة مواد الوصفة</small></div>
            <div><strong>{{ number_format($estimate['unit_material_cost'], 4) }} ₪</strong><br><small>تكلفة المادة للوحدة الناتجة</small></div>
            <div><strong>{{ count($estimate['missing_costs']) }}</strong><br><small>مواد بدون تكلفة موثوقة</small></div>
        </div>

        @if($estimate['missing_costs'])
            <div class="alert alert-warning" style="margin-top:1rem">
                لا يتم اختلاق تكلفة عند غياب مصدر موثوق. بدون تكلفة: {{ implode('، ', $estimate['missing_costs']) }}
            </div>
        @endif
    </div>
</div>
@endif

<div class="card">
    <div class="card-header"><span class="card-title">مكونات الوصفة</span></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>#</th><th>المكون</th><th>الكمية الصافية</th><th>الهدر المتوقع</th><th>الكمية المخططة</th><th>المرحلة</th></tr></thead>
            <tbody>
            @foreach($recipe->items as $item)
                @php $gross = $item->grossQuantity(); @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->ingredient?->name_ar ?: $item->ingredient?->name }}</td>
                    <td>{{ number_format((float)$item->quantity, 3) }} {{ $item->unit_snapshot }}</td>
                    <td>{{ number_format((float)$item->expected_waste_percent, 3) }}%</td>
                    <td>{{ number_format($gross, 3) }} {{ $item->unit_snapshot }}</td>
                    <td>{{ $item->stage ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
