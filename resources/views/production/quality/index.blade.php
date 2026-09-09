@extends('layouts.app')

@section('title', 'مراقبة جودة الإنتاج')
@section('page-title', 'مراقبة جودة الإنتاج')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">دفعات بانتظار الجودة</h1>
        <p class="page-subheading">الناتج لا يدخل المخزون قبل قرار الجودة عندما تكون الوحدة مفعلة.</p>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:.75rem;align-items:end">
            <div class="form-group">
                <label class="form-label">الموقع</label>
                <select name="location_id" class="form-input">
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected($selectedLocation->id === $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-outline">تطبيق</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>الدفعة</th><th>المنتج</th><th>الناتج الفعلي</th><th>منذ</th><th></th></tr></thead>
            <tbody>
            @forelse($batches as $batch)
                <tr>
                    <td>{{ $batch->batch_number }}</td>
                    <td>{{ $batch->product?->name_ar ?: $batch->product?->name }}</td>
                    <td>{{ number_format((float)$batch->actual_output_quantity, 3) }}</td>
                    <td>{{ $batch->submitted_for_quality_at?->diffForHumans() }}</td>
                    <td><a class="btn btn-gold btn-sm" href="{{ route('production.batches.show', $batch) }}">فحص</a></td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state"><h3>لا توجد دفعات بانتظار الفحص</h3></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:1rem">{{ $batches->links() }}</div>
</div>
@endsection
