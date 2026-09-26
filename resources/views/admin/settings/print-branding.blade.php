@extends('layouts.app')

@section('title', 'هوية المستندات والطباعة')

@section('content')
@php
    $template = old('print_template', $printTheme['template']);
    $primary = old('print_primary_color', $printTheme['primary_color']);
    $secondary = old('print_secondary_color', $printTheme['secondary_color']);
    $textColor = old('print_text_color', $printTheme['text_color']);
    $paperSize = old('print_paper_size', $printTheme['paper_size']);
    $logoPosition = old('print_logo_position', $printTheme['logo_position']);
    $logoSize = (int) old('print_logo_size', $printTheme['logo_size']);
@endphp

<style>
.pb-page{width:100%;max-width:1480px;margin:0 auto;padding-bottom:90px}
.pb-top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:16px}
.pb-layout{display:grid;grid-template-columns:minmax(560px,1fr) minmax(390px,470px);gap:18px;align-items:start}
.pb-main,.pb-side{min-width:0}
.pb-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-bottom:16px}
.pb-card-head{padding:14px 16px;border-bottom:1px solid var(--border)}
.pb-card-head h3{margin:0;font-size:.95rem}.pb-card-head p{margin:3px 0 0;color:var(--text-muted);font-size:.7rem}
.pb-card-body{padding:16px}.pb-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.pb-full{grid-column:1/-1}
.pb-templates{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.pb-template{position:relative;cursor:pointer}.pb-template input{position:absolute;opacity:0}
.pb-template-box{display:block;min-height:110px;padding:11px;border:1px solid var(--border);border-radius:12px;background:var(--surface);position:relative;overflow:hidden}
.pb-template-box:before{content:"";display:block;height:34px;margin-bottom:9px;border:1px solid #e5e7eb;border-radius:6px;border-top:4px solid var(--theme-primary);background:#fff}
.pb-template[data-template="classic"] .pb-template-box:before{border-top:6px double var(--theme-primary)}
.pb-template[data-template="minimal"] .pb-template-box:before{border:0;border-bottom:1px solid #e5e7eb;border-radius:0}
.pb-template input:checked + .pb-template-box{border-color:var(--theme-primary);box-shadow:0 0 0 2px color-mix(in srgb,var(--theme-primary) 12%,transparent)}
.pb-template input:checked + .pb-template-box:after{content:"✓";position:absolute;left:8px;top:8px;width:22px;height:22px;display:grid;place-items:center;border-radius:50%;background:var(--theme-primary);color:#fff;font-size:11px;font-weight:900}
.pb-template strong{display:block;font-size:.78rem}.pb-template small{display:block;color:var(--text-muted);font-size:.65rem;line-height:1.5;margin-top:4px}
.pb-color-row{display:grid;grid-template-columns:48px 1fr;gap:8px}.pb-color{width:48px;height:44px;border:1px solid var(--border);border-radius:10px;padding:3px;background:transparent}.pb-hex{direction:ltr;text-align:left;text-transform:uppercase}
.pb-file{display:grid;grid-template-columns:88px minmax(0,1fr);gap:12px;align-items:center;border:1px dashed var(--border);border-radius:12px;padding:11px;background:color-mix(in srgb,var(--surface) 93%,var(--background))}
.pb-file-preview{width:88px;height:72px;display:grid;place-items:center;background:#fff;border:1px solid var(--border);border-radius:9px;overflow:hidden;color:#9ca3af;font-size:.65rem}.pb-file-preview img{width:100%;height:100%;object-fit:contain}
.pb-file-meta{display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap;margin-top:7px;font-size:.67rem;color:var(--text-muted)}
.pb-range{display:grid;grid-template-columns:1fr 82px;gap:9px;align-items:center}.pb-range input[type=range]{width:100%;accent-color:var(--theme-primary)}
.pb-toggle{display:flex;justify-content:space-between;gap:14px;padding:11px 0;border-bottom:1px dashed var(--border)}.pb-toggle:last-child{border-bottom:0}.pb-toggle strong{display:block;font-size:.79rem}.pb-toggle small{display:block;margin-top:2px;color:var(--text-muted);font-size:.66rem}
.pb-switch{position:relative;width:46px;height:26px;flex:0 0 46px}.pb-switch input{opacity:0;width:0;height:0}.pb-slider{position:absolute;inset:0;background:#d7dce2;border-radius:999px;cursor:pointer}.pb-slider:before{content:"";position:absolute;width:20px;height:20px;right:3px;top:3px;border-radius:50%;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);transition:.2s}.pb-switch input:checked + .pb-slider{background:var(--theme-primary)}.pb-switch input:checked + .pb-slider:before{transform:translateX(-20px)}
.pb-side{position:sticky;top:14px}.pb-preview-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}.pb-live{display:inline-flex;gap:5px;align-items:center;padding:4px 8px;border-radius:999px;background:#ecfdf3;color:#08783e;font-size:.65rem;font-weight:800}.pb-live:before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
.pb-preview-wrap{padding:10px;border:1px solid var(--border);border-radius:15px;background:color-mix(in srgb,var(--surface) 92%,var(--background));overflow:hidden}
.pb-sheet{--p:{{$primary}};--s:{{$secondary}};--t:{{$textColor}};width:100%;min-height:610px;padding:22px;background:#fff;color:var(--t);border:1px solid #e5e7eb;border-top:4px solid var(--p);border-radius:10px;box-shadow:0 10px 28px rgba(15,23,42,.08);direction:rtl;overflow:hidden}
.pb-sheet.template-classic{border-top:7px double var(--p)}.pb-sheet.template-minimal{border:0;border-radius:0;box-shadow:none}
.pb-doc-head{display:grid;grid-template-columns:1fr auto 1fr;gap:11px;align-items:center;padding-bottom:12px;border-bottom:2px solid var(--p)}.pb-brand{min-width:0}.pb-logo{width:72px;height:56px;display:grid;place-items:center;overflow:hidden;background:#fff}.pb-logo img{width:100%;height:100%;object-fit:contain}.pb-name{font-size:10px;font-weight:900;color:var(--s);margin-top:4px}.pb-info{font-size:7.2px;line-height:1.5;color:#6b7280}.pb-title{text-align:center;font-size:17px;font-weight:900;color:var(--s)}.pb-meta{direction:ltr;text-align:left;font-size:8px;color:#6b7280}
.pb-person{margin-top:14px}.pb-person strong{font-size:13px;color:var(--s)}.pb-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin-top:14px}.pb-kpi{padding:8px;border:1px solid #e5e7eb;border-radius:7px}.pb-kpi small{display:block;font-size:7px;color:#6b7280}.pb-kpi strong{font-size:11px;color:var(--s)}.pb-table{width:100%;border-collapse:collapse;margin-top:14px}.pb-table th,.pb-table td{border:1px solid #e5e7eb;padding:6px;font-size:7px;text-align:right}.pb-table th{background:var(--s);color:#fff}.pb-signatures{display:flex;justify-content:space-between;gap:28px;margin-top:45px}.pb-sign{width:42%;border-top:1px solid #9ca3af;padding-top:6px;text-align:center;color:#6b7280;font-size:7px}.pb-stamp{display:none;width:64px;height:50px;margin:12px auto 0}.pb-stamp img{width:100%;height:100%;object-fit:contain}.pb-footer{margin-top:22px;padding-top:7px;border-top:1px solid #e5e7eb;text-align:center;font-size:7px;color:#9ca3af}
.pb-save{position:fixed;z-index:80;right:22px;left:22px;bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px;border:1px solid var(--border);border-radius:14px;background:color-mix(in srgb,var(--surface) 96%,transparent);box-shadow:0 8px 28px rgba(15,23,42,.12);backdrop-filter:blur(8px)}.pb-actions{display:flex;gap:8px;align-items:center}.pb-saving{opacity:.65;pointer-events:none}
@media(max-width:900px){.pb-layout{grid-template-columns:1fr}.pb-side{position:static;order:-1}.pb-save{right:12px;left:12px}}
@media(max-width:650px){.pb-top,.pb-save{flex-direction:column;align-items:stretch}.pb-grid,.pb-templates{grid-template-columns:1fr}.pb-full{grid-column:auto}.pb-file{grid-template-columns:1fr}.pb-file-preview{width:100%}}
</style>

<div class="pb-page">
    <div class="pb-top">
        <div>
            <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px">
                <a href="{{ url('/settings') }}" style="color:var(--theme-primary);text-decoration:none">إعدادات النظام</a>
                <span> ‹ </span>
                <span>هوية المستندات والطباعة</span>
            </div>
            <h1 class="page-heading">هوية المستندات والطباعة</h1>
            <p class="page-subheading">هوية مركزية للفواتير والسندات والقسائم وكشوف الحساب.</p>
        </div>
        <a class="btn btn-outline" href="{{ route('settings.print-branding.preview') }}" target="_blank">معاينة كاملة</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:14px">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom:14px">
            <strong>لم يتم الحفظ:</strong>
            <ul style="margin:6px 0 0;padding-right:18px">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.print-branding.update') }}" enctype="multipart/form-data" id="pbForm">
        @csrf
        @method('PUT')

        <div class="pb-layout">
            <div class="pb-main">
                <div class="pb-card">
                    <div class="pb-card-head"><h3>نمط المستند</h3><p>اختر الشكل العام لكل المستندات.</p></div>
                    <div class="pb-card-body">
                        <div class="pb-templates">
                            @foreach([
                                'modern'=>['حديث','مساحات بيضاء وهوية واضحة.'],
                                'classic'=>['كلاسيكي','رأس رسمي وإطار تقليدي.'],
                                'minimal'=>['بسيط','أقل عناصر للطباعة النظيفة.'],
                            ] as $value => [$label,$desc])
                                <label class="pb-template" data-template="{{ $value }}">
                                    <input type="radio" name="print_template" value="{{ $value }}" @checked($template === $value)>
                                    <span class="pb-template-box"><strong>{{ $label }}</strong><small>{{ $desc }}</small></span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="pb-card">
                    <div class="pb-card-head"><h3>الألوان وحجم الورق</h3><p>تتغير مباشرة في المعاينة.</p></div>
                    <div class="pb-card-body">
                        <div class="pb-grid">
                            @foreach([
                                ['primary','اللون الرئيسي','print_primary_color',$primary,'--p'],
                                ['secondary','اللون الثانوي','print_secondary_color',$secondary,'--s'],
                                ['text','لون النص','print_text_color',$textColor,'--t'],
                            ] as [$key,$label,$name,$value,$css])
                                <div class="form-group">
                                    <label class="form-label">{{ $label }}</label>
                                    <div class="pb-color-row">
                                        <input class="pb-color js-picker" type="color" value="{{ $value }}" data-key="{{ $key }}" data-css="{{ $css }}">
                                        <input class="form-input pb-hex js-hex" type="text" name="{{ $name }}" value="{{ $value }}" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" data-key="{{ $key }}" data-css="{{ $css }}" required>
                                    </div>
                                </div>
                            @endforeach
                            <div class="form-group">
                                <label class="form-label">حجم الورق</label>
                                <select class="form-input" name="print_paper_size" required>
                                    @foreach(['A4'=>'A4','A5'=>'A5','80mm'=>'حراري 80mm'] as $value=>$label)
                                        <option value="{{ $value }}" @selected($paperSize === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pb-card">
                    <div class="pb-card-head"><h3>الشعار والختم والتوقيع</h3><p>مصدر مركزي واحد لكل التقارير والمستندات المطبوعة.</p></div>
                    <div class="pb-card-body">
                        <div class="pb-grid">
                            <div class="form-group pb-full">
                                <label class="form-label">شعار المستندات</label>
                                <div class="pb-file">
                                    <div class="pb-file-preview" id="logoPreviewBox">
                                        @if(!empty($printTheme['logo_src']))<img src="{{ $printTheme['logo_src'] }}" id="logoPreviewImage" alt="Logo">@else LOGO @endif
                                    </div>
                                    <div>
                                        <input class="form-input" type="file" name="print_logo_file" id="logoFile" accept=".jpg,.jpeg,.png,.webp">
                                        <div class="pb-file-meta">
                                            <span>PNG / JPG / WEBP — حتى 4MB</span>
                                            <label><input type="checkbox" name="remove_print_logo" value="1"> إزالة شعار الطباعة</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">موضع الشعار</label>
                                <select class="form-input" name="print_logo_position" id="logoPosition" required>
                                    @foreach(['right'=>'يمين','center'=>'وسط','left'=>'يسار'] as $value=>$label)
                                        <option value="{{ $value }}" @selected($logoPosition === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">حجم الشعار</label>
                                <div class="pb-range">
                                    <input type="range" id="logoRange" min="40" max="180" value="{{ $logoSize }}">
                                    <input class="form-input" type="number" name="print_logo_size" id="logoSize" min="40" max="180" value="{{ $logoSize }}" required>
                                </div>
                            </div>
                            <div class="form-group pb-full">
                                <label class="form-label">صورة الختم</label>
                                <div class="pb-file">
                                    <div class="pb-file-preview" id="stampPreviewBox">
                                        @if(!empty($printTheme['stamp_src']))<img src="{{ $printTheme['stamp_src'] }}" id="stampPreviewImage" alt="Stamp">@else STAMP @endif
                                    </div>
                                    <div>
                                        <input class="form-input" type="file" name="print_stamp_file" id="stampFile" accept=".jpg,.jpeg,.png,.webp">
                                        <div class="pb-file-meta">
                                            <span>يفضل PNG بخلفية شفافة</span>
                                            <label><input type="checkbox" name="remove_print_stamp" value="1"> إزالة ختم الطباعة</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group pb-full">
                                <label class="form-label">التوقيع المعتمد</label>
                                <div class="pb-file">
                                    <div class="pb-file-preview" id="signaturePreviewBox">
                                        @if(!empty($printTheme['signature_src']))<img src="{{ $printTheme['signature_src'] }}" id="signaturePreviewImage" alt="Signature">@else SIGN @endif
                                    </div>
                                    <div>
                                        <input class="form-input" type="file" name="print_signature_file" id="signatureFile" accept=".jpg,.jpeg,.png,.webp">
                                        <div class="pb-file-meta">
                                            <span>يفضل PNG بخلفية شفافة للتوقيع</span>
                                            <label><input type="checkbox" name="remove_print_signature" value="1"> إزالة التوقيع</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pb-card">
                    <div class="pb-card-head"><h3>بيانات المنشأة</h3><p>تظهر في رأس المستندات.</p></div>
                    <div class="pb-card-body">
                        <div class="pb-grid">
                            <div class="form-group pb-full"><label class="form-label">الاسم القانوني / التجاري</label><input class="form-input js-text" name="business_legal_name" value="{{ old('business_legal_name',$printTheme['business_name']) }}" data-target="#businessName"></div>
                            <div class="form-group"><label class="form-label">الهاتف</label><input class="form-input js-text" name="business_phone" value="{{ old('business_phone',$printTheme['business_phone']) }}" data-target="#businessPhone"></div>
                            <div class="form-group"><label class="form-label">البريد الإلكتروني</label><input class="form-input js-text" type="email" name="business_email" value="{{ old('business_email',$printTheme['business_email']) }}" data-target="#businessEmail"></div>
                            <div class="form-group"><label class="form-label">الرقم الضريبي</label><input class="form-input js-text" name="business_tax_number" value="{{ old('business_tax_number',$printTheme['business_tax_number']) }}" data-target="#businessTax"></div>
                            <div class="form-group"><label class="form-label">العنوان</label><input class="form-input js-text" name="business_address" value="{{ old('business_address',$printTheme['business_address']) }}" data-target="#businessAddress"></div>
                            <div class="form-group pb-full"><label class="form-label">نص أسفل المستند</label><textarea class="form-input js-text" name="print_footer_text" rows="3" maxlength="500" data-target="#footerText">{{ old('print_footer_text',$printTheme['footer_text']) }}</textarea></div>
                        </div>
                    </div>
                </div>

                <div class="pb-card">
                    <div class="pb-card-head"><h3>العناصر الظاهرة</h3><p>تشغيل وإخفاء عناصر المستند.</p></div>
                    <div class="pb-card-body">
                        @foreach([
                            ['print_show_logo','إظهار الشعار','logo',$printTheme['show_logo']],
                            ['print_show_business_info','إظهار بيانات المنشأة','business',$printTheme['show_business_info']],
                            ['print_show_document_number','إظهار رقم المستند','number',$printTheme['show_document_number']],
                            ['print_show_signatures','إظهار التواقيع','signatures',$printTheme['show_signatures']],
                            ['print_show_stamp','إظهار الختم','stamp',$printTheme['show_stamp']],
                            ['print_show_footer','إظهار التذييل','footer',$printTheme['show_footer']],
                        ] as [$name,$label,$target,$checked])
                            <div class="pb-toggle">
                                <div><strong>{{ $label }}</strong><small>يطبق على المستندات التي تستخدم القالب المركزي.</small></div>
                                <label class="pb-switch"><input class="js-toggle" type="checkbox" name="{{ $name }}" value="1" data-target="{{ $target }}" @checked(old($name,$checked))><span class="pb-slider"></span></label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <aside class="pb-side">
                <div class="pb-preview-head"><div><strong>معاينة مباشرة</strong><small class="text-muted" style="display:block">تتغير فوراً قبل الحفظ</small></div><span class="pb-live">Live</span></div>
                <div class="pb-preview-wrap">
                    <div class="pb-sheet template-{{ $template }}" id="sheet">
                        <div class="pb-doc-head" id="docHead">
                            <div class="pb-brand" id="docBrand">
                                <div class="pb-logo" id="docLogo" style="width:{{ min(112,max(52,$logoSize*.72)) }}px">
                                    @if(!empty($printTheme['logo_src']))<img src="{{ $printTheme['logo_src'] }}" id="docLogoImage" alt="Logo">@else LOGO @endif
                                </div>
                                <div class="pb-name" id="businessName">{{ $printTheme['business_name'] ?: 'اسم المنشأة' }}</div>
                                <div class="pb-info" id="businessInfo"><div id="businessAddress">{{ $printTheme['business_address'] ?: 'عنوان المنشأة' }}</div><div><span id="businessPhone">{{ $printTheme['business_phone'] ?: 'الهاتف' }}</span> · <span id="businessEmail">{{ $printTheme['business_email'] ?: 'البريد' }}</span></div><div>الرقم الضريبي: <span id="businessTax">{{ $printTheme['business_tax_number'] ?: '—' }}</span></div></div>
                            </div>
                            <div class="pb-title">كشف حساب موظف</div>
                            <div class="pb-meta"><div id="documentNumber">STMT-0006</div><div>{{ now()->format('Y-m-d') }}</div></div>
                        </div>
                        <div class="pb-person"><strong>تامر سلامة</strong><div class="pb-info">EMP-006 · كاشير</div></div>
                        <div class="pb-kpis"><div class="pb-kpi"><small>الرصيد الافتتاحي</small><strong>0.00</strong></div><div class="pb-kpi"><small>إجمالي مدين</small><strong>500.00</strong></div><div class="pb-kpi"><small>الرصيد الختامي</small><strong>-500.00</strong></div></div>
                        <table class="pb-table"><thead><tr><th>التاريخ</th><th>البيان</th><th>دائن</th><th>مدين</th><th>الرصيد</th></tr></thead><tbody><tr><td>{{ now()->format('Y-m-d') }}</td><td>سلفة موظف</td><td>—</td><td>500.00</td><td>-500.00</td></tr></tbody></table>
                        <div class="pb-signatures" id="signatures"><div class="pb-sign">توقيع المستلم</div><div class="pb-sign"><div id="signatureLive">@if(!empty($printTheme['signature_src']))<img src="{{ $printTheme['signature_src'] }}" id="signatureLiveImage" alt="Signature" style="display:block;max-width:80px;max-height:34px;margin:0 auto 4px">@endif</div>اعتماد الإدارة</div></div>
                        <div class="pb-stamp" id="stamp" @if($printTheme['show_stamp'] && !empty($printTheme['stamp_src'])) style="display:block" @endif>@if(!empty($printTheme['stamp_src']))<img src="{{ $printTheme['stamp_src'] }}" id="stampImage" alt="Stamp">@endif</div>
                        <div class="pb-footer" id="footer"><span id="footerText">{{ $printTheme['footer_text'] ?: 'نص التذييل يظهر هنا' }}</span></div>
                    </div>
                </div>
                <a class="btn btn-outline" style="margin-top:10px" href="{{ route('settings.print-branding.preview') }}" target="_blank">فتح معاينة كاملة</a>
            </aside>
        </div>

        <div class="pb-save">
            <small class="text-muted">المعاينة لحظية، والحفظ يعتمد التغييرات نهائياً.</small>
            <div class="pb-actions"><button class="btn btn-gold" type="submit" id="saveButton">حفظ هوية المستندات</button></div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('pbForm');
    const sheet = document.getElementById('sheet');
    const saveButton = document.getElementById('saveButton');
    const hex = v => /^#[0-9A-F]{6}$/.test(String(v || '').trim().toUpperCase()) ? String(v).trim().toUpperCase() : null;

    function syncColor(key, value, css) {
        const v = hex(value); if (!v) return;
        const picker = document.querySelector(`.js-picker[data-key="${key}"]`);
        const input = document.querySelector(`.js-hex[data-key="${key}"]`);
        if (picker) picker.value = v; if (input) { input.value = v; input.setCustomValidity(''); }
        sheet.style.setProperty(css, v);
    }
    document.querySelectorAll('.js-picker').forEach(el => el.addEventListener('input', () => syncColor(el.dataset.key, el.value, el.dataset.css)));
    document.querySelectorAll('.js-hex').forEach(el => el.addEventListener('input', () => { const v = hex(el.value); el.setCustomValidity(v ? '' : 'HEX غير صحيح'); if (v) syncColor(el.dataset.key, v, el.dataset.css); }));

    function applyTemplate() {
        const value = document.querySelector('input[name="print_template"]:checked')?.value || 'modern';
        sheet.classList.remove('template-modern','template-classic','template-minimal'); sheet.classList.add(`template-${value}`);
    }
    document.querySelectorAll('input[name="print_template"]').forEach(el => el.addEventListener('change', applyTemplate));

    document.querySelectorAll('.js-text').forEach(el => el.addEventListener('input', () => {
        const target = document.querySelector(el.dataset.target); if (!target) return;
        const fallbacks = {business_legal_name:'اسم المنشأة',business_phone:'الهاتف',business_email:'البريد',business_address:'عنوان المنشأة',business_tax_number:'—',print_footer_text:'نص التذييل يظهر هنا'};
        target.textContent = el.value.trim() || fallbacks[el.name] || '—';
    }));

    const pos = document.getElementById('logoPosition'), head = document.getElementById('docHead'), brand = document.getElementById('docBrand'), meta = document.querySelector('.pb-meta');
    function applyPosition() {
        const v = pos.value; head.style.gridTemplateColumns = '1fr auto 1fr'; brand.style.gridColumn = ''; meta.style.gridColumn = '';
        if (v === 'left') { brand.style.gridColumn='3'; brand.style.textAlign='left'; meta.style.gridColumn='1'; meta.style.textAlign='right'; }
        else if (v === 'center') { head.style.gridTemplateColumns='1fr 1fr'; brand.style.gridColumn='1/-1'; brand.style.textAlign='center'; meta.style.gridColumn='2'; meta.style.textAlign='left'; }
        else { brand.style.gridColumn='1'; brand.style.textAlign='right'; meta.style.gridColumn='3'; meta.style.textAlign='left'; }
    }
    pos.addEventListener('change', applyPosition);

    const size = document.getElementById('logoSize'), range = document.getElementById('logoRange'), logo = document.getElementById('docLogo');
    function applySize(v) { const n = Math.max(40,Math.min(180,Number(v || 90))); size.value=n; range.value=n; logo.style.width=`${Math.min(112,Math.max(52,n*.72))}px`; }
    size.addEventListener('input',()=>applySize(size.value)); range.addEventListener('input',()=>applySize(range.value));

    function filePreview(input, previewBoxId, previewImgId, liveBoxId, liveImgId) {
        const file = input.files?.[0]; if (!file) return; const reader = new FileReader();
        reader.onload = () => {
            const box = document.getElementById(previewBoxId); let img = document.getElementById(previewImgId);
            if (!img) { box.innerHTML=''; img=document.createElement('img'); img.id=previewImgId; box.appendChild(img); } img.src=reader.result;
            if (liveBoxId) { const liveBox=document.getElementById(liveBoxId); let liveImg=document.getElementById(liveImgId); if (!liveImg) { liveBox.innerHTML=''; liveImg=document.createElement('img'); liveImg.id=liveImgId; liveBox.appendChild(liveImg); } liveImg.src=reader.result; }
        }; reader.readAsDataURL(file);
    }
    document.getElementById('logoFile').addEventListener('change', e => filePreview(e.currentTarget,'logoPreviewBox','logoPreviewImage','docLogo','docLogoImage'));
    document.getElementById('stampFile').addEventListener('change', e => { filePreview(e.currentTarget,'stampPreviewBox','stampPreviewImage','stamp','stampImage'); const on=document.querySelector('.js-toggle[data-target="stamp"]')?.checked; if (on) document.getElementById('stamp').style.display='block'; });
    document.getElementById('signatureFile').addEventListener('change', e => {
        filePreview(
            e.currentTarget,
            'signaturePreviewBox',
            'signaturePreviewImage',
            'signatureLive',
            'signatureLiveImage'
        );
    });

    function applyToggle(el) {
        const on = el.checked;
        if (el.dataset.target === 'logo') document.getElementById('docLogo').style.display = on ? 'grid':'none';
        if (el.dataset.target === 'business') { document.getElementById('businessName').hidden=!on; document.getElementById('businessInfo').hidden=!on; }
        if (el.dataset.target === 'number') document.getElementById('documentNumber').hidden=!on;
        if (el.dataset.target === 'signatures') document.getElementById('signatures').style.display = on ? 'flex':'none';
        if (el.dataset.target === 'stamp') document.getElementById('stamp').style.display = on && document.getElementById('stampImage') ? 'block':'none';
        if (el.dataset.target === 'footer') document.getElementById('footer').style.display = on ? 'block':'none';
    }
    document.querySelectorAll('.js-toggle').forEach(el => { applyToggle(el); el.addEventListener('change',()=>applyToggle(el)); });

    form.addEventListener('submit', () => { saveButton.disabled=true; saveButton.textContent='جاري الحفظ...'; form.classList.add('pb-saving'); });
    applyTemplate(); applyPosition(); applySize(size.value);
});
</script>
@endsection