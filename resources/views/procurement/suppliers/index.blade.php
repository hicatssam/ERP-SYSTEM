@extends('layouts.app')

@section('title', 'الموردون')

@section('content')
    @include('procurement.partials.flash')

    <div class="page-actions">
        <div class="page-actions-title">الموردون</div>
        @can('suppliers.create')
            <a class="btn btn-gold" href="{{ route('suppliers.create') }}">إضافة مورد</a>
        @endcan
    </div>

    <div class="card" style="margin-bottom:1rem">
        <div class="card-body">
            <form method="GET" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:end">
                <div class="form-group" style="min-width:220px;flex:1">
                    <label class="form-label">بحث</label>
                    <input class="form-input" name="search" value="{{ request('search') }}"
                        placeholder="اسم المورد أو الكود أو الهاتف">
                </div>
                <div class="form-group">
                    <label class="form-label">الحالة</label>
                    <select class="form-input" name="status">
                        <option value="">كل الحالات</option>
                        <option value="active" @selected(request('status') === 'active')>نشط</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>غير نشط</option>
                        <option value="blocked" @selected(request('status') === 'blocked')>موقوف</option>
                    </select>
                </div>
                <button class="btn btn-outline" type="submit">تصفية</button>
            </form>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>الكود</th>
                    <th>المورد</th>
                    <th>التواصل</th>
                    <th>العملة</th>
                    <th>الحالة</th>
                    <th>الأصناف</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($suppliers as $supplier)
                    <tr>
                        <td>{{ $supplier->supplier_code }}</td>
                        <td><strong>{{ $supplier->name }}</strong><br><small>{{ $supplier->company_name }}</small></td>
                        <td>{{ $supplier->phone ?: '—' }}<br><small>{{ $supplier->email }}</small></td>
                        <td>{{ $supplier->currency?->displayName() }}</td>
                        <td>{{ $supplier->statusLabel() }}</td>
                        <td>{{ $supplier->supplier_products_count ?? 0 }}</td>
                        <td>
                            <div class="actions"><a class="btn btn-ghost btn-sm"
                                    href="{{ route('suppliers.show', $supplier) }}">عرض</a>
                                @can('suppliers.update')
                                    <a class="btn btn-outline btn-sm" href="{{ route('suppliers.edit', $supplier) }}">تعديل</a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state-sm">لا يوجد موردون مطابقون.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1rem">{{ $suppliers->links() }}</div>
@endsection
