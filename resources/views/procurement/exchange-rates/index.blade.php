@extends('layouts.app')

@section('title', 'أسعار الصرف')

@section('content')
    <div class="page-actions">
        <div class="page-actions-title">أسعار الصرف</div>

        <div style="display:flex;gap:.6rem;flex-wrap:wrap">
            @can('system_currencies.view')
                @if(\Illuminate\Support\Facades\Route::has('settings.currencies.index'))
                    <a
                        class="btn btn-outline"
                        href="{{ route('settings.currencies.index') }}"
                    >
                        إدارة العملات
                    </a>
                @endif
            @endcan

            <a
                class="btn btn-ghost"
                href="{{ route('procurement.dashboard') }}"
            >
                لوحة المشتريات
            </a>
        </div>
    </div>

    @include('procurement.partials.flash')

    <div class="dashboard-row" style="margin-top:1rem">
        <div class="card">
            <div class="card-header">
                <span class="card-title">العملات المتاحة لأسعار الصرف</span>
            </div>

            <div class="card-body">
                <p style="font-size:.82rem;color:var(--text-muted);margin-top:0">
                    العملات هنا تأتي مباشرة من إدارة العملات.
                    أي عملة فعالة تضيفها هناك ستظهر هنا تلقائيًا.
                </p>

                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>العملة</th>
                                <th>الرمز</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($currencies as $currency)
                                <tr>
                                    <td>
                                        {{ $currency->displayName() }}
                                        ({{ $currency->code }})
                                    </td>

                                    <td>{{ $currency->symbol ?: '—' }}</td>

                                    <td>
                                        {{ $currency->is_base ? 'العملة الأساسية' : 'عملة تحويل' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <div class="empty-state-sm">
                                            لا توجد عملات فعالة.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @can('procurement.exchange_rates.manage')
            <div class="card">
                <div class="card-header">
                    <span class="card-title">إضافة أو تعديل سعر</span>
                </div>

                <div class="card-body">
                    <p style="font-size:.82rem;color:var(--text-muted)">
                        سعر الصرف هو عدد وحدات العملة الأساسية مقابل وحدة واحدة من العملة المختارة.
                    </p>

                    <form
                        method="POST"
                        action="{{ route('procurement.exchange-rates.store') }}"
                        style="display:grid;gap:.9rem"
                    >
                        @csrf

                        <div class="form-group">
                            <label class="form-label">العملة</label>

                            <select
                                class="form-input"
                                name="currency_id"
                                required
                            >
                                @foreach ($currencies as $currency)
                                    <option
                                        value="{{ $currency->id }}"
                                        @selected(old('currency_id') == $currency->id)
                                    >
                                        {{ $currency->displayName() }}
                                        ({{ $currency->code }})
                                        {{ $currency->is_base ? ' — أساسية' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">تاريخ السريان</label>

                            <input
                                class="form-input"
                                type="date"
                                name="effective_date"
                                value="{{ old('effective_date', now()->toDateString()) }}"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                سعر الصرف إلى العملة الأساسية
                            </label>

                            <input
                                class="form-input"
                                type="number"
                                min="0.00000001"
                                step="0.00000001"
                                name="rate_to_base"
                                value="{{ old('rate_to_base', 1) }}"
                                required
                            >
                        </div>

                        <button
                            class="btn btn-gold"
                            type="submit"
                        >
                            حفظ السعر
                        </button>
                    </form>
                </div>
            </div>
        @endcan
    </div>

    <div class="card" style="margin-top:1rem">
        <div class="card-header">
            <span class="card-title">سجل أسعار الصرف</span>
        </div>

        <div class="card-body">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>العملة</th>
                            <th>تاريخ السريان</th>
                            <th>السعر</th>
                            <th>تم الحفظ بواسطة</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($rates as $rate)
                            <tr>
                                <td>
                                    {{ $rate->currency?->displayName() ?? '—' }}
                                    @if($rate->currency?->code)
                                        ({{ $rate->currency->code }})
                                    @endif
                                </td>

                                <td>
                                    {{ $rate->effective_date?->format('Y/m/d') }}
                                </td>

                                <td>
                                    {{ number_format($rate->rate_to_base, 8) }}
                                </td>

                                <td>
                                    {{
                                        $rate->creator?->employee?->full_name
                                        ?? ($rate->creator?->display_name ?? '—')
                                    }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state-sm">
                                        لا توجد أسعار صرف محفوظة بعد.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:1rem">
                {{ $rates->links() }}
            </div>
        </div>
    </div>
@endsection
