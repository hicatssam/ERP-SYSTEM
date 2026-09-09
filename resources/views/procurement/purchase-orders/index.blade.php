@extends('layouts.app')
@section('title', 'أوامر الشراء')
@section('content')
    @include('procurement.partials.flash')
    <div class="page-actions">
        <div class="page-actions-title">أوامر الشراء</div>
        @can('purchase_orders.create')
            <a class="btn btn-gold" href="{{ route('purchase-orders.create') }}">إنشاء أمر شراء</a>
        @endcan
    </div>
    <div class="card" style="margin-bottom:1rem">
        <div class="card-body">
            <form method="GET" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:end">
                <div class="form-group"><label class="form-label">الحالة</label><select class="form-input" name="status">
                        <option value="">كل الحالات</option>
                        @foreach (['draft' => 'مسودة', 'submitted' => 'بانتظار الاعتماد', 'approved' => 'معتمد', 'partially_received' => 'مستلم جزئياً', 'received' => 'مستلم بالكامل', 'cancelled' => 'ملغى'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group"><label class="form-label">المورد</label><select class="form-input"
                        name="supplier_id">
                        <option value="">كل الموردين</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) request('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group"><label class="form-label">الموقع</label><select class="form-input"
                        name="location_id">
                        <option value="">كل المواقع المسموحة</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div><button class="btn btn-outline">تصفية</button>
            </form>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>رقم الأمر</th>
                    <th>المورد</th>
                    <th>الموقع</th>
                    <th>التاريخ</th>
                    <th>الإجمالي</th>
                    <th>الحالة</th>
                    <th>الإجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td><strong>{{ $order->purchase_order_number }}</strong><br><small>{{ $order->items_count }}
                                أصناف</small></td>
                        <td>{{ $order->supplier?->name }}</td>
                        <td>{{ $order->location?->name }}</td>
                        <td>{{ $order->order_date?->format('Y/m/d') }}</td>
                        <td>{{ number_format($order->grand_total, 2) }} {{ $order->currency?->displayName() }}</td>
                        <td>{{ $order->statusLabel() }}</td>
                        <td><a class="btn btn-ghost btn-sm" href="{{ route('purchase-orders.show', $order) }}">عرض</a></td>
                </tr>@empty<tr>
                        <td colspan="7">
                            <div class="empty-state-sm">لا توجد أوامر شراء.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:1rem">{{ $orders->links() }}</div>
@endsection
