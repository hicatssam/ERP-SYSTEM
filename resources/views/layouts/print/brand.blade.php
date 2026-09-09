@if(
    $printTheme['show_logo']
    && !empty($printTheme['logo_src'])
)
    <img
        src="{{ $printTheme['logo_src'] }}"
        class="print-logo"
        alt="{{ $printTheme['business_name'] }}"
        style="width:{{ (int) $printTheme['logo_size'] }}px"
    >
@endif

@if($printTheme['show_business_info'])
    <div class="print-business-name">
        {{ $printTheme['business_name'] }}
    </div>

    @if(!empty($printTheme['business_name_en']))
        <div class="print-business-name-en">
            {{ $printTheme['business_name_en'] }}
        </div>
    @endif

    @if(!empty($printTheme['business_tagline']))
        <div class="print-business-tagline">
            {{ $printTheme['business_tagline'] }}
        </div>
    @endif

    <div class="print-business-info">
        @if(!empty($printTheme['business_address']))
            <div>
                {{ $printTheme['business_address'] }}
            </div>
        @endif

        @if(
            !empty($printTheme['business_phone'])
            || !empty($printTheme['business_email'])
        )
            <div>
                @if(!empty($printTheme['business_phone']))
                    <span dir="ltr">
                        {{ $printTheme['business_phone'] }}
                    </span>
                @endif

                @if(
                    !empty($printTheme['business_phone'])
                    && !empty($printTheme['business_email'])
                )
                    ·
                @endif

                @if(!empty($printTheme['business_email']))
                    <span dir="ltr">
                        {{ $printTheme['business_email'] }}
                    </span>
                @endif
            </div>
        @endif

        @if(!empty($printTheme['business_tax_number']))
            <div>
                الرقم الضريبي:
                {{ $printTheme['business_tax_number'] }}
            </div>
        @endif
    </div>
@endif
