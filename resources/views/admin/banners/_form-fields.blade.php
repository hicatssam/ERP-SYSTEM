<div class="bn-form-grid">

    <div class="full">
        <label>الفرع</label>
        <select name="location_id" id="{{ $prefix }}_location_id">
            <option value="">كل الفروع (بانر عام)</option>
            @foreach($locations as $loc)
                <option value="{{ $loc->id }}" @selected(old('location_id') == $loc->id)>{{ $loc->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="full">
        <label>صورة الإعلان</label>
        <input type="file" name="image" id="{{ $prefix }}_image" accept="image/*">
        <div class="bn-current-image" id="{{ $prefix }}_current_image"></div>
    </div>

    <div class="full">
        <label>عنوان صغير فوق العنوان (Kicker) — اختياري</label>
        <input type="text" name="kicker" id="{{ $prefix }}_kicker" placeholder="مثال: لفترة محدودة" value="{{ old('kicker') }}">
    </div>

    <div class="full">
        <label>العنوان الرئيسي *</label>
        <input type="text" name="title" id="{{ $prefix }}_title" placeholder="مثال: خصم 30% على البكجات" required value="{{ old('title') }}">
    </div>

    <div class="full">
        <label>النص الفرعي — اختياري</label>
        <textarea name="subtitle" id="{{ $prefix }}_subtitle" rows="2">{{ old('subtitle') }}</textarea>
    </div>

    <div>
        <label>نص الزر — اختياري</label>
        <input type="text" name="cta_text" id="{{ $prefix }}_cta_text" placeholder="اطلب الآن" value="{{ old('cta_text') }}">
    </div>

    <div>
        <label>رابط الزر — اختياري</label>
        <input type="text" name="cta_link" id="{{ $prefix }}_cta_link" placeholder="#menu أو https://..." value="{{ old('cta_link') }}">
    </div>

    <div>
        <label>الترتيب</label>
        <input type="number" name="sort_order" id="{{ $prefix }}_sort_order" min="0" value="{{ old('sort_order', 0) }}">
    </div>

    <div>
        <label class="bn-checkbox-label">
            <input type="checkbox" name="is_active" id="{{ $prefix }}_is_active" value="1" @checked(old('is_active', true))>
            إعلان فعال
        </label>
    </div>

    <div>
        <label>يبدأ من (اختياري)</label>
        <input type="datetime-local" name="starts_at" id="{{ $prefix }}_starts_at" value="{{ old('starts_at') }}">
    </div>

    <div>
        <label>ينتهي في (اختياري)</label>
        <input type="datetime-local" name="ends_at" id="{{ $prefix }}_ends_at" value="{{ old('ends_at') }}">
    </div>

</div>
