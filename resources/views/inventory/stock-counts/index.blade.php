@extends('layouts.app')

@section('title', 'جرد المخزون')
@section('page-title', 'جرد المخزون')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-heading">جلسات جرد المخزون</h1>
        <p class="page-subheading">إدارة ومتابعة عمليات الجرد الدورية</p>
    </div>

    <div class="page-header-actions">
        @can('inventory.count')
            <a href="{{ route('stock-counts.create') }}" class="btn btn-gold">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                بدء جرد جديد
            </a>
        @endcan
    </div>
</div>

{{-- Summary KPIs --}}
@php
    $stockCountItems = $stockCounts->getCollection();

    /**
     * Return the scalar value of a status whether it is a backed Enum
     * or an older plain string stored on the model.
     */
    $getStatusValue = static function (mixed $status): string {
        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        if (is_string($status) || is_int($status)) {
            return (string) $status;
        }

        return '';
    };

    $total = $stockCounts->total();

    $inProgressCount = $stockCountItems
        ->filter(fn ($stockCount) => $getStatusValue($stockCount->status) === 'in_progress')
        ->count();

    $approvedCount = $stockCountItems
        ->filter(fn ($stockCount) => $getStatusValue($stockCount->status) === 'approved')
        ->count();
@endphp

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1rem">
    <div class="stat-card stat-gold">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 11 12 14 22 4"/>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
        </div>
        <div class="stat-info">
            <div class="stat-value">{{ $total }}</div>
            <div class="stat-label">إجمالي الجرد</div>
        </div>
    </div>

    <div class="stat-card stat-orange">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="stat-info">
            <div class="stat-value">{{ $inProgressCount }}</div>
            <div class="stat-label">قيد التنفيذ</div>
        </div>
    </div>

    <div class="stat-card stat-green">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <div class="stat-info">
            <div class="stat-value">{{ $approvedCount }}</div>
            <div class="stat-label">معتمد</div>
        </div>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="7" width="20" height="14" rx="2"/>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
            </svg>
            سجل عمليات الجرد
        </span>
    </div>

    <div class="table-wrap" style="border-radius:0;border:none">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الموقع</th>
                    <th>الحالة</th>
                    <th>المنفذ</th>
                    <th>معتمد من</th>
                    <th>تاريخ الاعتماد</th>
                    <th>تاريخ الإنشاء</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>

            <tbody>
                @forelse($stockCounts as $sc)
                    @php
                        $statusVal = $getStatusValue($sc->status);

                        $statusLabel = match ($statusVal) {
                            'in_progress' => 'قيد التنفيذ',
                            'pending_approval' => 'بانتظار الاعتماد',
                            'approved' => 'معتمد',
                            'rejected' => 'مرفوض',
                            default => $statusVal !== '' ? $statusVal : 'غير محدد',
                        };

                        $statusBadge = match ($statusVal) {
                            'approved' => 'badge-active',
                            'rejected' => 'badge-inactive',
                            'pending_approval' => 'badge-pending',
                            default => 'badge-grey',
                        };
                    @endphp

                    <tr>
                        <td style="color:var(--text-muted);font-size:.8rem">#{{ $sc->id }}</td>

                        <td>
                            <span style="font-weight:600">{{ $sc->location?->name ?? '—' }}</span>
                        </td>

                        <td>
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                        </td>

                        <td>
                            {{ $sc->creator?->display_name ?? 'غير مسجل' }}
                        </td>

                        <td>
                            {{ $sc->approvedBy?->employee?->full_name ?? '—' }}
                        </td>

                        <td>
                            {{ $sc->approved_at?->format('Y/m/d H:i') ?? '—' }}
                        </td>

                        <td>
                            {{ $sc->created_at?->format('Y/m/d H:i') ?? '—' }}
                        </td>

                        <td>
                            <div class="actions">
                                <a
                                    href="{{ route('stock-counts.show', $sc) }}"
                                    class="btn btn-ghost btn-sm"
                                    title="عرض"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </a>

                                @if(in_array($statusVal, ['in_progress', 'pending_approval'], true))
                                    <a
                                        href="{{ route('stock-counts.edit', $sc) }}"
                                        class="btn btn-ghost btn-sm"
                                        title="تعديل"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </a>
                                @endif

                                @can('inventory.count')
                                    @if($statusVal === 'pending_approval')
                                        <form
                                            action="{{ route('stock-counts.approve', $sc) }}"
                                            method="POST"
                                            style="display:inline"
                                        >
                                            @csrf
                                            <button
                                                type="submit"
                                                class="btn btn-success btn-sm"
                                                title="اعتماد"
                                                data-confirm="هل تريد اعتماد هذه الجردة؟"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <polyline points="20 6 9 17 4 12"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif

                                    @if($statusVal !== 'approved')
                                        <form
                                            action="{{ route('stock-counts.destroy', $sc) }}"
                                            method="POST"
                                            style="display:inline"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="button"
                                                class="btn btn-danger btn-sm"
                                                title="حذف"
                                                data-confirm="هل أنت متأكد من حذف هذه الجردة؟"
                                                onclick="openConfirmModal({ title: 'حذف الجردة', body: 'هل أنت متأكد من حذف هذه الجردة؟ لا يمكن التراجع.', onConfirm: () => this.closest('form').submit() })"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"/>
                                                    <path d="M19 6l-1 14H6L5 6"/>
                                                    <path d="M10 11v6M14 11v6"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <polyline points="9 11 12 14 22 4"/>
                                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                </svg>
                                <h3>لا توجد عمليات جرد</h3>
                                <p>ابدأ جردة جديدة للمخزون من الزر أعلاه</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

  

    <div>
    {{ $stockCounts->withQueryString()->links() }}
    </div>
</div>
@endsection
