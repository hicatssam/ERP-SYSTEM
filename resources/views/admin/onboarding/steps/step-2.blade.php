@php($data = $run->business_data ?? [])

<form method="POST" action="{{ route('onboarding.step.save', [$run, 2]) }}">
    @csrf
    @method('PATCH')

    <div class="onb-card">
        <div class="onb-card-head">
            <strong>2. بيانات المنشأة</strong>
        </div>

        <div class="onb-card-body">
            <div class="onb-grid">
                <div class="onb-full">
                    <label class="form-label">الاسم القانوني / التجاري *</label>
                    <input
                        class="form-input"
                        name="business_legal_name"
                        value="{{ old('business_legal_name', $data['business_legal_name'] ?? '') }}"
                        required
                    >
                </div>

                <div>
                    <label class="form-label">رقم التسجيل</label>
                    <input class="form-input" name="business_registration_number" value="{{ old('business_registration_number', $data['business_registration_number'] ?? '') }}">
                </div>

                <div>
                    <label class="form-label">الرقم الضريبي</label>
                    <input class="form-input" name="business_tax_number" value="{{ old('business_tax_number', $data['business_tax_number'] ?? '') }}">
                </div>

                <div>
                    <label class="form-label">الهاتف</label>
                    <input class="form-input" name="business_phone" value="{{ old('business_phone', $data['business_phone'] ?? '') }}">
                </div>

                <div>
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" class="form-input" name="business_email" value="{{ old('business_email', $data['business_email'] ?? '') }}">
                </div>

                <div>
                    <label class="form-label">الموقع الإلكتروني</label>
                    <input class="form-input" name="business_website" placeholder="https://example.com" value="{{ old('business_website', $data['business_website'] ?? '') }}">
                </div>

                <div>
                    <label class="form-label">الدولة</label>
                    <input class="form-input" name="business_country" value="{{ old('business_country', $data['business_country'] ?? '') }}">
                </div>

                <div>
                    <label class="form-label">المدينة</label>
                    <input class="form-input" name="business_city" value="{{ old('business_city', $data['business_city'] ?? '') }}">
                </div>

                <div class="onb-full">
                    <label class="form-label">العنوان</label>
                    <textarea class="form-input" rows="3" name="business_address">{{ old('business_address', $data['business_address'] ?? '') }}</textarea>
                </div>
            </div>

            <div class="onb-actions">
                <a class="btn btn-ghost" href="{{ route('onboarding.index', ['step' => 1]) }}">السابق</a>
                <button type="submit" class="btn btn-gold">حفظ ومتابعة</button>
            </div>
        </div>
    </div>
</form>
