@extends('layouts.app')
@section('title','مهمة التوصيل')

@section('content')
@include('growth._styles')

@php
    $nextStatuses = collect($task->status->allowedTransitions())
        ->reject(fn ($status) => $status === \App\Enums\DeliveryStatus::Assigned);

    if (auth()->user()->hasRole('Delivery Driver')) {
        $nextStatuses = $nextStatuses
            ->reject(fn ($status) => $status === \App\Enums\DeliveryStatus::Cancelled);
    }
@endphp

<div class="page-header">
    <div>
        <h1 class="page-heading">
            توصيل {{ $task->order?->order_number ?? '#'.$task->order_id }}
        </h1>
        <p class="page-subheading">
            <a href="{{ route('delivery.tasks.index') }}">التوصيل</a> ‹ #{{ $task->id }}
        </p>
    </div>
    <span class="growth-chip delivery-status">{{ $task->status->label() }}</span>
</div>

<div class="growth-grid">
    <div class="growth-stat">
        <small>العميل/المستلم</small>
        <strong>{{ $task->recipient_name ?? $task->customer?->name ?? '—' }}</strong>
        <div>{{ $task->recipient_phone }}</div>
    </div>
    <div class="growth-stat">
        <small>المنطقة</small>
        <strong>{{ $task->zone?->name ?? 'غير محددة' }}</strong>
        <div>₪{{ number_format((float)$task->fee_snapshot,2) }}</div>
    </div>
    <div class="growth-stat">
        <small>السائق</small>
        <strong>{{ $task->driver?->employee?->full_name ?? $task->driver?->display_name ?? 'لم يعيّن' }}</strong>
    </div>
    <div class="growth-stat">
        <small>الفرع</small>
        <strong>{{ $task->location?->name }}</strong>
    </div>
</div>

<div class="growth-grid-2 growth-section">
    <div class="card">
        <div class="card-header"><span class="card-title">العنوان</span></div>
        <div class="card-body">
            <p>{{ $task->address_snapshot }}</p>
            @if($task->notes)
                <div class="growth-note">{{ $task->notes }}</div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">التحكم</span></div>
        <div class="card-body">
            @can('delivery.assign')
                @if(in_array($task->status, [
                    \App\Enums\DeliveryStatus::Pending,
                    \App\Enums\DeliveryStatus::Failed,
                    \App\Enums\DeliveryStatus::Assigned,
                ], true))
                    <form method="POST" action="{{ route('delivery.tasks.assign',$task) }}" class="growth-actions">
                        @csrf
                        <select class="form-select" name="assigned_driver_id" required>
                            <option value="">اختر السائق</option>
                            @foreach($drivers as $driver)
                                <option value="{{ $driver->id }}" @selected($task->assigned_driver_id===$driver->id)>
                                    {{ $driver->employee?->full_name ?? $driver->display_name }}
                                </option>
                            @endforeach
                        </select>
                        <button class="btn btn-outline">
                            {{ $task->status === \App\Enums\DeliveryStatus::Assigned ? 'إعادة تعيين' : 'تعيين' }}
                        </button>
                    </form>
                    <hr>
                @endif
            @endcan

            @can('delivery.update_status')
                @if($nextStatuses->isNotEmpty())
                    <form method="POST" action="{{ route('delivery.tasks.status',$task) }}">
                        @csrf
                        <div class="growth-actions">
                            <select class="form-select" name="status" required>
                                <option value="">اختر الحالة التالية</option>
                                @foreach($nextStatuses as $status)
                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            <input class="form-input" name="note" placeholder="ملاحظة اختيارية">
                            <button class="btn btn-gold">تحديث الحالة</button>
                        </div>
                    </form>
                @else
                    <div class="growth-note">لا توجد انتقالات تشغيلية متاحة في الحالة الحالية.</div>
                @endif
            @endcan
        </div>
    </div>
</div>

<div class="card growth-section">
    <div class="card-header"><span class="card-title">سجل الحالات</span></div>
    <div class="card-body delivery-history">
        @foreach($task->histories->sortByDesc('created_at') as $history)
            <div>
                <strong>{{ $history->to_status->label() }}</strong>
                @if($history->from_status)
                    <span class="text-muted">من {{ $history->from_status->label() }}</span>
                @endif
                <br>
                <small>
                    {{ $history->created_at?->format('Y-m-d H:i') }} ·
                    {{ $history->changedBy?->employee?->full_name ?? $history->changedBy?->display_name ?? 'النظام' }}
                </small>
                @if($history->note)
                    <p>{{ $history->note }}</p>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
