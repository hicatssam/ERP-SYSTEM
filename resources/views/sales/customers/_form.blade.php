@php
    $editing = isset($customer) && $customer->exists;
    $selectedLocations = collect(old('location_ids', $editing ? $customer->locations->pluck('id')->all() : []))
        ->map(fn ($id) => (string) $id)
        ->all();
    $scopeValue = old('scope', $editing ? $customer->scope : \App\Models\Customer::SCOPE_BRANCH);
    $typeValue = old('customer_type', $editing ? $customer->customer_type : \App\Models\Customer::TYPE_INDIVIDUAL);
    $allowCredit = (bool) old('allow_credit', $editing ? $customer->allow_credit : false);
@endphp

<div class="customer-form-grid">
    <div class="card">
        <div class="card-header"><span class="card-title">البيانات الأساسية</span></div>
        <div class="card-body form-grid-2">
            <div class="form-group">
                <label class="form-label">الاسم *</label>
                <input name="name" class="form-input @error('name') is-invalid @enderror"
                       value="{{ old('name', $editing ? $customer->name : '') }}" required>
                @error('name')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">الهاتف *</label>
                <input name="phone" class="form-input @error('phone') is-invalid @enderror"
                       value="{{ old('phone', $editing ? $customer->phone : '') }}" required>
                @error('phone')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">هاتف بديل</label>
                <input name="secondary_phone" class="form-input @error('secondary_phone') is-invalid @enderror"
                       value="{{ old('secondary_phone', $editing ? $customer->secondary_phone : '') }}">
                @error('secondary_phone')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            @if($isAdmin)
                <div class="form-group">
                    <label class="form-label">نوع العميل *</label>
                    <select name="customer_type" id="customerType" class="form-select @error('customer_type') is-invalid @enderror" required>
                        <option value="individual" @selected($typeValue === 'individual')>فرد</option>
                        <option value="institution" @selected($typeValue === 'institution')>مؤسسة</option>
                        <option value="company" @selected($typeValue === 'company')>شركة</option>
                        <option value="government" @selected($typeValue === 'government')>جهة / قطاع</option>
                    </select>
                    @error('customer_type')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            @endif

            @if($isAdmin)
                <div class="form-group">
                    <label class="form-label">الفرع المرجعي *</label>
                    <select name="location_id" class="form-select @error('location_id') is-invalid @enderror" required>
                        <option value="">اختر الفرع</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}"
                                @selected((string) old('location_id', $editing ? $customer->location_id : '') === (string) $location->id)>
                                {{ $location->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-help">للتعريف والإدارة فقط. نطاق الفروع الفعلي يحدد من الخيار التالي.</small>
                    @error('location_id')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            @else
                <div class="form-group">
                    <label class="form-label">الفرع</label>
                    <input class="form-input" value="{{ $primaryLocation?->name ?? ($editing ? $customer->location?->name : '') }}" readonly>
                </div>
            @endif

            @if($isAdmin)
                <div class="form-group">
                    <label class="form-label">نطاق العميل *</label>
                    <select name="scope" id="customerScope" class="form-select @error('scope') is-invalid @enderror" required>
                        <option value="branch" @selected($scopeValue === 'branch')>فرع واحد</option>
                        <option value="selected" @selected($scopeValue === 'selected')>فروع محددة</option>
                        <option value="global" @selected($scopeValue === 'global')>جميع الفروع</option>
                    </select>
                    <small class="form-help">المؤسسات مثل اللجنة المصرية يمكن جعلها متاحة في عدة فروع بنفس الحساب.</small>
                    @error('scope')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            @endif

            @if($isAdmin)
                <div class="form-group form-span-2" id="selectedLocationsGroup">
                    <label class="form-label">الفروع المسموح بالسحب منها *</label>
                    <div class="branch-options">
                        @foreach($locations as $location)
                            <label class="branch-option">
                                <input type="checkbox" name="location_ids[]" value="{{ $location->id }}"
                                       @checked(in_array((string) $location->id, $selectedLocations, true))>
                                <span>{{ $location->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('location_ids')<span class="form-error">{{ $message }}</span>@enderror
                    @error('location_ids.*')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            @endif

            @if($isAdmin)
                <div class="form-group">
                    <label class="form-label">الشخص المسؤول</label>
                    <input name="contact_person" class="form-input @error('contact_person') is-invalid @enderror"
                           value="{{ old('contact_person', $editing ? $customer->contact_person : '') }}">
                    @error('contact_person')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">الرقم الضريبي / التعريفي</label>
                    <input name="tax_number" class="form-input @error('tax_number') is-invalid @enderror"
                           value="{{ old('tax_number', $editing ? $customer->tax_number : '') }}">
                    @error('tax_number')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group form-span-2">
                    <label class="form-label">العنوان</label>
                    <input name="address" class="form-input @error('address') is-invalid @enderror"
                           value="{{ old('address', $editing ? $customer->address : '') }}">
                    @error('address')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            @endif

            <div class="form-group form-span-2">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-textarea @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $editing ? $customer->notes : '') }}</textarea>
                @error('notes')<span class="form-error">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>

    @if($isAdmin)
        <div class="card">
            <div class="card-header"><span class="card-title">الحساب الآجل والفوترة</span></div>
            <div class="card-body">
                <label class="credit-switch">
                    <input type="hidden" name="allow_credit" value="0">
                    <input type="checkbox" name="allow_credit" id="allowCredit" value="1" @checked($allowCredit)>
                    <span>
                        <strong>السماح بالبيع على الحساب</strong>
                        <small>يُستخدم للمؤسسات والشركات التي تسدد لاحقًا أو بنهاية الشهر.</small>
                    </span>
                </label>

                <div class="form-grid-3" id="creditFields">
                    <div class="form-group">
                        <label class="form-label">الحد الائتماني (₪)</label>
                        <input type="number" name="credit_limit" class="form-input @error('credit_limit') is-invalid @enderror"
                               min="0" step="0.01" value="{{ old('credit_limit', $editing ? $customer->credit_limit : '') }}"
                               placeholder="اتركه فارغًا بدون حد">
                        @error('credit_limit')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">دورة الفوترة *</label>
                        <select name="billing_cycle" class="form-select @error('billing_cycle') is-invalid @enderror">
                            <option value="immediate" @selected(old('billing_cycle', $editing ? $customer->billing_cycle : 'immediate') === 'immediate')>فوري</option>
                            <option value="weekly" @selected(old('billing_cycle', $editing ? $customer->billing_cycle : '') === 'weekly')>أسبوعي</option>
                            <option value="monthly" @selected(old('billing_cycle', $editing ? $customer->billing_cycle : '') === 'monthly')>شهري</option>
                        </select>
                        @error('billing_cycle')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">مهلة السداد بالأيام *</label>
                        <input type="number" name="payment_terms_days" class="form-input @error('payment_terms_days') is-invalid @enderror"
                               min="0" max="365" value="{{ old('payment_terms_days', $editing ? $customer->payment_terms_days : 0) }}">
                        @error('payment_terms_days')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<style>
.customer-form-grid{display:grid;gap:1.25rem;max-width:1050px}.form-grid-2,.form-grid-3{display:grid;gap:1rem}.form-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}.form-grid-3{grid-template-columns:repeat(3,minmax(0,1fr));margin-top:1rem}.form-span-2{grid-column:1/-1}.form-help{display:block;margin-top:.35rem;color:var(--text-muted);font-size:.76rem}.form-error{display:block;margin-top:.35rem;color:var(--error,#dc3545);font-size:.8rem}.is-invalid{border-color:var(--error,#dc3545)!important}.branch-options{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem}.branch-option{display:flex;align-items:center;gap:.55rem;padding:.7rem .8rem;border:1px solid var(--border);border-radius:10px;cursor:pointer;background:var(--surface)}.branch-option:has(input:checked){border-color:var(--gold);background:rgba(212,175,55,.08)}.credit-switch{display:flex;align-items:flex-start;gap:.75rem;padding:1rem;border:1px solid rgba(212,175,55,.25);border-radius:12px;background:rgba(212,175,55,.06);cursor:pointer}.credit-switch input[type=checkbox]{margin-top:.25rem;width:18px;height:18px;accent-color:var(--gold)}.credit-switch span{display:flex;flex-direction:column;gap:.15rem}.credit-switch small{color:var(--text-muted)}
@media(max-width:800px){.form-grid-2,.form-grid-3,.branch-options{grid-template-columns:1fr}.form-span-2{grid-column:auto}}
</style>

@if($isAdmin)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const scope = document.getElementById('customerScope');
    const type = document.getElementById('customerType');
    const locations = document.getElementById('selectedLocationsGroup');
    const allowCredit = document.getElementById('allowCredit');
    const creditFields = document.getElementById('creditFields');

    const syncScope = () => {
        locations.hidden = scope.value !== 'selected';
        if (scope.value !== 'branch' && type.value === 'individual') {
            type.value = 'institution';
        }
    };

    const syncCredit = () => {
        creditFields.style.display = allowCredit.checked ? 'grid' : 'none';
    };

    scope.addEventListener('change', syncScope);
    allowCredit.addEventListener('change', syncCredit);
    syncScope();
    syncCredit();
});
</script>
@endif
