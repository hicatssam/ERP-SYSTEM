@php
    use App\Enums\CommissionBase;
    use App\Enums\DeliveryFeeRecipient;
    use App\Enums\DiscountType;
    use App\Enums\SalesChannelType;
    use App\Enums\SettlementCycle;
    $channel = $channel ?? new \App\Models\SalesChannel();
    $enumValue = fn ($value, $fallback = '') => $value instanceof \BackedEnum ? $value->value : ($value ?? $fallback);
@endphp

@if($errors->any())
    <div class="sc-alert sc-alert-danger">
        <strong>يرجى تصحيح البيانات التالية:</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="sc-form-grid">
    <section class="card sc-section">
        <div class="card-header"><span class="card-title">البيانات الأساسية</span></div>
        <div class="card-body sc-grid sc-grid-2">
            <div class="form-group">
                <label class="form-label">اسم القناة *</label>
                <input name="name" class="form-input" value="{{ old('name', $channel->name) }}" required maxlength="150">
                @error('name')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">المعرّف Slug</label>
                <input name="slug" class="form-input" value="{{ old('slug', $channel->slug) }}" placeholder="يُنشأ تلقائيًا عند تركه فارغًا">
                @error('slug')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">نوع القناة *</label>
                <select name="type" class="form-select" required>
                    @foreach(SalesChannelType::cases() as $case)
                        <option value="{{ $case->value }}" @selected(old('type', $enumValue($channel->type, 'direct')) === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">ترتيب الظهور</label>
                <input type="number" name="sort_order" class="form-input" min="0" max="9999" value="{{ old('sort_order', $channel->sort_order ?? 0) }}">
            </div>
            <div class="form-group sc-span-2">
                <label class="form-label">الوصف</label>
                <textarea name="description" class="form-textarea" rows="3">{{ old('description', $channel->description) }}</textarea>
            </div>
            @php
    $logoUrl = $channel->logo_url;
    $logoInitials = mb_strtoupper(mb_substr($channel->name ?: 'قناة', 0, 2));
@endphp

<div class="form-group sc-logo-field">
    <label class="form-label" for="channel-logo">شعار القناة</label>

    <div class="sc-logo-upload">
        <div class="sc-logo-preview">
            @if($logoUrl)
                <img
                    id="channel-logo-preview"
                    src="{{ $logoUrl }}"
                    alt="شعار {{ $channel->name }}"
                    onerror="this.hidden = true; this.nextElementSibling.hidden = false;"
                >
            @else
                <img id="channel-logo-preview" alt="" hidden>
            @endif

            <span
                id="channel-logo-fallback"
                class="sc-logo-fallback"
                @if($logoUrl) hidden @endif
            >
                {{ $logoInitials }}
            </span>
        </div>

        <div style="flex:1">
            <input
                id="channel-logo"
                type="file"
                name="logo"
                class="form-input"
                accept="image/jpeg,image/png,image/webp"
            >

            <small class="form-help">
                JPG أو PNG أو WEBP، بحد أقصى 4MB.
            </small>

            @if($channel->exists && $channel->logo)
                <small class="form-help">
                    الشعار الحالي: {{ basename($channel->logo) }}
                </small>
            @endif

            @error('logo')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>
            <div class="form-group sc-toggle-wrap">
                <input type="hidden" name="is_active" value="0">
                <label class="sc-toggle">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $channel->exists ? $channel->is_active : true))>
                    <span></span><strong>القناة مفعّلة</strong>
                </label>
                @if($channel->exists && $channel->logo)
                    <label class="sc-check"><input type="checkbox" name="remove_logo" value="1"> حذف الشعار الحالي</label>
                @endif
            </div>
        </div>
    </section>

    <section class="card sc-section">
        <div class="card-header"><span class="card-title">خصم العميل وتحمل التكلفة</span></div>
        <div class="card-body sc-grid sc-grid-3">
            <div class="form-group">
                <label class="form-label">نوع الخصم *</label>
                <select name="discount_type" class="form-select" required>
                    @foreach(DiscountType::cases() as $case)
                        <option value="{{ $case->value }}" @selected(old('discount_type', $enumValue($channel->discount_type, 'percentage')) === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">قيمة الخصم *</label>
                <input type="number" name="discount_value" class="form-input" min="0" step="0.001" value="{{ old('discount_value', $channel->discount_value ?? 0) }}" required>
                @error('discount_value')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">نسبة ما تتحمله القناة من الخصم *</label>
                <div class="sc-suffix"><input type="number" name="discount_funded_by_channel" class="form-input" min="0" max="100" step="0.01" value="{{ old('discount_funded_by_channel', $channel->discount_funded_by_channel ?? 0) }}" required><span>%</span></div>
                <small class="form-help">0%: المطعم يتحمل الخصم كاملًا. 100%: التطبيق يتحمله كاملًا.</small>
            </div>
        </div>
    </section>

    <section class="card sc-section">
        <div class="card-header"><span class="card-title">العمولة والتسوية</span></div>
        <div class="card-body sc-grid sc-grid-3">
            <div class="form-group">
                <label class="form-label">نوع العمولة *</label>
                <select name="commission_type" class="form-select" required>
                    @foreach(DiscountType::cases() as $case)
                        <option value="{{ $case->value }}" @selected(old('commission_type', $enumValue($channel->commission_type, 'percentage')) === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">قيمة العمولة *</label>
                <input type="number" name="commission_value" class="form-input" min="0" step="0.001" value="{{ old('commission_value', $channel->commission_value ?? 0) }}" required>
                @error('commission_value')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">تُحسب العمولة على *</label>
                <select name="commission_base" class="form-select" required>
                    @foreach(CommissionBase::cases() as $case)
                        <option value="{{ $case->value }}" @selected(old('commission_base', $enumValue($channel->commission_base, 'net_sales')) === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">مستفيد رسوم التوصيل *</label>
                <select name="delivery_fee_recipient" class="form-select" required>
                    @foreach(DeliveryFeeRecipient::cases() as $case)
                        <option value="{{ $case->value }}" @selected(old('delivery_fee_recipient', $enumValue($channel->delivery_fee_recipient, 'restaurant')) === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">دورة التسوية *</label>
                <select name="settlement_cycle" class="form-select" required>
                    @foreach(SettlementCycle::cases() as $case)
                        <option value="{{ $case->value }}" @selected(old('settlement_cycle', $enumValue($channel->settlement_cycle, 'monthly')) === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">مهلة التحويل بالأيام *</label>
                <input type="number" name="settlement_days" class="form-input" min="0" max="365" value="{{ old('settlement_days', $channel->settlement_days ?? 0) }}" required>
            </div>
        </div>
    </section>

    <section class="card sc-section">
        <div class="card-header"><span class="card-title">الربط والملاحظات</span></div>
        <div class="card-body sc-grid sc-grid-2">
            <div class="form-group sc-span-2">
                <label class="form-label">API Key</label>
                <input type="password" name="api_key" class="form-input" autocomplete="new-password" placeholder="{{ $channel->exists && $channel->api_key ? 'اتركه فارغًا للإبقاء على المفتاح الحالي' : 'اختياري' }}">
                <small class="form-help">يُشفّر داخل قاعدة البيانات ولا يظهر في القوائم أو السجلات.</small>
                @if($channel->exists && $channel->api_key)<label class="sc-check"><input type="checkbox" name="remove_api_key" value="1"> حذف المفتاح الحالي</label>@endif
            </div>
            <div class="form-group sc-span-2">
                <label class="form-label">ملاحظات العقد أو التسوية</label>
                <textarea name="notes" class="form-textarea" rows="4">{{ old('notes', $channel->notes) }}</textarea>
            </div>
        </div>
    </section>
</div>

<style>
.sc-form-grid{display:grid;gap:1.25rem}.sc-section{overflow:visible}.sc-grid{display:grid;gap:1rem}.sc-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}.sc-grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}.sc-span-2{grid-column:1/-1}.sc-alert{margin-bottom:1rem;padding:1rem 1.2rem;border-radius:12px}.sc-alert-danger{color:#b42318;background:#fff1f0;border:1px solid #fecdca}.sc-alert ul{margin:.5rem 0 0}.form-error{display:block;margin-top:.35rem;color:#dc3545;font-size:.82rem}.form-help{display:block;margin-top:.35rem;color:var(--text-muted);font-size:.78rem}.sc-toggle-wrap{display:flex;flex-direction:column;justify-content:center;gap:.6rem}.sc-toggle{display:flex;align-items:center;gap:.65rem;cursor:pointer}.sc-toggle>input{position:absolute;opacity:0}.sc-toggle span{width:44px;height:24px;padding:3px;background:#aaa;border-radius:20px;transition:.2s}.sc-toggle span:after{content:'';display:block;width:18px;height:18px;background:#fff;border-radius:50%;transition:.2s}.sc-toggle input:checked+span{background:var(--gold)}.sc-toggle input:checked+span:after{transform:translateX(-20px)}.sc-check{display:flex;align-items:center;gap:.4rem;color:var(--text-muted);font-size:.85rem}.sc-suffix{position:relative}.sc-suffix span{position:absolute;inset-inline-end:14px;top:50%;transform:translateY(-50%);color:var(--gold);font-weight:800}.sc-suffix input{padding-inline-end:38px}@media(max-width:850px){.sc-grid-2,.sc-grid-3{grid-template-columns:1fr}.sc-span-2{grid-column:auto}}


.sc-logo-field {
    grid-column: span 1;
}

.sc-logo-upload {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.sc-logo-preview {
    width: 84px;
    height: 84px;
    flex: 0 0 84px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid rgba(188, 145, 48, .3);
    border-radius: 14px;
    background: var(--gold-ultra);
}

.sc-logo-preview img {
    display: block;
    width: 100%;
    height: 100%;
    padding: 6px;
    object-fit: contain;
    background: #fff;
}

.sc-logo-fallback {
    color: var(--gold);
    font-size: 1.15rem;
    font-weight: 800;
}
</style>

<script>
    (() => {
        const input = document.getElementById('channel-logo');
        const preview = document.getElementById('channel-logo-preview');
        const fallback = document.getElementById('channel-logo-fallback');

        if (!input || !preview || !fallback) {
            return;
        }

        input.addEventListener('change', () => {
            const file = input.files?.[0];

            if (!file || !file.type.startsWith('image/')) {
                return;
            }

            preview.src = URL.createObjectURL(file);
            preview.hidden = false;
            fallback.hidden = true;
        });
    })();
</script>