@extends('layouts.app')

@section('title', 'الفترات المالية')

@section('content')

@php
    $statusValue = static function ($status): string {
        return $status instanceof \BackedEnum
            ? (string) $status->value
            : (string) $status;
    };
@endphp

<div class="page-header">
    <div>
        <h1 class="page-heading">الفترات المالية</h1>
        <p class="page-subheading">
            فتح الفترات ومتابعة حالتها من نفس الصفحة.
        </p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">
        {{ session('success') }}
    </div>
@endif

@if(session('info'))
    <div class="alert alert-info" style="margin-bottom:1rem">
        {{ session('info') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger" style="margin-bottom:1rem">
        {{ session('error') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        <strong>تعذر تنفيذ العملية:</strong>
        <ul style="margin:.5rem 1rem 0 0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@can('financial.periods.open')
    <div class="card" style="margin-bottom:1.25rem">
        <div class="card-header">
            <div>
                <div class="card-title">
                    فتح فترة مالية جديدة
                </div>
                <div
                    style="
                        margin-top:.2rem;
                        font-size:.78rem;
                        color:var(--text-muted)
                    "
                >
                    أدخل السنة والشهر والرصيد الافتتاحي.
                </div>
            </div>
        </div>

        <div class="card-body">
            <form
                action="{{ route('financial-periods.store') }}"
                method="POST"
            >
                @csrf

                <div
                    class="financial-period-form-grid"
                    style="
                        display:grid;
                        grid-template-columns:
                            repeat(4,minmax(0,1fr));
                        gap:1rem;
                        align-items:end
                    "
                >
                    <div class="form-group">
                        <label
                            class="form-label"
                            for="year"
                        >
                            السنة *
                        </label>

                        <input
                            type="number"
                            name="year"
                            id="year"
                            class="form-input @error('year') is-invalid @enderror"
                            value="{{ old('year', now()->year) }}"
                            min="2020"
                            max="2100"
                            required
                        >

                        @error('year')
                            <div class="form-error">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label
                            class="form-label"
                            for="month"
                        >
                            الشهر *
                        </label>

                        <select
                            name="month"
                            id="month"
                            class="form-select @error('month') is-invalid @enderror"
                            required
                        >
                            @for($m = 1; $m <= 12; $m++)
                                <option
                                    value="{{ $m }}"
                                    @selected(
                                        (int) old(
                                            'month',
                                            now()->month
                                        ) === $m
                                    )
                                >
                                    {{
                                        \Carbon\Carbon::create()
                                            ->month($m)
                                            ->locale('ar')
                                            ->translatedFormat('F')
                                    }}
                                </option>
                            @endfor
                        </select>

                        @error('month')
                            <div class="form-error">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label
                            class="form-label"
                            for="opening_balance"
                        >
                            الرصيد الافتتاحي (₪)
                        </label>

                        <input
                            type="number"
                            name="opening_balance"
                            id="opening_balance"
                            class="form-input @error('opening_balance') is-invalid @enderror"
                            value="{{ old('opening_balance', 0) }}"
                            min="0"
                            step="0.01"
                        >

                        @error('opening_balance')
                            <div class="form-error">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div>
                        <button
                            type="submit"
                            class="btn btn-gold"
                            style="width:100%"
                        >
                            + فتح الفترة
                        </button>
                    </div>
                </div>

                <div
                    class="form-group"
                    style="margin-top:1rem"
                >
                    <label
                        class="form-label"
                        for="notes"
                    >
                        ملاحظات
                    </label>

                    <textarea
                        name="notes"
                        id="notes"
                        class="form-textarea @error('notes') is-invalid @enderror"
                        rows="3"
                        placeholder="أضف ملاحظات على الفترة إن وجدت..."
                    >{{ old('notes') }}</textarea>

                    @error('notes')
                        <div class="form-error">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </form>
        </div>
    </div>
@endcan

<div class="card">
    <div class="card-header">
        <div class="card-title">
            الفترات المالية
        </div>

        <div
            style="
                font-size:.78rem;
                color:var(--text-muted)
            "
        >
            {{ number_format($periods->total()) }} فترة
        </div>
    </div>

    <div
        class="table-wrap"
        style="border:none;border-radius:0"
    >
        <table class="data-table">
            <thead>
                <tr>
                    <th>السنة</th>
                    <th>الشهر</th>
                    <th>من</th>
                    <th>إلى</th>
                    <th>الرصيد الافتتاحي</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>

            <tbody>
                @forelse($periods as $p)
                    @php
                        /*
                         * status عندك Enum:
                         * FinancialPeriodStatus::class
                         * لذلك لا يجوز:
                         * $p->status === 'open'
                         */
                        $currentStatus =
                            $statusValue($p->status);

                        $isOpen =
                            $currentStatus === 'open';
                    @endphp

                    <tr>
                        <td>
                            <strong>{{ $p->year }}</strong>
                        </td>

                        <td>
                            {{
                                \Carbon\Carbon::create()
                                    ->month((int) $p->month)
                                    ->locale('ar')
                                    ->translatedFormat('F')
                            }}
                        </td>

                        <td dir="ltr">
                            {{
                                $p->start_date
                                    ? $p->start_date->format('Y/m/d')
                                    : '—'
                            }}
                        </td>

                        <td dir="ltr">
                            {{
                                $p->end_date
                                    ? $p->end_date->format('Y/m/d')
                                    : '—'
                            }}
                        </td>

                        <td>
                            ₪ {{
                                number_format(
                                    (float)
                                    $p->opening_balance,
                                    2
                                )
                            }}
                        </td>

                        <td>
                            <span
                                class="badge {{
                                    $isOpen
                                        ? 'badge-active'
                                        : 'badge-inactive'
                                }}"
                            >
                                {{
                                    $isOpen
                                        ? 'مفتوحة'
                                        : 'مغلقة'
                                }}
                            </span>
                        </td>

                        <td>
                            <div class="actions">
                                <a
                                    href="{{
                                        route(
                                            'financial-periods.show',
                                            $p
                                        )
                                    }}"
                                    class="btn btn-ghost btn-sm"
                                >
                                    عرض
                                </a>

                                @if($isOpen)
                                    @can('financial.periods.close')
                                        <form
                                            action="{{
                                                route(
                                                    'financial-periods.close',
                                                    $p
                                                )
                                            }}"
                                            method="POST"
                                            style="display:inline"
                                            onsubmit="
                                                return confirm(
                                                    'إغلاق الفترة المالية؟'
                                                );
                                            "
                                        >
                                            @csrf

                                            

                                            <button
                                                type="submit"
                                                class="btn btn-outline btn-sm"
                                            >
                                                إغلاق
                                            </button>
                                        </form>
                                    @endcan
                                @else
                                    @can('financial.periods.open')
                                        <form
                                            action="{{
                                                route(
                                                    'financial-periods.open',
                                                    $p
                                                )
                                            }}"
                                            method="POST"
                                            style="display:inline"
                                            onsubmit="
                                                return confirm(
                                                    'إعادة فتح الفترة؟ سيتم حذف Snapshot الإغلاق السابق وإعادة توليده عند الإغلاق القادم.'
                                                );
                                            "
                                        >
                                            @csrf

                                            <button
                                                type="submit"
                                                class="btn btn-gold btn-sm"
                                            >
                                                إعادة فتح
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state-sm">
                                لا توجد فترات مالية.
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div style="margin-top:1rem">
    {{ $periods->withQueryString()->links() }}
</div>

<style>
@media(max-width: 980px) {
    .financial-period-form-grid {
        grid-template-columns: 1fr 1fr !important;
    }
}

@media(max-width: 600px) {
    .financial-period-form-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>

@endsection
