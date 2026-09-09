@extends('layouts.app')

@section('title', 'دفعات الإنتاج')
@section('page-title', 'دفعات الإنتاج')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">دفعات الإنتاج</h1>
        <p class="page-subheading">تخطيط، حجز، صرف، تنفيذ، جودة وتتبع تكلفة المواد.</p>
    </div>
    @can('production.create')
        <a class="btn btn-gold" href="{{ route('production.batches.create', ['location_id' => $selectedLocation->id]) }}">دفعة جديدة</a>
    @endcan
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="card-body">
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label class="filter-label">الموقع</label>
                <select name="location_id" class="form-input">
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected($selectedLocation->id === $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">الحالة</label>
                <select name="status" class="form-input">
                    <option value="">الكل</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group" style="align-self:end"><button class="btn btn-outline">تطبيق</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>الدفعة</th><th>المنتج</th><th>الوصفة</th><th>مخطط</th><th>فعلي</th><th>الحالة</th><th>التاريخ</th><th></th></tr></thead>
            <tbody>
            @forelse($batches as $batch)
                <tr>
                    <td><strong>{{ $batch->batch_number }}</strong></td>
                    <td>{{ $batch->product?->name_ar ?: $batch->product?->name }}</td>
                    <td>{{ $batch->recipe?->name }} V{{ $batch->recipe_version }}</td>
                    <td>{{ number_format((float)$batch->planned_output_quantity, 3) }}</td>
                    <td>{{ $batch->actual_output_quantity !== null ? number_format((float)$batch->actual_output_quantity, 3) : '—' }}</td>
                    <td><span class="badge badge-pending">{{ $batch->status->label() }}</span></td>
                    <td>{{ $batch->planned_date?->format('d/m/Y') ?: $batch->created_at->format('d/m/Y') }}</td>
                    <td><a class="btn btn-ghost btn-sm" href="{{ route('production.batches.show', $batch) }}">فتح</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty-state"><h3>لا توجد دفعات إنتاج</h3></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:1rem">{{ $batches->links() }}</div>
</div>
@endsection
