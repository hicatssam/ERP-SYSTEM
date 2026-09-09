@extends('layouts.app')
@section('title', 'تقارير المشتريات')
@section('content')
    <div class="page-actions">
        <div class="page-actions-title">تقارير المشتريات والموردين</div><a class="btn btn-ghost"
            href="{{ route('procurement.dashboard') }}">لوحة المشتريات</a>
    </div>
    <div class="card" style="margin-bottom:1rem">
        <div class="card-body">
            <form method="GET" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:end">
                <div class="form-group"><label class="form-label">من</label><input class="form-input" type="date"
                        name="from" value="{{ $from->format('Y-m-d') }}"></div>
                <div class="form-group"><label class="form-label">إلى</label><input class="form-input" type="date"
                        name="to" value="{{ $to->format('Y-m-d') }}"></div>
                <div class="form-group"><label class="form-label">الموقع</label><select class="form-input"
                        name="location_id">
                        <option value="">كل المواقع المسموحة</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div><button class="btn btn-outline">تحديث</button>
            </form>
        </div>
    </div>
    <div class="dashboard-row">
        <div class="card">
            <div class="card-header"><span class="card-title">الاستلام حسب الموقع</span></div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>الموقع</th>
                                <th>الكمية المقبولة</th>
                                <th>القيمة الأساسية</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($receiptByLocation as $row)
                                <tr>
                                    <td>{{ $row->name }}</td>
                                    <td>{{ number_format($row->accepted_quantity, 3) }}</td>
                                    <td>{{ number_format($row->base_total, 2) }}</td>
                            </tr>@empty<tr>
                                    <td colspan="3">لا توجد بيانات.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">المشتريات حسب المورد</span></div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>المورد</th>
                                <th>القيمة الأساسية</th>
                                <th>المتبقي بالعملة الأساسية</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($purchasesBySupplier as $row)
                                <tr>
                                    <td>{{ $row->name }}</td>
                                    <td>{{ number_format($row->base_total, 2) }}</td>
                                    <td>{{ number_format($row->base_outstanding_total, 2) }}</td>
                            </tr>@empty<tr>
                                    <td colspan="3">لا توجد بيانات.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="dashboard-row" style="margin-top:1rem">
        <div class="card">
            <div class="card-header"><span class="card-title">أعمار الذمم</span></div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>الفاتورة</th>
                                <th>المورد</th>
                                <th>المتبقي</th>
                                <th>متأخرة</th>
                                <th>الشريحة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($aging as $row)
                                <tr>
                                    <td><a
                                            href="{{ route('supplier-invoices.show', $row['invoice']) }}">{{ $row['invoice']->invoice_number }}</a>
                                    </td>
                                    <td>{{ $row['invoice']->supplier?->name }}</td>
                                    <td>{{ number_format($row['invoice']->remaining_amount, 2) }}</td>
                                    <td>{{ $row['days_overdue'] }} يوم</td>
                                    <td>{{ $row['bucket'] }}</td>
                            </tr>@empty<tr>
                                    <td colspan="5">لا توجد ذمم مستحقة.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">أرصدة الموردين</span></div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>المورد</th>
                                <th>العملة</th>
                                <th>الرصيد المستحق</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($supplierBalances as $row)
                                <tr>
                                    <td><a
                                            href="{{ route('suppliers.statement', $row['supplier']) }}">{{ $row['supplier']->name }}</a>
                                    </td>
                                    <td>{{ $row['supplier']->currency?->displayName() }}</td>
                                    <td>{{ number_format($row['summary']['outstanding_balance'], 2) }}</td>
                            </tr>@empty<tr>
                                    <td colspan="3">لا توجد أرصدة.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="card" style="margin-top:1rem">
        <div class="card-header"><span class="card-title">المرتجعات ضمن الفترة</span></div>
        <div class="card-body">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>المرتجع</th>
                            <th>المورد</th>
                            <th>الموقع</th>
                            <th>القيمة</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($returns as $return)
                            <tr>
                                <td><a
                                        href="{{ route('purchase-returns.show', $return) }}">{{ $return->return_number }}</a>
                                </td>
                                <td>{{ $return->supplier?->name }}</td>
                                <td>{{ $return->location?->name }}</td>
                                <td>{{ number_format($return->grand_total, 2) }} {{ $return->currency?->displayName() }}</td>
                                <td>{{ $return->returned_at?->format('Y/m/d') }}</td>
                        </tr>@empty<tr>
                                <td colspan="5">لا توجد مرتجعات.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
