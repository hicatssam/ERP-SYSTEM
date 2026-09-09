@php
    $signatureMode = trim(
        $__env->yieldContent(
            'signature_mode',
            'both'
        )
    );

    $rightLabel = trim(
        $__env->yieldContent(
            'signature_right',
            'توقيع المستلم'
        )
    );

    $leftLabel = trim(
        $__env->yieldContent(
            'signature_left',
            'اعتماد الإدارة'
        )
    );

    if ($signatureMode === 'admin_only') {
        $rightLabel = '';

        if ($leftLabel === '') {
            $leftLabel = 'اعتماد الإدارة';
        }
    }

    if ($signatureMode === 'right_only') {
        $leftLabel = '';
    }

    $showRight = $rightLabel !== '';
    $showLeft = $leftLabel !== '';

    $visibleCount =
        ($showRight ? 1 : 0)
        + ($showLeft ? 1 : 0);

    $cellWidth =
        $visibleCount === 1
            ? '100%'
            : '50%';
@endphp

@if($showRight || $showLeft)
    <table
        class="print-signature-table"
        cellpadding="0"
        cellspacing="0"
    >
        <tr>
            @if($showRight)
                <td style="width:{{ $cellWidth }}">
                    <div style="height:46px"></div>

                    <div class="print-signature-label">
                        {{ $rightLabel }}
                    </div>
                </td>
            @endif

            @if($showLeft)
                <td style="width:{{ $cellWidth }}">
                    @if(!empty($printTheme['signature_src']))
                        <img
                            src="{{ $printTheme['signature_src'] }}"
                            class="print-signature-image"
                            alt="التوقيع المعتمد"
                        >
                    @else
                        <div style="height:46px"></div>
                    @endif

                    <div class="print-signature-label">
                        {{ $leftLabel }}
                    </div>
                </td>
            @endif
        </tr>
    </table>
@endif
