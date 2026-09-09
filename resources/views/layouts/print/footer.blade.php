<table
    class="print-footer"
    cellpadding="0"
    cellspacing="0"
>
    <tr>
        <td style="text-align:right">
            {{ $printTheme['business_name'] }}

            @if(!empty($printTheme['footer_text']))
                ·
                {{ $printTheme['footer_text'] }}
            @endif
        </td>

        <td style="text-align:left" dir="ltr">
            {{ now()->format('d/m/Y H:i') }}
        </td>
    </tr>
</table>
