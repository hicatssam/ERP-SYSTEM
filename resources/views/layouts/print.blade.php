@php
    $printService = app(\App\Services\PrintThemeService::class);
    $printTheme = $printService->settings();

    $documentTitle = trim(
        $__env->yieldContent('document_title', 'مستند')
    );

    $documentNumber = trim(
        $__env->yieldContent('document_number', '')
    );

    $documentDate = trim(
        $__env->yieldContent(
            'document_date',
            \App\Support\ArabicDate::dateTime(
                now(),
                false
            )
        )
    );

    /*
     * Child print views may still pass an ISO date. Keep the common print
     * header human-readable even before the browser-side formatter runs.
     */
    if (
        preg_match(
            '/^\d{4}-\d{2}-\d{2}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?$/',
            $documentDate
        )
    ) {
        $documentDate = str_contains(
            $documentDate,
            ':'
        )
            ? \App\Support\ArabicDate::dateTime(
                $documentDate,
                false
            )
            : \App\Support\ArabicDate::date(
                $documentDate,
                false
            );
    }

    $documentSubtitle = trim(
        $__env->yieldContent('document_subtitle', '')
    );

    $documentMeta = trim(
        $__env->yieldContent('document_meta', '')
    );

    $pdfMode = trim(
        $__env->yieldContent('pdf_mode', '0')
    ) === '1';

    /*
     * Signatures and the official stamp are document-level concerns.
     * Reports/statements keep the Branding defaults, while invoices and
     * receipts can explicitly disable them without changing global settings.
     */
    $showSignatures = trim(
        $__env->yieldContent(
            'show_signatures',
            ! empty($printTheme['show_signatures']) ? '1' : '0'
        )
    ) === '1';

    $showStamp = trim(
        $__env->yieldContent(
            'show_stamp',
            ! empty($printTheme['show_stamp']) ? '1' : '0'
        )
    ) === '1';

    $paperOrientation = trim(
        $__env->yieldContent('paper_orientation', 'portrait')
    );

    if (! in_array($paperOrientation, ['portrait', 'landscape'], true)) {
        $paperOrientation = 'portrait';
    }

    $paperSize = $printTheme['paper_size'] ?? 'A4';

    $paperWidth = match (true) {
        $paperSize === 'A5'
            && $paperOrientation === 'landscape' => '210mm',

        $paperSize === 'A5' => '148mm',

        $paperSize === '80mm' => '80mm',

        $paperOrientation === 'landscape' => '297mm',

        default => '210mm',
    };

    $paperMinHeight = match (true) {
        $paperSize === 'A5'
            && $paperOrientation === 'landscape' => '148mm',

        $paperSize === 'A5' => '210mm',

        $paperSize === '80mm' => '120mm',

        $paperOrientation === 'landscape' => '210mm',

        default => '297mm',
    };

    /*
    |--------------------------------------------------------------------------
    | مهم للطباعة من Chrome / Edge
    |--------------------------------------------------------------------------
    | نستخدم margin: 0 في @page ثم نصنع الهامش داخل الورقة نفسها.
    | هذا يمنع مساحة رؤوس/تذييلات المتصفح من دخول التصميم في أغلب
    | المتصفحات، وفي نفس الوقت يحافظ على حواف داخلية نظيفة للمستند.
    */
    $printInnerPadding = match ($paperSize) {
        '80mm' => '4mm',
        'A5' => '8mm 9mm',
        default => '10mm 11mm',
    };
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>{{ $documentTitle }}</title>

    <style>
        @page {
            @if($paperSize === 'A5')
                size: A5 {{ $paperOrientation }};
            @elseif($paperSize === '80mm')
                size: 80mm auto;
            @else
                size: A4 {{ $paperOrientation }};
            @endif

            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            direction: rtl;
        }

        html {
            background: #e5e7eb;
        }

        body {
            background: #e5e7eb;
            color: {{ $printTheme['text_color'] }};
            font-family:
                "Cairo",
                "DejaVu Sans",
                Arial,
                sans-serif;
            font-size: 10pt;
            line-height: 1.55;
        }

        table {
            border-collapse: collapse;
        }

        .print-screen-toolbar {
            width: {{ $paperWidth }};
            max-width: calc(100vw - 32px);
            margin: 14px auto 10px;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 8px;
        }

        .print-screen-btn {
            appearance: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 38px;
            padding: 8px 16px;
            border: 1px solid #d7dde4;
            border-radius: 8px;
            background: #ffffff;
            color: {{ $printTheme['secondary_color'] }};
            font-family: inherit;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
        }

        .print-screen-btn.primary {
            border-color: {{ $printTheme['primary_color'] }};
            background: {{ $printTheme['primary_color'] }};
            color: #ffffff;
        }

        .print-paper {
            width: {{ $paperWidth }};
            max-width: calc(100vw - 32px);
            min-height: {{ $paperMinHeight }};
            margin: 0 auto 26px;
            padding: {{ $printInnerPadding }};
            background: #ffffff;
            box-shadow: 0 8px 30px rgba(15, 23, 42, .14);
            overflow: hidden;
        }

        .print-header-table {
            width: 100%;
            margin: 0 0 12px;
            border-bottom:
                {{ ($printTheme['template'] ?? 'modern') === 'modern' ? '2px' : '1px' }}
                solid
                {{ $printTheme['primary_color'] }};
            page-break-inside: avoid;
        }

        .print-header-table td {
            padding: 0 0 9px;
            vertical-align: middle;
        }

        .print-header-classic {
            border-top:
                3px solid
                {{ $printTheme['primary_color'] }};
        }

        .print-brand-cell {
            width: 32%;
            vertical-align: top !important;
        }

        .print-title-cell {
            width: 36%;
            text-align: center;
        }

        .print-meta-cell {
            width: 32%;
            text-align: left;
            vertical-align: top !important;
        }

        .print-logo {
            display: inline-block;
            max-height: 62px;
            max-width: 145px;
            object-fit: contain;
        }

        .print-business-name {
            margin-top: 2px;
            color: {{ $printTheme['secondary_color'] }};
            font-size: 13pt;
            font-weight: 900;
            line-height: 1.35;
        }

        .print-business-name-en {
            margin-top: 1px;
            direction: ltr;
            color: #6b7280;
            font-size: 7pt;
        }

        .print-business-tagline {
            margin-top: 2px;
            color: #6b7280;
            font-size: 7pt;
        }

        .print-business-info {
            margin-top: 3px;
            color: #6b7280;
            font-size: 6.8pt;
            line-height: 1.42;
        }

        .print-document-title {
            color: {{ $printTheme['secondary_color'] }};
            font-size: 17pt;
            font-weight: 900;
            line-height: 1.25;
        }

        .print-document-subtitle {
            margin-top: 3px;
            color: #6b7280;
            font-size: 7.4pt;
            line-height: 1.4;
        }

        .print-meta-row {
            margin-bottom: 2px;
            color: #6b7280;
            font-size: 7.2pt;
            line-height: 1.45;
        }

        .print-meta-row strong {
            color: {{ $printTheme['secondary_color'] }};
        }

        .print-content {
            display: block;
            width: 100%;
            clear: both;
        }

        .print-section {
            margin-top: 12px;
            page-break-inside: auto;
        }

        .print-section:first-child {
            margin-top: 0;
        }

        .print-section-title {
            margin: 0 0 6px;
            color: {{ $printTheme['secondary_color'] }};
            font-size: 10pt;
            font-weight: 900;
        }

        .print-summary {
            width: 100%;
            margin-top: 8px;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 5px 0;
        }

        .print-summary td {
            padding: 7px;
            border: 1px solid #e5e7eb;
            text-align: center;
            vertical-align: middle;
            background: #f8fafc;
        }

        .print-summary-label {
            color: #6b7280;
            font-size: 7pt;
        }

        .print-summary-value {
            display: block;
            margin-top: 2px;
            color: {{ $printTheme['secondary_color'] }};
            font-size: 10pt;
            font-weight: 900;
        }

        .print-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .print-table {
            width: 100%;
            border: 1px solid #e5e7eb;
            page-break-inside: auto;
        }

        .print-table thead {
            display: table-header-group;
        }

        .print-table tr {
            page-break-inside: avoid;
        }

        .print-table th {
            padding: 6px 5px;
            border: 1px solid #d7dde4;
            background: {{ $printTheme['secondary_color'] }};
            color: #ffffff;
            font-size: 7.4pt;
            font-weight: 900;
            text-align: center;
        }

        .print-table td {
            padding: 6px 5px;
            border: 1px solid #e5e7eb;
            font-size: 7.4pt;
            vertical-align: middle;
        }

        .print-table tbody tr:nth-child(even) td {
            background: #fbfcfd;
        }

        .print-money {
            direction: ltr;
            white-space: nowrap;
            font-weight: 800;
        }

        .print-credit {
            color: #08783e;
        }

        .print-debit {
            color: #b42318;
        }

        .print-primary {
            color: {{ $printTheme['primary_color'] }};
        }

        .print-muted {
            color: #6b7280;
        }

        .print-empty {
            padding: 16px;
            text-align: center;
            color: #6b7280;
        }

        .print-total-box {
            width: 42%;
            margin-top: 12px;
            margin-right: auto;
            border: 1px solid #e5e7eb;
        }

        .print-total-box td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 7.6pt;
        }

        .print-total-box td:last-child {
            direction: ltr;
            text-align: left;
            white-space: nowrap;
            font-weight: 800;
        }

        .print-total-box tr:last-child td {
            border-bottom: 0;
        }

        .print-total-box .grand td {
            padding: 8px;
            background: {{ $printTheme['primary_color'] }};
            color: #ffffff;
            font-size: 9pt;
            font-weight: 900;
        }

        .print-after-content {
            clear: both;
            width: 100%;
        }

        .print-signature-table {
            width: 100%;
            margin-top: 22px;
            page-break-inside: avoid;
        }

        .print-signature-table td {
            width: 50%;
            padding: 6px 28px 0;
            text-align: center;
            vertical-align: bottom;
        }

        .print-signature-image {
            display: block;
            max-width: 110px;
            max-height: 46px;
            margin: 0 auto 3px;
        }

        .print-signature-label {
            padding-top: 5px;
            border-top: 1px solid #9ca3af;
            color: #4b5563;
            font-size: 7.4pt;
        }

        .print-stamp {
            display: block;
            max-width: 90px;
            max-height: 68px;
            margin: 10px auto 0;
            page-break-inside: avoid;
        }

        .print-footer {
            width: 100%;
            margin-top: 13px;
            border-top:
                1px solid
                {{ $printTheme['primary_color'] }};
            page-break-inside: avoid;
        }

        .print-footer td {
            padding-top: 5px;
            color: #7a8490;
            font-size: 6.6pt;
        }

        @if($paperSize === '80mm')
            .print-paper {
                padding: 4mm;
            }

            body {
                font-size: 8pt;
            }

            .print-header-table,
            .print-signature-table {
                table-layout: fixed;
            }

            .print-brand-cell,
            .print-title-cell,
            .print-meta-cell {
                width: auto;
            }

            .print-document-title {
                font-size: 12pt;
            }

            .print-business-name {
                font-size: 9pt;
            }

            .print-table th,
            .print-table td {
                padding: 4px 3px;
                font-size: 6.3pt;
            }

            .print-total-box {
                width: 100%;
            }
        @endif

        @media print {
            html,
            body {
                width: 100% !important;
                min-height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-screen-toolbar {
                display: none !important;
            }

            .print-paper {
                width: 100% !important;
                max-width: none !important;
                min-height: 0 !important;
                margin: 0 !important;
                padding: {{ $printInnerPadding }} !important;
                background: #ffffff !important;
                box-shadow: none !important;
                overflow: visible !important;
            }

            .print-table-wrap {
                overflow: visible !important;
            }

            a[href]::after {
                content: none !important;
            }
        }
    </style>

    @stack('print_styles')

    {{-- Final print guard: must stay AFTER child styles --}}
    <style>
        @page {
            @if($paperSize === 'A5')
                size: A5 {{ $paperOrientation }};
            @elseif($paperSize === '80mm')
                size: 80mm auto;
            @else
                size: A4 {{ $paperOrientation }};
            @endif

            margin: 0 !important;
        }

        @media print {
            html,
            body {
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
            }

            .print-screen-toolbar {
                display: none !important;
            }

            .print-paper {
                width: 100% !important;
                max-width: none !important;
                min-height: 0 !important;
                margin: 0 !important;
                padding: {{ $printInnerPadding }} !important;
                box-shadow: none !important;
                overflow: visible !important;
            }
        }
    </style>
</head>

<body>
    @unless($pdfMode)
        <div class="print-screen-toolbar">
            <button
                type="button"
                class="print-screen-btn primary"
                onclick="window.print()"
            >
                🖨️ طباعة
            </button>

            <button
                type="button"
                class="print-screen-btn"
                onclick="window.close()"
            >
                إغلاق
            </button>
        </div>
    @endunless

    <main class="print-paper">
        @include(
            'layouts.print.header',
            [
                'printTheme' => $printTheme,
                'documentTitle' => $documentTitle,
                'documentNumber' => $documentNumber,
                'documentDate' => $documentDate,
                'documentSubtitle' => $documentSubtitle,
                'documentMeta' => $documentMeta,
            ]
        )

        <div class="print-content">
            @yield('print_content')
        </div>

        <div class="print-after-content">
            @if($showSignatures)
                @include(
                    'layouts.print.signatures',
                    ['printTheme' => $printTheme]
                )
            @endif

            @if(
                $showStamp
                && ! empty($printTheme['stamp_src'])
                && $printTheme['stamp_src'] !== ($printTheme['signature_src'] ?? null)
            )
                <img
                    src="{{ $printTheme['stamp_src'] }}"
                    class="print-stamp"
                    alt="الختم الرسمي"
                >
            @endif

            @if($printTheme['show_footer'])
                @include(
                    'layouts.print.footer',
                    ['printTheme' => $printTheme]
                )
            @endif
        </div>
    </main>

    @stack('print_scripts')
    @if(! $pdfMode)
        @php
            $dateFormatAssetVersion = @filemtime(
                public_path(
                    'assets/js/date-format.js'
                )
            ) ?: 1;
        @endphp

        <script
            src="{{ asset('assets/js/date-format.js') }}?v={{ $dateFormatAssetVersion }}"
        ></script>
    @endif

</body>
</html>
