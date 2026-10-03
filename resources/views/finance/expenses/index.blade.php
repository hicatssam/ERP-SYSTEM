@extends('layouts.app')
@section('title', 'المصروفات')
@section('page-title', 'المصروفات')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">المصروفات</h1>
        <p class="page-subheading">مسودة ← اعتماد مستقل ← ترحيل مالي ← عكس محاسبي عند الحاجة</p>
    </div>
    <div class="page-header-actions">
        @can('expense_categories.manage')
            <a class="btn btn-outline btn-sm" href="{{ route('costing.expense-categories.index') }}">تصنيفات المصروفات</a>
        @endcan
        @can('expenses.create')
            <button type="button" class="btn btn-gold" data-open-dialog="expense-create-dialog">+ مصروف جديد</button>
        @endcan
    </div>
</div>

@can('expenses.create')
<x-action-dialog id="expense-create-dialog" title="إنشاء مسودة مصروف" description="راجع الموقع والتصنيف وطريقة الدفع قبل حفظ المسودة.">
    <form method="POST" action="{{ route('costing.expenses.store') }}">
        @csrf
        <input type="hidden" name="_modal" value="expense-create-dialog">
        @include('finance.expenses.fields', ['expense' => new \App\Models\Expense()])
        <div class="form-actions" style="display:flex;justify-content:flex-end;gap:.75rem">
            <button type="button" class="btn btn-ghost" data-close-dialog>إلغاء</button>
            <button type="submit" class="btn btn-gold">حفظ المسودة</button>
        </div>
    </form>
</x-action-dialog>
@endcan

<div class="card" style="margin-bottom:1rem">
    <div class="card-body">
        <form method="GET" class="filter-grid">
            @if($locations->isNotEmpty())
                <div class="filter-group">
                    <label class="filter-label">الموقع</label>
                    <select name="location_id" class="form-input">
                        <option value="">كل المواقع</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((int)$locationId === (int)$location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="filter-group">
                <label class="filter-label">الحالة</label>
                <select name="status" class="form-input">
                    <option value="">كل الحالات</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">التصنيف</label>
                <select name="category_id" class="form-input">
                    <option value="">كل التصنيفات</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((int)request('category_id') === (int)$category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group"><label class="filter-label">من</label><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input"></div>
            <div class="filter-group"><label class="filter-label">إلى</label><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input"></div>
            <div class="filter-group" style="justify-content:flex-end"><label class="filter-label">&nbsp;</label><button class="btn btn-gold">تطبيق</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">سجل المصروفات</span><span>{{ number_format($expenses->total()) }} سجل</span></div>
    <div class="table-wrap" style="border:0;border-radius:0">
        <table class="data-table">
            <thead><tr><th>الرقم</th><th>التاريخ</th><th>الموقع</th><th>التصنيف</th><th>الوصف</th><th>القيمة</th><th>الحالة</th><th>المنشئ</th><th></th></tr></thead>
            <tbody>
            @forelse($expenses as $expense)
                <tr>
                    <td><strong>{{ $expense->expense_number }}</strong></td>
                    <td>{{ $expense->expense_date?->format('d/m/Y') }}</td>
                    <td>{{ $expense->location?->name ?? '—' }}</td>
                    <td>{{ $expense->category?->name ?? '—' }}</td>
                    <td style="max-width:280px">{{ \Illuminate\Support\Str::limit($expense->description, 65) }}</td>
                    <td><strong>₪{{ number_format((float)$expense->amount, 2) }}</strong></td>
                    <td><span class="badge {{ $expense->status?->badgeClass() }}">{{ $expense->status?->label() }}</span></td>
                    <td>{{ $expense->creator?->display_name ?? 'غير مسجل' }}</td>
                    <td><a class="btn btn-ghost btn-sm" href="{{ route('costing.expenses.show', $expense) }}">فتح</a></td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="empty-state"><h3>لا توجد مصروفات</h3></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:1rem">{{ $expenses->links() }}</div>
</div>
@endsection
