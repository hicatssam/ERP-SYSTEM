@php
    $logoPosition = $printTheme['logo_position'] ?? 'right';

    $documentSubtitle = $documentSubtitle
        ?? trim($__env->yieldContent('document_subtitle', ''));

    $documentMeta = $documentMeta
        ?? trim($__env->yieldContent('document_meta', ''));
@endphp

<table
    class="print-header-table {{ ($printTheme['template'] ?? 'modern') === 'classic' ? 'print-header-classic' : '' }}"
    cellpadding="0"
    cellspacing="0"
>
    <tr>
        @if($logoPosition === 'left')
            <td class="print-meta-cell">
                @if(
                    $printTheme['show_document_number']
                    && $documentNumber !== ''
                )
                    <div class="print-meta-row">
                        رقم المستند:
                        <strong dir="ltr">
                            {{ $documentNumber }}
                        </strong>
                    </div>
                @endif

                @if($documentDate !== '')
                    <div class="print-meta-row">
                        التاريخ:
                        <strong dir="ltr">
                            {{ $documentDate }}
                        </strong>
                    </div>
                @endif

                @if($documentMeta !== '')
                    <div class="print-meta-row">
                        {{ $documentMeta }}
                    </div>
                @endif
            </td>

            <td class="print-title-cell">
                <div class="print-document-title">
                    {{ $documentTitle }}
                </div>

                @if($documentSubtitle !== '')
                    <div class="print-document-subtitle">
                        {{ $documentSubtitle }}
                    </div>
                @endif
            </td>

            <td
                class="print-brand-cell"
                style="text-align:left"
            >
                @include(
                    'layouts.print.brand',
                    ['printTheme' => $printTheme]
                )
            </td>
        @elseif($logoPosition === 'center')
            <td
                class="print-meta-cell"
                style="width:28%"
            >
                @if(
                    $printTheme['show_document_number']
                    && $documentNumber !== ''
                )
                    <div class="print-meta-row">
                        رقم المستند:
                        <strong dir="ltr">
                            {{ $documentNumber }}
                        </strong>
                    </div>
                @endif

                @if($documentDate !== '')
                    <div class="print-meta-row">
                        التاريخ:
                        <strong dir="ltr">
                            {{ $documentDate }}
                        </strong>
                    </div>
                @endif

                @if($documentMeta !== '')
                    <div class="print-meta-row">
                        {{ $documentMeta }}
                    </div>
                @endif
            </td>

            <td
                class="print-title-cell"
                style="width:44%"
            >
                @include(
                    'layouts.print.brand',
                    ['printTheme' => $printTheme]
                )

                <div
                    class="print-document-title"
                    style="margin-top:6px"
                >
                    {{ $documentTitle }}
                </div>

                @if($documentSubtitle !== '')
                    <div class="print-document-subtitle">
                        {{ $documentSubtitle }}
                    </div>
                @endif
            </td>

            <td style="width:28%"></td>
        @else
            <td class="print-brand-cell">
                @include(
                    'layouts.print.brand',
                    ['printTheme' => $printTheme]
                )
            </td>

            <td class="print-title-cell">
                <div class="print-document-title">
                    {{ $documentTitle }}
                </div>

                @if($documentSubtitle !== '')
                    <div class="print-document-subtitle">
                        {{ $documentSubtitle }}
                    </div>
                @endif
            </td>

            <td class="print-meta-cell">
                @if(
                    $printTheme['show_document_number']
                    && $documentNumber !== ''
                )
                    <div class="print-meta-row">
                        رقم المستند:
                        <strong dir="ltr">
                            {{ $documentNumber }}
                        </strong>
                    </div>
                @endif

                @if($documentDate !== '')
                    <div class="print-meta-row">
                        التاريخ:
                        <strong dir="ltr">
                            {{ $documentDate }}
                        </strong>
                    </div>
                @endif

                @if($documentMeta !== '')
                    <div class="print-meta-row">
                        {{ $documentMeta }}
                    </div>
                @endif
            </td>
        @endif
    </tr>
</table>
