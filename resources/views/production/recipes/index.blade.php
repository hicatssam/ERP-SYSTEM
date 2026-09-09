@extends('layouts.app')

@section('title', 'الوصفات')
@section('page-title', 'الوصفات')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">الوصفات وBOM</h1>
        <p class="page-subheading">إصدارات معتمدة للمنتجات مع كميات معيارية وهدر متوقع.</p>
    </div>
    @can('recipes.manage')
        <a href="{{ route('production.recipes.create') }}" class="btn btn-gold">إضافة وصفة</a>
    @endcan
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="card-body">
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label class="filter-label">بحث</label>
                <input class="form-input" name="q" value="{{ request('q') }}" placeholder="المنتج، الوصفة أو الكود">
            </div>
            <div class="filter-group">
                <label class="filter-label">الحالة</label>
                <select class="form-input" name="status">
                    <option value="">الكل</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group" style="align-self:end">
                <button class="btn btn-outline">تطبيق</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>الكود</th>
                <th>المنتج الناتج</th>
                <th>الإصدار</th>
                <th>ناتج الوصفة</th>
                <th>الحالة</th>
                <th>الإصدار الفعال</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($recipes as $recipe)
                <tr>
                    <td><code>{{ $recipe->code }}</code></td>
                    <td>
                        {{ $recipe->product?->name_ar ?: $recipe->product?->name }}
                        @if($recipe->productVariant)
                            <small style="display:block;color:var(--text-muted);margin-top:.2rem">
                                الحجم/المتغير: {{ $recipe->productVariant->displayName() }}
                            </small>
                        @endif
                    </td>
                    <td>V{{ $recipe->version }}</td>
                    <td>{{ number_format((float)$recipe->yield_quantity, 3) }}</td>
                    <td>
                        <span class="badge {{ $recipe->status->value === 'approved' ? 'badge-active' : ($recipe->status->value === 'archived' ? 'badge-inactive' : 'badge-pending') }}">
                            {{ $recipe->status->label() }}
                        </span>
                    </td>
                    <td>{{ $recipe->is_active ? 'نعم' : 'لا' }}</td>
                    <td>
                        <a class="btn btn-ghost btn-sm" href="{{ route('production.recipes.show', $recipe) }}">فتح</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><h3>لا توجد وصفات</h3></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:1rem">{{ $recipes->links() }}</div>
</div>
@endsection
