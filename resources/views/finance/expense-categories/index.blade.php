@extends('layouts.app')
@section('title','تصنيفات المصروفات')
@section('page-title','تصنيفات المصروفات')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">تصنيفات المصروفات</h1>
        <p class="page-subheading">تصنيف موحد للتقارير والربحية. التصنيفات النظامية محمية من الإيقاف.</p>
    </div>
    <div style="display:flex;gap:.5rem"><button type="button" class="btn btn-gold" data-open-dialog="category-add-dialog">إضافة تصنيف</button><a class="btn btn-ghost" href="{{ route('costing.expenses.index') }}">رجوع</a></div>
</div>

<x-action-dialog id="category-add-dialog" title="إضافة تصنيف مصروف">
        <form method="POST" action="{{ route('costing.expense-categories.store') }}" class="filter-grid">
            @csrf
            <input type="hidden" name="_modal" value="category-add-dialog">
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
                <button class="btn btn-gold">حفظ التصنيف</button>
            </div>
        </form>
</x-action-dialog>

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
                    <td>{{ $category->name }}</td>
                    <td>{{ $category->classification?->label() ?? '—' }}</td>
                    <td>{{ $category->sort_order }}</td>
                    <td>
                        <span class="badge {{ $category->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $category->is_active ? 'فعال' : 'متوقف' }}</span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-outline btn-sm" data-open-dialog="category-edit-{{ $category->id }}">تعديل</button>
                        @if($category->is_system)
                            <span style="color:var(--text-muted);font-size:.75rem">نظامي محمي</span>
                        @else
                            <form method="POST" action="{{ route('costing.expense-categories.toggle',$category) }}" style="display:inline-block" onsubmit="return confirm('تأكيد تغيير حالة التصنيف؟')">
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
@foreach($categories as $category)
    <x-action-dialog :id="'category-edit-'.$category->id" :title="'تعديل تصنيف: '.$category->code">
        <form method="POST" action="{{ route('costing.expense-categories.update', $category) }}" style="display:grid;gap:.8rem">
            @csrf @method('PUT')
            <input type="hidden" name="_modal" value="category-edit-{{ $category->id }}">
            <div><label class="form-label">الاسم</label><input class="form-input" name="name" value="{{ old('_modal') === 'category-edit-'.$category->id ? old('name', $category->name) : $category->name }}" required maxlength="140"></div>
            <div><label class="form-label">التصنيف المحاسبي</label><select class="form-input" name="classification" required>
                @foreach($types as $type)<option value="{{ $type->value }}" @selected((old('_modal') === 'category-edit-'.$category->id ? old('classification') : ($category->classification?->value ?? $category->classification)) === $type->value)>{{ $type->label() }}</option>@endforeach
            </select></div>
            <div><label class="form-label">الترتيب</label><input class="form-input" type="number" name="sort_order" min="0" max="9999" value="{{ old('_modal') === 'category-edit-'.$category->id ? old('sort_order', $category->sort_order) : $category->sort_order }}"></div>
            <div style="display:flex;gap:.5rem;justify-content:flex-end"><button type="button" class="btn btn-outline" data-close-dialog>إلغاء</button><button class="btn btn-gold">حفظ</button></div>
        </form>
    </x-action-dialog>
@endforeach
@endsection
