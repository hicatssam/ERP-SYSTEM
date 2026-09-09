@php
    $isEdit = $currency->exists;
@endphp

<div class="card">
    <div class="card-header">
        <span class="card-title">
            {{ $isEdit ? 'بيانات العملة' : 'إضافة عملة جديدة' }}
        </span>
    </div>

    <div class="card-body">
        <div class="currency-form-grid">
            <div class="form-group">
                <label class="form-label">كود العملة *</label>

                <input
                    class="form-input"
                    name="code"
                    value="{{ old('code', $currency->code) }}"
                    maxlength="10"
                    placeholder="مثال: USD"
                    dir="ltr"
                    required
                >

                <small class="currency-help">
                    الكود المختصر للعملة.
                    مثال: ILS أو USD أو EUR.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">اسم العملة بالعربية *</label>

                <input
                    class="form-input"
                    name="name_ar"
                    value="{{ old('name_ar', $currency->name_ar) }}"
                    maxlength="120"
                    placeholder="مثال: دولار أمريكي"
                    required
                >

                <small class="currency-help">
                    الاسم الذي سيظهر للمستخدمين داخل النظام.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">اسم العملة بالإنجليزية</label>

                <input
                    class="form-input"
                    name="name_en"
                    value="{{ old('name_en', $currency->name_en) }}"
                    maxlength="120"
                    placeholder="مثال: US Dollar"
                    dir="ltr"
                >

                <small class="currency-help">
                    اختياري، ومفيد للتقارير أو الواجهات الإنجليزية لاحقًا.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">رمز العملة *</label>

                <input
                    class="form-input"
                    name="symbol"
                    value="{{ old('symbol', $currency->symbol) }}"
                    maxlength="20"
                    placeholder="مثال: $ أو ₪ أو €"
                    required
                >

                <small class="currency-help">
                    الرمز المختصر الذي يمثل العملة.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">أيقونة العملة</label>

                <input
                    class="form-input"
                    name="icon"
                    value="{{ old('icon', $currency->icon) }}"
                    maxlength="50"
                    placeholder="مثال: $ أو € أو SAR"
                >

                <small class="currency-help">
                    اختياري. يمكن أن تكون Emoji أو رمزًا نصيًا صغيرًا.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">عدد الخانات العشرية *</label>

                <input
                    class="form-input"
                    type="number"
                    name="decimal_places"
                    value="{{ old('decimal_places', $currency->decimal_places ?? 2) }}"
                    min="0"
                    max="6"
                    dir="ltr"
                    required
                >

                <small class="currency-help">
                    غالبًا 2. بعض العملات مثل الدينار قد تستخدم 3 خانات.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">ترتيب العرض</label>

                <input
                    class="form-input"
                    type="number"
                    name="sort_order"
                    value="{{ old('sort_order', $currency->sort_order ?? 0) }}"
                    min="0"
                    dir="ltr"
                >

                <small class="currency-help">
                    الرقم الأصغر يظهر أولًا في قائمة العملات.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">صورة / علم العملة</label>

                <input
                    class="form-input"
                    type="file"
                    name="image"
                    accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                >

                <small class="currency-help">
                    اختياري. PNG / JPG / WEBP، بحد أقصى 2MB.
                </small>

                @if($isEdit && $currency->image_url)
                    <div style="margin-top:.65rem">
                        <img
                            src="{{ $currency->image_url }}"
                            alt="{{ $currency->display_name }}"
                            style="width:54px;height:54px;object-fit:contain;border:1px solid var(--theme-border);border-radius:10px;padding:5px"
                        >
                    </div>
                @endif
            </div>
        </div>

        <div class="currency-toggles">
            <label class="currency-switch-card">
                <input
                    type="hidden"
                    name="is_active"
                    value="0"
                >

                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    @checked(old('is_active', $currency->is_active ?? true))
                >

                <span>
                    <strong>العملة فعالة</strong>
                    <small>إظهار العملة ضمن وحدة إدارة العملات.</small>
                </span>
            </label>

            <label class="currency-switch-card">
                <input
                    type="hidden"
                    name="is_default"
                    value="0"
                >

                <input
                    type="checkbox"
                    name="is_default"
                    value="1"
                    @checked(old('is_default', $currency->is_default ?? false))
                >

                <span>
                    <strong>عملة افتراضية</strong>
                    <small>
                        إعداد داخلي لهذه الوحدة فقط حاليًا، ولا يغيّر أي فواتير أو أسعار موجودة.
                    </small>
                </span>
            </label>
        </div>

        <div class="form-group" style="margin-top:1rem">
            <label class="form-label">ملاحظات</label>

            <textarea
                class="form-input"
                name="notes"
                rows="3"
                maxlength="2000"
                placeholder="أي ملاحظات داخلية عن العملة..."
            >{{ old('notes', $currency->notes) }}</textarea>
        </div>
    </div>
</div>

<style>
    .currency-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .currency-help {
        display: block;
        margin-top: .35rem;
        color: var(--theme-text-muted, #6b7280);
        font-size: .78rem;
        line-height: 1.55;
    }

    .currency-toggles {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .85rem;
        margin-top: 1rem;
    }

    .currency-switch-card {
        display: flex;
        align-items: flex-start;
        gap: .7rem;
        padding: .9rem;
        border: 1px solid var(--theme-border, #e5e7eb);
        border-radius: var(--theme-radius, 10px);
        cursor: pointer;
        background: var(--theme-surface, #fff);
    }

    .currency-switch-card input[type="checkbox"] {
        margin-top: .2rem;
    }

    .currency-switch-card strong,
    .currency-switch-card small {
        display: block;
    }

    .currency-switch-card small {
        margin-top: .2rem;
        color: var(--theme-text-muted, #6b7280);
        line-height: 1.5;
    }

    @media(max-width: 800px) {
        .currency-form-grid,
        .currency-toggles {
            grid-template-columns: 1fr;
        }
    }
</style>
