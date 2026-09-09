@extends('layouts.app')
@section('title','تصنيفات المصروفات')
@section('page-title','تصنيفات المصروفات')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">تصنيفات المصروفات</h1>
        <p class="page-subheading">تصنيف موحد للتقارير والربحية. التصنيفات النظامية محمية من الإيقاف.</p>
    </div>
    <a class="btn btn-ghost" href="{{ route('costing.expenses.index') }}">رجوع</a>
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="card-header"><span class="card-title">إضافة تصنيف</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('costing.expense-categories.store') }}" class="filter-grid">
            @csrf
            <div class="filter-group">
                <label class="filter-label">الكود</label>
                <input class="form-input" name="code" required maxlength="70" placeholder="TRAVEL">
            </div>
            <div class="filter-group">
                <label class="filter-label">الاسم</label>
                <input class="form-input" name="name" required maxlength="140">
            </div>
            <div class="filter-group">
                <label class="filter-label">التصنيف المحاسبي</label>
                <select class="form-input" name="classification" required>
                    @foreach($types as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">الترتيب</label>
                <input class="form-input" type="number" min="0" max="9999" name="sort_order" value="0">
            </div>
            <div class="filter-group" style="justify-content:flex-end">
                <label class="filter-label">&nbsp;</label>
                <button class="btn btn-gold">إضافة</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap" style="border:0;border-radius:0">
        <table class="data-table">
            <thead>
            <tr>
                <th>الكود</th>
                <th>الاسم</th>
                <th>التصنيف</th>
                <th>الترتيب</th>
                <th>الحالة</th>
                <th>الإجراء</th>
            </tr>
            </thead>
            <tbody>
            @foreach($categories as $category)
                <tr>
                    <td><code>{{ $category->code }}</code></td>
                    <td colspan="3">
                        <form method="POST" action="{{ route('costing.expense-categories.update',$category) }}" style="display:grid;grid-template-columns:2fr 1.5fr .7fr auto;gap:.5rem;align-items:center">
                            @csrf
                            @method('PUT')
                            <input class="form-input" name="name" value="{{ $category->name }}" required maxlength="140">
                            <select class="form-input" name="classification" required>
                                @foreach($types as $type)
                                    <option value="{{ $type->value }}" @selected(($category->classification?->value ?? $category->classification) === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                            <input class="form-input" type="number" name="sort_order" min="0" max="9999" value="{{ $category->sort_order }}">
                            <button class="btn btn-outline btn-sm">حفظ</button>
                        </form>
                    </td>
                    <td>
                        <span class="badge {{ $category->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $category->is_active ? 'فعال' : 'متوقف' }}</span>
                    </td>
                    <td>
                        @if($category->is_system)
                            <span style="color:var(--text-muted);font-size:.75rem">نظامي محمي</span>
                        @else
                            <form method="POST" action="{{ route('costing.expense-categories.toggle',$category) }}">
                                @csrf
                                <button class="btn btn-ghost btn-sm">{{ $category->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
