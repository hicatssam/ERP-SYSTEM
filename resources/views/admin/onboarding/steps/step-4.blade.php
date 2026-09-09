@php($data = $run->operational_data ?? [])

<form method="POST" action="{{ route('onboarding.step.save', [$run, 4]) }}">
    @csrf
    @method('PATCH')

    <div class="onb-card">
        <div class="onb-card-head">
            <strong>4. الإعدادات التشغيلية</strong>
        </div>

        <div class="onb-card-body">
            <div class="onb-grid">
                <div>
                    <label class="form-label">المنطقة الزمنية *</label>
                    <input
                        class="form-input"
                        list="timezone-list"
                        name="timezone"
                        value="{{ old('timezone', $data['timezone'] ?? 'UTC') }}"
                        required
                    >
                    <datalist id="timezone-list">
                        @foreach($timezones as $timezone)
                            <option value="{{ $timezone }}"></option>
                        @endforeach
                    </datalist>
                    <small class="onb-help">مثال: Asia/Gaza أو Europe/London.</small>
                </div>

                <div>
                    <label class="form-label">العملة الأساسية</label>
                    <input
                        class="form-input"
                        value="{{ $baseCurrency ? ($baseCurrency->code . ' ' . $baseCurrency->symbol) : 'غير محددة' }}"
                        readonly
                    >
                    <small class="onb-help">للسلامة المالية لا يغيّر Sprint 9 العملة الأساسية.</small>
                </div>

                <div>
                    <label class="form-label">بادئة رقم الطلب *</label>
                    <input class="form-input" name="order_number_prefix" value="{{ old('order_number_prefix', $data['order_number_prefix'] ?? 'ORD') }}" required>
                </div>

                <div>
                    <label class="form-label">بادئة رقم الفاتورة *</label>
                    <input class="form-input" name="invoice_prefix" value="{{ old('invoice_prefix', $data['invoice_prefix'] ?? 'INV') }}" required>
                </div>

                <div>
                    <label class="form-label">بادئة طلب الكيك</label>
                    <input class="form-input" name="cake_order_prefix" value="{{ old('cake_order_prefix', $data['cake_order_prefix'] ?? 'CKO') }}">
                </div>

                <div>
                    <label class="form-label">بادئة طلب المخزون</label>
                    <input class="form-input" name="stock_request_prefix" value="{{ old('stock_request_prefix', $data['stock_request_prefix'] ?? 'SR') }}">
                </div>

                <div class="onb-full">
                    <label class="form-label">تذييل الفاتورة بالعربية</label>
                    <input class="form-input" name="invoice_footer_ar" value="{{ old('invoice_footer_ar', $data['invoice_footer_ar'] ?? '') }}">
                </div>

                <div class="onb-full">
                    <label class="form-label">تذييل الفاتورة بالإنجليزية</label>
                    <input class="form-input" name="invoice_footer_en" value="{{ old('invoice_footer_en', $data['invoice_footer_en'] ?? '') }}">
                </div>
            </div>

            <div class="onb-actions">
                <a class="btn btn-ghost" href="{{ route('onboarding.index', ['step' => 3]) }}">السابق</a>
                <button type="submit" class="btn btn-gold">حفظ ومتابعة</button>
            </div>
        </div>
    </div>
</form>
