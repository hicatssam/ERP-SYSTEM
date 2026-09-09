@extends('layouts.app')
@section('title', 'الفئات')
@section('content')
<div class="page-actions">
    <div class="page-actions-title">فئات المنتجات</div>
    <div class="action-btns">
        @can('products.create')
        <a href="{{ route('categories.create') }}" class="btn btn-gold">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            إضافة فئة
        </a>
        @endcan
    </div>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th style="width:64px">الصورة</th><th>الاسم</th><th>الاسم بالعربي</th><th>الرابط</th><th>المنتجات</th><th>الترتيب</th><th>الحالة</th><th>الإجراءات</th></tr>
        </thead>
        <tbody>
        @forelse($categories as $cat)
            <tr>
                <td>
                    @if($cat->image)
                        <img src="{{ Storage::url($cat->image) }}" alt="{{ $cat->name }}" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid var(--border)">
                    @else
                        <div style="width:44px;height:44px;border-radius:8px;background:var(--surface);border:1px solid var(--border);display:flex;align-items:center;justify-content:center">
                            <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5" style="width:20px;height:20px"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                        </div>
                    @endif
                </td>
                <td>{{ $cat->name }}</td>
                <td>{{ $cat->name_ar ?? '—' }}</td>
                <td><code>{{ $cat->slug }}</code></td>
                <td>{{ $cat->products_count }}</td>
                <td>{{ $cat->sort_order ?? 0 }}</td>
                <td><span class="badge {{ $cat->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $cat->is_active ? 'نشطة' : 'معطلة' }}</span></td>
                <td>
                    <div class="actions">
                        <a href="{{ route('categories.edit', $cat) }}" class="btn btn-outline btn-sm">تعديل</a>
                        <form action="{{ route('categories.toggle-status', $cat) }}" method="POST" style="display:inline">@csrf @method('POST')
                            <button class="btn btn-ghost btn-sm">{{ $cat->is_active ? 'تعطيل' : 'تفعيل' }}</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty-state-sm">لا توجد فئات.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
