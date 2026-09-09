@extends('layouts.app')

@section('title', 'كشف حساب المورد')

@section('content')

    <div class="page-actions">

        <div class="page-actions-title">
            كشف حساب:
            {{ $supplier->name }}
        </div>


        <div class="action-btns">

            <a
                class="btn btn-ghost"
                href="{{ route(
                    'suppliers.show',
                    $supplier
                ) }}"
            >
                رجوع
            </a>


            {{-- طباعة كشف الحساب --}}
            <a
                class="btn btn-gold"
                target="_blank"
                href="{{ route(
                    'suppliers.statement.print',
                    array_merge(
                        [
                            'supplier' => $supplier,
                        ],
                        request()->only([
                            'currency_id',
                            'from',
                            'to',
                        ])
                    )
                ) }}"
            >
                🖨️ طباعة كشف الحساب
            </a>

        </div>

    </div>


    {{-- Filters --}}
    <div
        class="card"
        style="margin-bottom:1rem"
    >

        <div class="card-body">

            <form
                method="GET"
                class="statement-filters"
            >

                <div class="form-group">

                    <label class="form-label">
                        العملة
                    </label>

                    <select
                        class="form-input"
                        name="currency_id"
                    >

                        @foreach ($currencies as $currency)

                            <option
                                value="{{ $currency->id }}"
                                @selected(
                                    $selectedCurrencyId
                                    ==
                                    $currency->id
                                )
                            >
                                {{ $currency->displayName() }}
                                ({{ $currency->code }})
                            </option>

                        @endforeach

                    </select>

                </div>


                <div class="form-group">

                    <label class="form-label">
                        من
                    </label>

                    <input
                        class="form-input"
                        type="date"
                        name="from"
                        value="{{ request('from') }}"
                    >

                </div>


                <div class="form-group">

                    <label class="form-label">
                        إلى
                    </label>

                    <input
                        class="form-input"
                        type="date"
                        name="to"
                        value="{{ request('to') }}"
                    >

                </div>


                <button
                    class="btn btn-outline"
                    type="submit"
                >
                    عرض
                </button>


                @if(
                    request()->filled('from')
                    ||
                    request()->filled('to')
                )

                    <a
                        class="btn btn-ghost"
                        href="{{ route(
                            'suppliers.statement',
                            [
                                'supplier' => $supplier,
                                'currency_id'
                                    => $selectedCurrencyId,
                            ]
                        ) }}"
                    >
                        إزالة الفترة
                    </a>

                @endif

            </form>

        </div>

    </div>


    {{-- Summary --}}
    <div class="statement-summary">

        <div class="statement-summary-card">

            <span>
                إجمالي المدين
            </span>

            <strong dir="ltr">
                {{ number_format(
                    $totalDebit,
                    2
                ) }}

                {{ $selectedCurrency?->code }}
            </strong>

        </div>


        <div class="statement-summary-card">

            <span>
                إجمالي الدائن
            </span>

            <strong dir="ltr">
                {{ number_format(
                    $totalCredit,
                    2
                ) }}

                {{ $selectedCurrency?->code }}
            </strong>

        </div>


        <div class="statement-summary-card">

            <span>
                الرصيد النهائي
            </span>

            <strong dir="ltr">
                {{ number_format(
                    $finalBalance,
                    2
                ) }}

                {{ $selectedCurrency?->code }}
            </strong>

        </div>

    </div>


    {{-- Statement --}}
    <div class="table-wrap">

        <table class="data-table">

            <thead>

                <tr>
                    <th>التاريخ</th>
                    <th>المرجع</th>
                    <th>البيان</th>
                    <th>مدين</th>
                    <th>دائن</th>
                    <th>الرصيد</th>
                </tr>

            </thead>


            <tbody>

                @forelse($rows as $row)

                    <tr>

                        <td dir="ltr">
                            {{ $row['date']?->format('Y/m/d') ?? '—' }}
                        </td>


                        <td dir="ltr">
                            {{ $row['reference'] }}
                        </td>


                        <td>
                            {{ $row['description'] }}
                        </td>


                        <td class="statement-number">

                            {{ number_format(
                                $row['debit'],
                                2
                            ) }}

                        </td>


                        <td class="statement-number">

                            {{ number_format(
                                $row['credit'],
                                2
                            ) }}

                        </td>


                        <td class="statement-number">

                            <strong>

                                {{ number_format(
                                    $row['balance'],
                                    2
                                ) }}

                            </strong>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="6">

                            <div class="empty-state-sm">

                                لا توجد حركات ضمن الفترة المختارة.

                            </div>

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>


    <style>

        .statement-filters {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            align-items: end;
        }


        .statement-summary {
            display: grid;

            grid-template-columns:
                repeat(
                    3,
                    minmax(180px, 1fr)
                );

            gap: 1rem;

            margin-bottom: 1rem;
        }


        .statement-summary-card {
            display: flex;

            justify-content: space-between;
            align-items: center;

            gap: 1rem;

            padding: 1rem 1.15rem;

            border:
                1px solid
                var(--border-light);

            border-radius: .75rem;

            background: #fff;
        }


        .statement-summary-card span {
            color: var(--text-muted);

            font-size: .8rem;
        }


        .statement-summary-card strong {
            font-size: 1rem;

            font-variant-numeric:
                tabular-nums;
        }


        .statement-number {
            direction: ltr;

            text-align: center;

            font-variant-numeric:
                tabular-nums;
        }


        @media(max-width:768px) {

            .statement-summary {
                grid-template-columns: 1fr;
            }


            .statement-filters {
                align-items: stretch;
                flex-direction: column;
            }

        }

    </style>

@endsection