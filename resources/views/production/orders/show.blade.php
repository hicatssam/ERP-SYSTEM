@extends('layouts.app')

@section('title', $order->production_number)
@section('page-title', 'تفاصيل أمر الإنتاج')

@section('content')
@php
    $status=$order->statusValue();
    $badge=match($status){'completed'=>'badge-active','cancelled'=>'badge-inactive','in_progress'=>'badge-pending','released'=>'badge-pending',default=>'badge-pending'};
@endphp

<div class="page-header">
    <div>
        <h1 class="page-heading">{{ $order->production_number }}</h1>
        <p class="page-subheading">{{ $order->product?->name_ar ?: $order->product?->name }} — {{ $order->location?->name }}</p>
    </div>
    <div class="page-header-actions">
        <span class="badge {{ $badge }}">{{ $order->status->label() }}</span>

        @if($status === 'draft')
            @can('production.release')
                <form method="POST" action="{{ route('production.orders.release',$order) }}" style="display:inline">@csrf
                    <button class="btn btn-gold btn-sm" type="submit" onclick="return confirm('اعتماد الأمر وتجميد خطة المواد؟')">اعتماد للإنتاج</button>
                </form>
            @endcan
        @endif

        @if($status === 'released')
            @can('production.start')
                <form method="POST" action="{{ route('production.orders.start',$order) }}" style="display:inline">@csrf
                    <button class="btn btn-gold btn-sm" type="submit" onclick="return confirm('سيتم فحص وصرف المواد من المخزون. بدء الإنتاج؟')">بدء الإنتاج</button>
                </form>
            @endcan
        @endif

        @if(in_array($status,['draft','released'],true))
            @can('production.cancel')
                <form method="POST" action="{{ route('production.orders.cancel',$order) }}" style="display:inline">@csrf
                    <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--error)" onclick="return confirm('إلغاء أمر الإنتاج؟')">إلغاء</button>
                </form>
            @endcan
        @endif

        <a class="btn btn-ghost btn-sm" href="{{ route('production.orders.index') }}">رجوع</a>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:1rem">
    <div class="stat-card stat-gold"><div class="stat-info"><div class="stat-value">{{ number_format((float)$order->planned_output_quantity,3) }}</div><div class="stat-label">الناتج المخطط</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ $order->actual_output_quantity===null?'—':number_format((float)$order->actual_output_quantity,3) }}</div><div class="stat-label">الناتج الفعلي</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ number_format((float)$order->estimated_material_cost,2) }}</div><div class="stat-label">مواد معيارية</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ number_format((float)$order->total_cost,2) }}</div><div class="stat-label">إجمالي التكلفة</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-value">{{ number_format((float)$order->unit_cost,4) }}</div><div class="stat-label">تكلفة الوحدة</div></div></div>
</div>

@if(!$order->cost_is_complete && in_array($status,['released','in_progress','completed'],true))
    <div class="alert alert-warning" style="margin-bottom:1rem">
        تكلفة هذا الأمر غير مكتملة لأن أحد مكونات الوصفة لم يكن له سعر معياري عند الاعتماد.
        عمليات المخزون صحيحة، لكن التكلفة المعروضة ليست تكلفة مالية نهائية.
    </div>
@endif

<div class="dashboard-row">
    <div class="card">
        <div class="card-header"><span class="card-title">بيانات الأمر</span></div>
        <div class="card-body">
            <table class="data-table">
                <tbody>
                    <tr><td>الوصفة</td><td><a href="{{ route('production.recipes.show',$order->recipe) }}">{{ $order->recipe?->name }} v{{ $order->recipe_version }}</a></td></tr>
                    <tr><td>الموقع</td><td>{{ $order->location?->name }}</td></tr>
                    <tr><td>وحدة الناتج</td><td>{{ $order->outputUnit?->displayName() ?? $order->product?->unitDefinition?->displayName() ?? $order->product?->unit }}</td></tr>
                    <tr><td>وقت التخطيط</td><td>{{ $order->planned_at?->format('Y/m/d H:i') ?? '—' }}</td></tr>
                    <tr><td>وقت الاعتماد</td><td>{{ $order->released_at?->format('Y/m/d H:i') ?? '—' }}</td></tr>
                    <tr><td>وقت البدء</td><td>{{ $order->started_at?->format('Y/m/d H:i') ?? '—' }}</td></tr>
                    <tr><td>وقت الإكمال</td><td>{{ $order->completed_at?->format('Y/m/d H:i') ?? '—' }}</td></tr>
                    @if($order->notes)<tr><td>ملاحظات</td><td>{{ $order->notes }}</td></tr>@endif
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">التكلفة والانحراف</span></div>
        <div class="card-body">
            <table class="data-table">
                <tbody>
                    <tr><td>المواد المعيارية</td><td>{{ number_format((float)$order->estimated_material_cost,2) }}</td></tr>
                    <tr><td>المواد الفعلية</td><td>{{ number_format((float)$order->actual_material_cost,2) }}</td></tr>
                    <tr><td>العمل</td><td>{{ number_format((float)$order->labor_cost,2) }}</td></tr>
                    <tr><td>Overhead</td><td>{{ number_format((float)$order->overhead_cost,2) }} ({{ number_format((float)$order->overhead_percent_snapshot,2) }}%)</td></tr>
                    <tr><td>انحراف الناتج</td><td>{{ $order->output_variance_quantity===null?'—':number_format((float)$order->output_variance_quantity,3).' ('.number_format((float)$order->output_variance_percent,2).'%)' }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <div class="card-header"><span class="card-title">المواد / الاستهلاك</span></div>

    @if($status === 'draft')
        <div class="card-body">
            <div class="empty-state-sm">سيتم إنشاء Snapshot للمواد عند اعتماد الأمر.</div>
        </div>
    @else
        <div class="table-wrap" style="border:none;border-radius:0">
            <table class="data-table">
                <thead><tr><th>المكوّن</th><th>مخطط</th><th>مصروف</th><th>فعلي</th><th>مرتجع</th><th>هدر</th><th>تكلفة فعلية</th></tr></thead>
                <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->product?->name_ar ?: $item->product?->name }}</td>
                        <td>{{ number_format((float)$item->planned_quantity,3) }}</td>
                        <td>{{ number_format((float)$item->issued_quantity,3) }}</td>
                        <td>{{ $item->actual_quantity===null?'—':number_format((float)$item->actual_quantity,3) }}</td>
                        <td>{{ number_format((float)$item->returned_quantity,3) }}</td>
                        <td>{{ number_format((float)$item->waste_quantity,3) }}</td>
                        <td>{{ number_format((float)$item->actual_cost,2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@if($status === 'in_progress')
    @can('production.complete')
        <div class="card" style="margin-top:1rem">
            <div class="card-header"><span class="card-title">إكمال وتسوية الإنتاج</span></div>
            <div class="card-body">
                <div style="padding:.75rem;border-radius:8px;background:rgba(201,133,22,.08);font-size:.8rem;margin-bottom:1rem">
                    أدخل الاستهلاك الفعلي. إذا كان أقل من المصروف سيعاد الفرق للمخزون، وإذا كان أعلى سيُفحص المخزون ويصرف الفرق.
                    لا يمكن إلغاء الأمر بعد بدء الصرف لأن ذلك قد يعطي عكسًا مخزنيًا غير مطابق للواقع.
                </div>

                <form method="POST" action="{{ route('production.orders.complete',$order) }}">
                    @csrf

                    <div class="form-group" style="max-width:300px;margin-bottom:1rem">
                        <label class="form-label">الناتج الفعلي *</label>
                        <input class="form-input" type="number" step=".001" min=".001" name="actual_output_quantity" value="{{ old('actual_output_quantity',$order->planned_output_quantity) }}" required>
                    </div>

                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>المكوّن</th><th>المصروف</th><th>الاستهلاك الفعلي</th><th>الهدر ضمن الفعلي</th></tr></thead>
                            <tbody>
                            @foreach($order->items as $index=>$item)
                                <tr>
                                    <td>
                                        {{ $item->product?->name_ar ?: $item->product?->name }}
                                        <input type="hidden" name="materials[{{ $index }}][item_id]" value="{{ $item->id }}">
                                    </td>
                                    <td>{{ number_format((float)$item->issued_quantity,3) }}</td>
                                    <td>
                                        <input class="form-input" style="min-width:120px" type="number" step=".001" min="0" name="materials[{{ $index }}][actual_quantity]" value="{{ old("materials.$index.actual_quantity",$item->issued_quantity) }}" required>
                                    </td>
                                    <td>
                                        <input class="form-input" style="min-width:120px" type="number" step=".001" min="0" name="materials[{{ $index }}][waste_quantity]" value="{{ old("materials.$index.waste_quantity",0) }}">
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="display:flex;justify-content:flex-end;margin-top:1rem">
                        <button class="btn btn-gold" type="submit" onclick="return confirm('إكمال الأمر وترحيل التسوية والناتج للمخزون؟')">إكمال وترحيل الإنتاج</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endif
@endsection
