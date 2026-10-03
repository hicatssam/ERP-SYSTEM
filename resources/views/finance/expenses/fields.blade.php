<div class="form-grid">
    @if($locations->isNotEmpty())
    <div class="form-group"><label class="form-label">الموقع *</label><select class="form-input" name="location_id" required>
        <option value="">اختر الموقع</option>
        @foreach($locations as $location)<option value="{{ $location->id }}" @selected((int)old('location_id',$locationId) === (int)$location->id)>{{ $location->name }}</option>@endforeach
    </select></div>
    @else
        <input type="hidden" name="location_id" value="{{ $locationId }}">
    @endif
    <div class="form-group"><label class="form-label">تصنيف المصروف *</label><select class="form-input" name="expense_category_id" required>
        <option value="">اختر التصنيف</option>
        @foreach($categories as $category)<option value="{{ $category->id }}" @selected((int)old('expense_category_id',$expense->expense_category_id) === (int)$category->id)>{{ $category->name }}</option>@endforeach
    </select></div>
    <div class="form-group"><label class="form-label">القيمة ₪ *</label><input class="form-input" type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount',$expense->amount) }}"></div>
    <div class="form-group"><label class="form-label">تاريخ المصروف *</label><input class="form-input" type="date" name="expense_date" max="{{ now()->toDateString() }}" required value="{{ old('expense_date',$expense->expense_date?->toDateString() ?? now()->toDateString()) }}"></div>
    <div class="form-group"><label class="form-label">طريقة الدفع *</label><select class="form-input" name="payment_method_id" required>
        <option value="">غير محددة — لا تدخل مطابقة الصندوق</option>
        @foreach($paymentMethods as $method)<option value="{{ $method->id }}" @selected((int)old('payment_method_id',$expense->payment_method_id) === (int)$method->id)>{{ $method->name_ar ?: $method->name }}</option>@endforeach
    </select><small>اختر «نقدي» فقط إذا خرج المبلغ فعليًا من صندوق الفرع. المصروف غير المحدد يمنع إقفال يومه النقدي بعد الترحيل.</small></div>
    <div class="form-group"><label class="form-label">الجهة / المستفيد</label><input class="form-input" name="payee" maxlength="180" value="{{ old('payee',$expense->payee) }}"></div>
    <div class="form-group"><label class="form-label">رقم مرجعي</label><input class="form-input" name="reference_number" maxlength="120" value="{{ old('reference_number',$expense->reference_number) }}"></div>
    <div class="form-group" style="grid-column:1/-1"><label class="form-label">الوصف والتبرير *</label><textarea class="form-input" name="description" rows="4" required>{{ old('description',$expense->description) }}</textarea></div>
</div>
