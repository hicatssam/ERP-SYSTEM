<!DOCTYPE html>

<html
    lang="ar"
    dir="rtl"
>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        كشف حساب
        {{ $supplier->name }}
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            padding: 0;

            background: #f3f4f6;

            color: #222;

            font-family:
                "Cairo",
                "Tahoma",
                "Arial",
                sans-serif;
        }


        /*
        |--------------------------------------------------------------------------
        | Toolbar
        |--------------------------------------------------------------------------
        */

        .print-toolbar {
            width: 210mm;
            max-width: calc(100% - 30px);

            margin: 20px auto 10px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 1rem;
        }


        .toolbar-actions {
            display: flex;
            gap: .6rem;
        }


        .btn {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: .35rem;

            padding: 9px 17px;

            border: 1px solid #ddd;
            border-radius: 8px;

            background: #fff;

            color: #222;

            font-family: inherit;
            font-size: 13px;
            font-weight: 700;

            cursor: pointer;

            text-decoration: none;
        }


        .btn-print {
            background: #c9a338;

            border-color: #c9a338;

            color: #fff;
        }


        /*
        |--------------------------------------------------------------------------
        | Page
        |--------------------------------------------------------------------------
        */

        .statement-page {
            width: 210mm;

            min-height: 297mm;

            margin: 0 auto 30px;

            padding: 15mm;

            background: #fff;

            box-shadow:
                0 4px 20px
                rgba(0, 0, 0, .08);
        }


        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .statement-header {
            display: flex;

            justify-content: space-between;
            align-items: flex-start;

            gap: 2rem;

            padding-bottom: 18px;

            border-bottom:
                2px solid #c9a338;
        }


        .company-name {
            margin: 0;

            color: #ad871d;

            font-size: 26px;
            font-weight: 800;
        }


        .company-subtitle {
            margin-top: 3px;

            color: #888;

            font-size: 11px;
        }


        .statement-title {
            text-align: left;
        }


        .statement-title h1 {
            margin: 0;

            font-size: 23px;
        }


        .statement-title .supplier-code {
            margin-top: 6px;

            color: #777;

            direction: ltr;

            font-size: 12px;
        }


        /*
        |--------------------------------------------------------------------------
        | Supplier Information
        |--------------------------------------------------------------------------
        */

        .supplier-information {
            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 15px;

            margin-top: 20px;
        }


        .info-box {
            padding: 14px;

            border: 1px solid #e6e6e6;
            border-radius: 9px;
        }


        .info-title {
            margin-bottom: 10px;

            padding-bottom: 8px;

            border-bottom:
                1px solid #eee;

            font-size: 14px;
            font-weight: 800;
        }


        .info-row {
            display: flex;

            justify-content: space-between;

            gap: 20px;

            padding: 5px 0;

            font-size: 11.5px;
        }


        .info-label {
            color: #777;
        }


        .info-value {
            font-weight: 600;
        }


        .ltr {
            direction: ltr;

            unicode-bidi: isolate;

            font-variant-numeric:
                tabular-nums;
        }


        /*
        |--------------------------------------------------------------------------
        | Period
        |--------------------------------------------------------------------------
        */

        .period-box {
            display: flex;

            justify-content: center;

            gap: 25px;

            margin-top: 18px;

            padding: 10px;

            border-radius: 8px;

            background: #faf8ef;

            border: 1px solid #e9dfbd;

            color: #715d1d;

            font-size: 11px;
        }


        /*
        |--------------------------------------------------------------------------
        | Table
        |--------------------------------------------------------------------------
        */

        .statement-table {
            margin-top: 20px;
        }


        .section-title {
            margin-bottom: 8px;

            font-size: 14px;
            font-weight: 800;
        }


        table {
            width: 100%;

            border-collapse: collapse;

            font-size: 10.5px;
        }


        thead {
            display: table-header-group;
        }


        th {
            padding: 8px 6px;

            border: 1px solid #e5d79e;

            background: #fff8d9;

            color: #725c14;

            font-weight: 800;
        }


        td {
            padding: 8px 6px;

            border: 1px solid #e8e8e8;

            vertical-align: middle;
        }


        td.numeric {
            direction: ltr;

            text-align: center;

            white-space: nowrap;

            font-variant-numeric:
                tabular-nums;
        }


        td.reference {
            direction: ltr;

            unicode-bidi: isolate;

            white-space: nowrap;
        }


        tbody tr:nth-child(even) {
            background: #fcfcfc;
        }


        .empty {
            padding: 20px;

            text-align: center;

            color: #888;
        }


        /*
        |--------------------------------------------------------------------------
        | Totals
        |--------------------------------------------------------------------------
        */

        .totals {
            width: 48%;

            margin-top: 18px;
            margin-right: auto;

            border: 1px solid #e4e4e4;
            border-radius: 8px;

            overflow: hidden;
        }


        .total-row {
            display: flex;

            justify-content: space-between;

            gap: 20px;

            padding: 8px 12px;

            border-bottom:
                1px solid #ededed;

            font-size: 11.5px;
        }


        .total-row:last-child {
            border-bottom: none;
        }


        .total-row strong {
            direction: ltr;

            font-variant-numeric:
                tabular-nums;
        }


        .final-balance {
            background: #fff8dc;

            color: #6f5811;

            font-size: 13px;
            font-weight: 800;
        }


        /*
        |--------------------------------------------------------------------------
        | Notes
        |--------------------------------------------------------------------------
        */

        .statement-note {
            margin-top: 20px;

            padding: 10px 12px;

            border:
                1px solid #e5e5e5;

            border-radius: 8px;

            color: #777;

            font-size: 10px;
        }


        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */

        .footer {
            margin-top: 35px;

            padding-top: 12px;

            border-top:
                1px solid #ddd;

            display: flex;

            justify-content: space-between;

            gap: 20px;

            color: #999;

            font-size: 9.5px;
        }


        /*
        |--------------------------------------------------------------------------
        | Print
        |--------------------------------------------------------------------------
        */

        @page {
            size: A4 portrait;

            margin: 8mm;
        }


        @media print {

            body {
                background: #fff;
            }


            .print-toolbar {
                display: none !important;
            }


            .statement-page {
                width: auto;

                min-height: auto;

                margin: 0;

                padding: 5mm;

                box-shadow: none;
            }


            tr {
                break-inside: avoid;
            }


            .info-box,
            .totals {
                break-inside: avoid;
            }

        }


        @media screen and (max-width: 850px) {

            .statement-page {
                width: calc(100% - 20px);

                min-height: auto;

                margin: 10px;

                padding: 20px;
            }


            .supplier-information {
                grid-template-columns: 1fr;
            }


            .totals {
                width: 100%;
            }

        }

    </style>

</head>


<body>


    {{-- Toolbar --}}
    <div class="print-toolbar">

        <strong>
            معاينة كشف الحساب
        </strong>


        <div class="toolbar-actions">

            <button
                class="btn btn-print"
                type="button"
                onclick="window.print()"
            >
                🖨️ طباعة
            </button>


            <button
                class="btn"
                type="button"
                onclick="window.close()"
            >
                إغلاق
            </button>

        </div>

    </div>



    <div class="statement-page">


        {{-- Header --}}
        <div class="statement-header">

            <div>

                <h2 class="company-name">
                    حلويات دهب
                </h2>

                <div class="company-subtitle">
                    نظام إدارة المشتريات والموردين
                </div>

            </div>


            <div class="statement-title">

                <h1>
                    كشف حساب مورد
                </h1>


                @if($supplier->supplier_code)

                    <div class="supplier-code">
                        {{ $supplier->supplier_code }}
                    </div>

                @endif

            </div>

        </div>



        {{-- Supplier Information --}}
        <div class="supplier-information">

            <div class="info-box">

                <div class="info-title">
                    بيانات المورد
                </div>


                <div class="info-row">

                    <span class="info-label">
                        اسم المورد
                    </span>

                    <span class="info-value">
                        {{ $supplier->name }}
                    </span>

                </div>


                @if($supplier->company_name)

                    <div class="info-row">

                        <span class="info-label">
                            الشركة
                        </span>

                        <span class="info-value">
                            {{ $supplier->company_name }}
                        </span>

                    </div>

                @endif


                @if($supplier->contact_person)

                    <div class="info-row">

                        <span class="info-label">
                            مسؤول التواصل
                        </span>

                        <span class="info-value">
                            {{ $supplier->contact_person }}
                        </span>

                    </div>

                @endif


                @if($supplier->phone)

                    <div class="info-row">

                        <span class="info-label">
                            الهاتف
                        </span>

                        <span class="info-value ltr">
                            {{ $supplier->phone }}
                        </span>

                    </div>

                @endif


                @if($supplier->email)

                    <div class="info-row">

                        <span class="info-label">
                            البريد الإلكتروني
                        </span>

                        <span class="info-value ltr">
                            {{ $supplier->email }}
                        </span>

                    </div>

                @endif


                @if($supplier->address)

                    <div class="info-row">

                        <span class="info-label">
                            العنوان
                        </span>

                        <span class="info-value">
                            {{ $supplier->address }}
                        </span>

                    </div>

                @endif

            </div>



            <div class="info-box">

                <div class="info-title">
                    بيانات الكشف
                </div>


                <div class="info-row">

                    <span class="info-label">
                        العملة
                    </span>

                    <span class="info-value ltr">
                        {{ $selectedCurrency?->code ?? '—' }}
                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">
                        من تاريخ
                    </span>

                    <span class="info-value ltr">
                        {{ $from
                            ? \Carbon\Carbon::parse($from)->format('Y/m/d')
                            : 'بداية الحساب'
                        }}
                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">
                        إلى تاريخ
                    </span>

                    <span class="info-value ltr">
                        {{ $to
                            ? \Carbon\Carbon::parse($to)->format('Y/m/d')
                            : 'حتى الآن'
                        }}
                    </span>

                </div>


                @if($supplier->tax_number)

                    <div class="info-row">

                        <span class="info-label">
                            الرقم الضريبي
                        </span>

                        <span class="info-value ltr">
                            {{ $supplier->tax_number }}
                        </span>

                    </div>

                @endif


                @if($supplier->commercial_registration)

                    <div class="info-row">

                        <span class="info-label">
                            السجل التجاري
                        </span>

                        <span class="info-value ltr">
                            {{ $supplier->commercial_registration }}
                        </span>

                    </div>

                @endif

            </div>

        </div>



        {{-- Period --}}
        <div class="period-box">

            <span>
                المورد:
                <strong>
                    {{ $supplier->name }}
                </strong>
            </span>

            <span>
                العملة:
                <strong class="ltr">
                    {{ $selectedCurrency?->code }}
                </strong>
            </span>

            <span>
                عدد الحركات:
                <strong class="ltr">
                    {{ collect($rows)->count() }}
                </strong>
            </span>

        </div>



        {{-- Statement --}}
        <div class="statement-table">

            <div class="section-title">
                تفاصيل الحركات
            </div>


            <table>

                <thead>

                    <tr>
                        <th>#</th>
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

                            <td class="numeric">
                                {{ $loop->iteration }}
                            </td>


                            <td class="numeric">

                                {{ $row['date']
                                    ?->format('Y/m/d')
                                    ?? '—'
                                }}

                            </td>


                            <td class="reference">

                                {{ $row['reference'] }}

                            </td>


                            <td>

                                {{ $row['description'] }}

                            </td>


                            <td class="numeric">

                                {{ number_format(
                                    (float) $row['debit'],
                                    2
                                ) }}

                            </td>


                            <td class="numeric">

                                {{ number_format(
                                    (float) $row['credit'],
                                    2
                                ) }}

                            </td>


                            <td class="numeric">

                                <strong>

                                    {{ number_format(
                                        (float) $row['balance'],
                                        2
                                    ) }}

                                </strong>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="7"
                                class="empty"
                            >

                                لا توجد حركات ضمن الفترة المختارة.

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>



        {{-- Totals --}}
        <div class="totals">

            <div class="total-row">

                <span>
                    إجمالي المدين
                </span>

                <strong>

                    {{ number_format(
                        $totalDebit,
                        2
                    ) }}

                    {{ $selectedCurrency?->code }}

                </strong>

            </div>


            <div class="total-row">

                <span>
                    إجمالي الدائن
                </span>

                <strong>

                    {{ number_format(
                        $totalCredit,
                        2
                    ) }}

                    {{ $selectedCurrency?->code }}

                </strong>

            </div>


            <div class="total-row final-balance">

                <span>
                    الرصيد النهائي
                </span>

                <strong>

                    {{ number_format(
                        $finalBalance,
                        2
                    ) }}

                    {{ $selectedCurrency?->code }}

                </strong>

            </div>

        </div>



        <div class="statement-note">

            هذا الكشف يعرض الحركات المسجلة في نظام المشتريات
            ضمن العملة والفترة المحددتين، ويعتمد الرصيد
            الظاهر على تسلسل الحركات المسجلة في النظام.

        </div>



        {{-- Footer --}}
        <div class="footer">

            <span>
                حلويات دهب — كشف حساب مورد
            </span>

            <span class="ltr">
                تاريخ الطباعة:
                {{ now()->format('Y-m-d H:i') }}
            </span>

        </div>

    </div>


</body>

</html>