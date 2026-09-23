@extends('layouts.app')
@section('title', 'طلب كيك جديد')

@section('content')
<div class="page-header">
    <h1 class="page-heading">طلب كيك جديد</h1>
    <p class="page-subheading"><a href="{{ route('cake-orders.index') }}">طلبات الكيك</a> &laquo; جديد</p>
</div>

@if ($errors->any())
    <div class="alert alert-danger cake-errors" role="alert">
        <strong>تعذر إنشاء الطلب. يرجى تصحيح الأخطاء التالية:</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger cake-errors" role="alert">{{ session('error') }}</div>
@endif

<form action="{{ route('cake-orders.store') }}" method="POST" enctype="multipart/form-data" id="cakeOrderForm">
    @csrf
    <div style="display:grid;gap:1.5rem;max-width:1000px">

        {{-- Customer & Delivery --}}
        <div class="card">
            <div class="card-header"><span class="card-title">بيانات العميل والتسليم</span></div>
            <div class="card-body cake-grid cake-grid-3">
                <div class="form-group">
                    <label class="form-label">العميل *</label>
                    <select name="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                        <option value="">اختر عميلاً</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>
                                {{ $customer->name }} — {{ $customer->phone }}
                            </option>
                        @endforeach
                    </select>
                    @error('customer_id')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">تاريخ التسليم *</label>
                    <input type="date" name="required_date"
                           class="form-input @error('required_date') is-invalid @enderror"
                           value="{{ old('required_date') }}" required>
                    @error('required_date')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">وقت التسليم</label>
                    <input type="time" name="required_time"
                           class="form-input @error('required_time') is-invalid @enderror"
                           value="{{ old('required_time') }}">
                    @error('required_time')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </div>

        {{-- Cake Specs --}}
        <div class="card">
            <div class="card-header"><span class="card-title">مواصفات الكيك</span></div>
            <div class="card-body">
                <div class="cake-grid cake-grid-3">
                    <div class="form-group">
                        <label class="form-label">نوع الكيك *</label>
                        <select name="cake_type" class="form-select @error('cake_type') is-invalid @enderror" required>
                            <option value="">اختر النوع</option>
                            <option value="chocolate" @selected(old('cake_type') === 'chocolate')>شوكولاتة</option>
                            <option value="vanilla"   @selected(old('cake_type') === 'vanilla')>فانيلا</option>
                            <option value="red_velvet" @selected(old('cake_type') === 'red_velvet')>ريد فيلفيت</option>
                            <option value="caramel"   @selected(old('cake_type') === 'caramel')>كراميل</option>
                            <option value="fruit"     @selected(old('cake_type') === 'fruit')>فاكهة</option>
                            <option value="other"     @selected(old('cake_type') === 'other')>أخرى</option>
                        </select>
                        @error('cake_type')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">شكل/تصنيف الكيك *</label>
                        <select name="shape" id="cakeShape" class="form-select @error('shape') is-invalid @enderror" required>
                            <option value="">اختر الشكل</option>
                            <option value="round"          @selected(old('shape') === 'round')>دائري</option>
                            <option value="slab"           @selected(old('shape') === 'slab')>بلاطة</option>
                            <option value="wedding_tiers"  @selected(old('shape') === 'wedding_tiers')>طوابق أفراح</option>
                            <option value="standard_tiers" @selected(old('shape') === 'standard_tiers')>طوابق ستاندر</option>
                        </select>
                        @error('shape')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group" id="sizeGroup" hidden>
                        <label class="form-label" id="sizeLabel">الحجم *</label>
                        <select name="cake_size" id="cakeSize" class="form-select @error('cake_size') is-invalid @enderror"></select>
                        @error('cake_size')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group" id="layersGroup" hidden>
                        <label class="form-label">عدد الطبقات *</label>
                        <select name="layers_count" id="layersCount" class="form-select @error('layers_count') is-invalid @enderror"></select>
                        @error('layers_count')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">عدد الأشخاص</label>
                        <input type="number" min="1" name="persons_count"
                               class="form-input @error('persons_count') is-invalid @enderror"
                               value="{{ old('persons_count') }}">
                        @error('persons_count')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">الثيم/الموضوع</label>
                        <input name="theme" class="form-input @error('theme') is-invalid @enderror" value="{{ old('theme') }}">
                        @error('theme')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-group" style="margin-top:1rem">
                    <label class="form-label">النص على الكيك</label>
                    <input name="cake_text" class="form-input @error('cake_text') is-invalid @enderror" value="{{ old('cake_text') }}">
                    @error('cake_text')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group" style="margin-top:1rem">
                    <label class="form-label">تعليمات خاصة</label>
                    <textarea name="special_instructions" class="form-textarea @error('special_instructions') is-invalid @enderror">{{ old('special_instructions') }}</textarea>
                    @error('special_instructions')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </div>

        {{-- Cake Images --}}
        <div class="card">
            <div class="card-header"><span class="card-title">صور تصميم الكيك</span></div>
            <div class="card-body">
                <div class="cake-grid cake-grid-2">
                    <div class="form-group">
                        <label class="form-label">صورة مرجعية للكيك</label>
                        <input type="file" name="reference_image" id="referenceImage"
                               class="form-input @error('reference_image') is-invalid @enderror"
                               accept="image/jpeg,image/png,image/webp">
                        <small class="form-help">أضف صورة توضح شكل أو تصميم الكيك المطلوب، إن وجدت.</small>
                        @error('reference_image')<span class="form-error">{{ $message }}</span>@enderror
                        <div id="referenceImagePreview" class="image-preview"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">نوع الصورة على الكيك *</label>
                        <select name="image_cover_type" id="imageCoverType" class="form-select @error('image_cover_type') is-invalid @enderror" required>
                            <option value="none"               @selected(old('image_cover_type', 'none') === 'none')>بدون صورة مطبوعة</option>
                            <option value="edible_sugar"       @selected(old('image_cover_type') === 'edible_sugar')>طباعة سكر قابلة للأكل</option>
                            <option value="removable_cardboard" @selected(old('image_cover_type') === 'removable_cardboard')>صورة كرتون قابلة للإزالة</option>
                        </select>
                        @error('image_cover_type')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="form-group" id="printImageGroup" style="margin-top:1rem" hidden>
                    <label class="form-label">صورة الطباعة على السكر *</label>
                    <input type="file" name="print_image" id="printImage"
                           class="form-input @error('print_image') is-invalid @enderror"
                           accept="image/jpeg,image/png,image/webp">
                    <small class="form-help">أضف الصورة الأصلية التي تريد طباعتها على ورق السكر.</small>
                    @error('print_image')<span class="form-error">{{ $message }}</span>@enderror
                    <div id="printImagePreview" class="image-preview"></div>
                </div>
            </div>
        </div>

        {{-- Pricing & Payment --}}
        <div class="card">
            <div class="card-header"><span class="card-title">السعر والخصم والدفع</span></div>
            <div class="card-body">
                {{-- Row 1: Price & Discount --}}
                <div class="cake-grid cake-grid-3" style="margin-bottom:1rem">
                    <div class="form-group">
                        <label class="form-label">السعر الإجمالي (₪) *</label>
                        <input type="number" min="0" step="0.01" name="total_price" id="totalPrice"
                               class="form-input @error('total_price') is-invalid @enderror"
                               value="{{ old('total_price') }}" required>
                        @error('total_price')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">نوع الخصم</label>
                        <select name="discount_type" id="discountType" class="form-select @error('discount_type') is-invalid @enderror">
                            <option value="none"       @selected(old('discount_type', 'none') === 'none')>بدون خصم</option>
                            <option value="percentage" @selected(old('discount_type') === 'percentage')>نسبة مئوية (%)</option>
                            <option value="fixed"      @selected(old('discount_type') === 'fixed')>مبلغ ثابت (₪)</option>
                        </select>
                        @error('discount_type')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group" id="discountValueGroup" hidden>
                        <label class="form-label" id="discountValueLabel">قيمة الخصم</label>
                        <input type="number" min="0" step="0.01" name="discount_value" id="discountValue"
                               class="form-input @error('discount_value') is-invalid @enderror"
                               value="{{ old('discount_value', 0) }}">
                        @error('discount_value')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                {{-- Net price preview --}}
                <div id="netPricePreview" class="net-price-box" hidden>
                    <span class="net-price-label">الصافي بعد الخصم:</span>
                    <strong class="net-price-value" id="netPriceValue">—</strong>
                </div>

                {{-- Row 2: Payment arrangement & method --}}
                <div class="cake-grid cake-grid-2" style="margin-top:1rem">
                    <div class="form-group">
                        <label class="form-label">ترتيب الدفع *</label>
                        <select name="payment_arrangement" id="paymentArrangement" class="form-select @error('payment_arrangement') is-invalid @enderror" required>
                            <option value="">اختر ترتيب الدفع</option>
                            @foreach(\App\Enums\PaymentArrangement::cases() as $arrangement)
                                <option value="{{ $arrangement->value }}"
                                    @selected(old('payment_arrangement') === $arrangement->value)>
                                    {{ $arrangement->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('payment_arrangement')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group payment-methods-group">
                        <label class="form-label">طريقة الدفع</label>
                        @php
                            $selectedPaymentMethodId = (string) old('payment_method_id', '');
                            $paymentLogoUrl = function ($method) {
                                $logo = $method->logo_path ?: $method->logo;
                                if (!$logo) return null;
                                $logo = str_replace('\\', '/', trim($logo));
                                if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://') || str_starts_with($logo, 'data:image/')) return $logo;
                                $logo = preg_replace('#^/?(?:public/|storage/)+#', '', $logo);
                                return asset('storage/' . ltrim($logo, '/'));
                            };
                        @endphp

                        @if($paymentMethods->isNotEmpty())
                            <div class="payment-methods-grid">
                                @foreach($paymentMethods as $method)
                                    @php
                                        $methodName = $method->name_ar ?: $method->name;
                                        $logoUrl = $paymentLogoUrl($method);
                                    @endphp
                                    <label class="payment-method-card">
                                        <input type="radio" name="payment_method_id" value="{{ $method->id }}"
                                               data-payment-method
                                               data-requires-verification="{{ $method->requires_verification ? '1' : '0' }}"
                                               data-requires-reference="{{ $method->requires_reference ? '1' : '0' }}"
                                               @checked($selectedPaymentMethodId === (string) $method->id)>
                                        <span class="payment-method-content">
                                            <span class="payment-method-logo">
                                                @if($logoUrl)
                                                    <img src="{{ $logoUrl }}" alt="{{ $methodName }}" loading="lazy"
                                                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                                @endif
                                                <span class="payment-method-fallback" style="{{ $logoUrl ? 'display:none' : 'display:flex' }}">
                                                    {{ mb_substr($methodName, 0, 2) }}
                                                </span>
                                            </span>
                                            <span class="payment-method-details">
                                                <strong>{{ $methodName }}</strong>
                                                @if($method->name && $method->name_ar && $method->name !== $method->name_ar)
                                                    <small>{{ $method->name }}</small>
                                                @endif
                                            </span>
                                            <span class="payment-method-check">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                    <polyline points="20 6 9 17 4 12"/>
                                                </svg>
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="payment-methods-empty">لا توجد طرق دفع مفعّلة حاليًا.</div>
                        @endif

                        @error('payment_method_id')<span class="form-error">{{ $message }}</span>@enderror

                        @php
                            $selectedPaymentAccountId = (string) old('location_payment_account_id', '');
                        @endphp

                        <div class="payment-account-groups" id="paymentAccountGroups">
                            @foreach($paymentMethods as $method)
                                @php
                                    $accounts = $paymentAccounts->get($method->id, collect());
                                @endphp

                                @if($accounts->isNotEmpty())
                                    <div class="payment-account-group"
                                         data-payment-account-group="{{ $method->id }}"
                                         hidden>
                                        <div class="payment-account-heading">
                                            <strong>حساب الدفع المعتمد للفرع</strong>
                                            <small>اختر الحساب الذي سيستقبل دفعة هذا الطلب.</small>
                                        </div>

                                        <div class="payment-accounts-grid">
                                            @foreach($accounts as $account)
                                                <label class="payment-account-card">
                                                    <input type="radio"
                                                           name="location_payment_account_id"
                                                           value="{{ $account->id }}"
                                                           data-payment-account
                                                           @checked($selectedPaymentAccountId === (string) $account->id)>
                                                    <span class="payment-account-content">
                                                        <span class="payment-account-title">
                                                            <strong>{{ $account->name }}</strong>
                                                            @if($account->provider_name)
                                                                <small>{{ $account->provider_name }}</small>
                                                            @endif
                                                        </span>

                                                        <span class="payment-account-lines">
                                                            @if($account->account_holder_name)
                                                                <span><b>اسم المستفيد:</b> {{ $account->account_holder_name }}</span>
                                                            @endif
                                                            @if($account->account_number)
                                                                <span dir="ltr"><b>رقم الحساب:</b> {{ $account->account_number }}</span>
                                                            @endif
                                                            @if($account->iban)
                                                                <span dir="ltr"><b>IBAN:</b> {{ $account->iban }}</span>
                                                            @endif
                                                            @if($account->phone_number)
                                                                <span dir="ltr"><b>رقم المحفظة/الجوال:</b> {{ $account->phone_number }}</span>
                                                            @endif
                                                            @if($account->instructions)
                                                                <span class="payment-account-note">{{ $account->instructions }}</span>
                                                            @endif
                                                        </span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        @error('location_payment_account_id')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                {{-- Row 3: Conditional payment details --}}
                <div class="cake-grid cake-grid-2" id="paidAmountGroup" style="margin-top:1rem" hidden>
                    <div class="form-group">
                        <label class="form-label">المبلغ المدفوع الآن (₪) *</label>
                        <input type="number" min="0" step="0.01" name="paid_amount" id="paidAmount"
                               class="form-input @error('paid_amount') is-invalid @enderror"
                               value="{{ old('paid_amount') }}">
                        @error('paid_amount')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="cake-grid cake-grid-2" id="verificationGroup" style="margin-top:1rem" hidden>
                    <div class="form-group">
                        <label class="form-label">الرقم المرجعي *</label>
                        <input name="reference_number" id="referenceNumber"
                               class="form-input @error('reference_number') is-invalid @enderror"
                               value="{{ old('reference_number') }}">
                        @error('reference_number')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">إثبات الدفع (صورة) *</label>
                        <input type="file" name="payment_proof" id="paymentProof"
                               class="form-input @error('payment_proof') is-invalid @enderror"
                               accept="image/jpeg,image/png,image/webp">
                        @error('payment_proof')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:.75rem">
            <button class="btn btn-gold" type="submit">إنشاء الطلب</button>
            <a href="{{ route('cake-orders.index') }}" class="btn btn-ghost">إلغاء</a>
        </div>
    </div>
</form>

<style>
.cake-grid{display:grid;gap:1rem}
.cake-grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}
.cake-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
.form-help{display:block;margin-top:.4rem;color:#8b8b8b}
.image-preview{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1rem}
.image-preview img{width:110px;height:110px;object-fit:cover;border-radius:12px;border:1px solid rgba(212,175,55,.45)}
.cake-errors{max-width:1000px;margin-bottom:1.25rem;padding:1rem 1.25rem;border:1px solid #dc3545;border-radius:12px;background:rgba(220,53,69,.12);color:#dc3545}
.cake-errors ul{margin:.65rem 0 0;padding-inline-start:1.25rem}
.is-invalid{border-color:#dc3545!important}
.form-error{display:block;color:#dc3545;margin-top:.35rem;font-size:.82rem}
.net-price-box{background:rgba(212,175,55,.12);border:1px solid rgba(212,175,55,.4);border-radius:10px;padding:.75rem 1rem;display:flex;align-items:center;gap:.75rem}
.net-price-label{color:var(--text-muted)}
.net-price-value{font-size:1.1rem;color:var(--gold)}
.payment-methods-group{grid-column:1/-1}
.payment-methods-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}
.payment-method-card{position:relative;display:block;cursor:pointer}
.payment-method-card input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
.payment-method-content{position:relative;min-height:76px;padding:.75rem;display:flex;align-items:center;gap:.7rem;overflow:hidden;color:var(--text);background:var(--surface);border:1px solid var(--border);border-radius:12px;transition:border-color .2s ease,background .2s ease,transform .2s ease,box-shadow .2s ease}
.payment-method-card:hover .payment-method-content{border-color:rgba(212,175,55,.6);transform:translateY(-2px)}
.payment-method-card input:focus-visible + .payment-method-content{border-color:var(--gold);box-shadow:0 0 0 3px rgba(212,175,55,.18)}
.payment-method-card input:checked + .payment-method-content{color:var(--gold);background:rgba(212,175,55,.1);border-color:var(--gold);box-shadow:0 5px 16px rgba(212,175,55,.12)}
.payment-method-logo{width:48px;height:48px;flex:0 0 48px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fff;border:1px solid var(--border);border-radius:10px}
.payment-method-logo img{width:100%;height:100%;display:block;padding:5px;object-fit:contain}
.payment-method-fallback{width:100%;height:100%;align-items:center;justify-content:center;color:var(--gold);background:var(--gold-ultra);font-size:.75rem;font-weight:800}
.payment-method-details{min-width:0;display:flex;flex:1;flex-direction:column;gap:.15rem}
.payment-method-details strong,.payment-method-details small{overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
.payment-method-details strong{font-size:.9rem}
.payment-method-details small{color:var(--text-muted);font-size:.7rem}
.payment-method-check{width:22px;height:22px;flex:0 0 22px;display:flex;align-items:center;justify-content:center;color:#fff;background:var(--gold);border-radius:50%;opacity:0;transform:scale(.6);transition:opacity .2s ease,transform .2s ease}
.payment-method-check svg{width:13px;height:13px}
.payment-method-card input:checked + .payment-method-content .payment-method-check{opacity:1;transform:scale(1)}
.payment-account-groups{margin-top:1rem}
.payment-account-group{padding:1rem;background:rgba(212,175,55,.06);border:1px solid rgba(212,175,55,.3);border-radius:14px}
.payment-account-heading{display:flex;flex-direction:column;gap:.2rem;margin-bottom:.8rem}
.payment-account-heading strong{color:var(--gold)}
.payment-account-heading small{color:var(--text-muted)}
.payment-accounts-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}
.payment-account-card{position:relative;display:block;cursor:pointer}
.payment-account-card input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
.payment-account-content{min-height:100%;padding:.85rem;display:flex;flex-direction:column;gap:.65rem;background:var(--surface);border:1px solid var(--border);border-radius:12px;transition:border-color .2s ease,box-shadow .2s ease,transform .2s ease}
.payment-account-card:hover .payment-account-content{border-color:rgba(212,175,55,.65);transform:translateY(-2px)}
.payment-account-card input:focus-visible + .payment-account-content{border-color:var(--gold);box-shadow:0 0 0 3px rgba(212,175,55,.18)}
.payment-account-card input:checked + .payment-account-content{border-color:var(--gold);box-shadow:0 5px 16px rgba(212,175,55,.13)}
.payment-account-title{display:flex;align-items:center;justify-content:space-between;gap:.5rem}
.payment-account-title small{color:var(--text-muted)}
.payment-account-lines{display:flex;flex-direction:column;gap:.3rem;font-size:.82rem;color:var(--text-muted)}
.payment-account-lines b{color:var(--text)}
.payment-account-note{margin-top:.2rem;padding-top:.45rem;border-top:1px dashed var(--border)}
.payment-methods-empty{padding:1rem;color:#dc3545;text-align:center;background:rgba(220,53,69,.08);border:1px dashed rgba(220,53,69,.5);border-radius:10px}
[hidden]{display:none!important}
@media(max-width:800px){.cake-grid-3,.cake-grid-2{grid-template-columns:1fr}.payment-methods-grid,.payment-accounts-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:520px){.payment-methods-grid,.payment-accounts-grid{grid-template-columns:1fr}}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // ── Shape / Size / Layers ─────────────────────────────────────────────────
    const shape      = document.getElementById('cakeShape');
    const sizeGroup  = document.getElementById('sizeGroup');
    const sizeLabel  = document.getElementById('sizeLabel');
    const size       = document.getElementById('cakeSize');
    const layersGroup = document.getElementById('layersGroup');
    const layers     = document.getElementById('layersCount');
    let oldSize      = @json(old('cake_size'));
    let oldLayers    = @json(old('layers_count'));

    const choices = {
        round:          { showSize:true,  label:'الحجم *', values:{small:'صغير',medium:'وسط',large:'كبير'}, layers:{standard:'ستاندر','4':'4 طبقات','5':'5 طبقات','6':'6 طبقات'} },
        slab:           { showSize:true,  label:'مقاس البلاطة *', values:{'30x30':'30×30','40x30':'40×30','60x40':'60×40','120x30':'120×30','120x80':'120×80'}, layers:null },
        wedding_tiers:  { showSize:false, values:{standard:'ستاندر'}, layers:{'3':'3 طوابق','4':'4 طوابق','5':'5 طوابق','6':'6 طوابق'} },
        standard_tiers: { showSize:true,  label:'حجم طوابق الستاندر *', values:{small:'صغير',medium:'وسط',large:'كبير'}, layers:null },
    };

    function updateOptions() {
        const config = choices[shape.value];
        size.innerHTML = '<option value="">اختر</option>';
        if (!config) { sizeGroup.hidden = true; layersGroup.hidden = true; size.required = false; layers.required = false; return; }
        sizeLabel.textContent = config.label || 'الحجم *';
        Object.entries(config.values).forEach(([v,l]) => size.add(new Option(l,v)));
        size.value    = oldSize || (config.showSize ? '' : 'standard');
        sizeGroup.hidden  = !config.showSize;
        size.required     = true;
        layers.innerHTML  = '<option value="">اختر</option>';
        if (config.layers) Object.entries(config.layers).forEach(([v,l]) => layers.add(new Option(l,v)));
        layersGroup.hidden = !config.layers;
        layers.required   = Boolean(config.layers);
        layers.value      = config.layers ? (oldLayers || '') : '';
    }
    shape.addEventListener('change', () => { oldSize = null; oldLayers = null; updateOptions(); });
    updateOptions();

    // ── Discount calculation ──────────────────────────────────────────────────
    const discountType       = document.getElementById('discountType');
    const discountValueGroup = document.getElementById('discountValueGroup');
    const discountValueLabel = document.getElementById('discountValueLabel');
    const discountValueInput = document.getElementById('discountValue');
    const totalPriceInput    = document.getElementById('totalPrice');
    const netPricePreview    = document.getElementById('netPricePreview');
    const netPriceValue      = document.getElementById('netPriceValue');

    function updateDiscount() {
        const type = discountType.value;
        if (type === 'none') {
            discountValueGroup.hidden = true;
            discountValueInput.value = 0;
            recalcNet();
            return;
        }
        discountValueGroup.hidden = false;
        discountValueLabel.textContent = type === 'percentage' ? 'نسبة الخصم (%)' : 'مبلغ الخصم (₪)';
        recalcNet();
    }

    function recalcNet() {
        const total = Math.max(0, parseFloat(totalPriceInput.value) || 0);
        const type = discountType.value;
        const discountValue = Math.max(0, parseFloat(discountValueInput.value) || 0);
        let discountAmount = 0;

        if (type === 'percentage') {
            discountAmount = total * Math.min(discountValue, 100) / 100;
        } else if (type === 'fixed') {
            discountAmount = Math.min(discountValue, total);
        }

        const net = Math.max(0, total - discountAmount);
        netPriceValue.textContent = '₪' + net.toFixed(2);
        netPricePreview.hidden = type === 'none';

        const currentArrangement = document.getElementById('paymentArrangement');
        const currentPaidInput = document.getElementById('paidAmount');

        if (currentPaidInput) {
            currentPaidInput.max = net.toFixed(2);

            if (currentArrangement?.value === 'pay_now') {
                currentPaidInput.value = net.toFixed(2);
            } else if ((parseFloat(currentPaidInput.value) || 0) > net) {
                currentPaidInput.value = net.toFixed(2);
            }
        }
    }
    discountType.addEventListener('change', updateDiscount);
    discountValueInput.addEventListener('input', recalcNet);
    totalPriceInput.addEventListener('input', recalcNet);
    updateDiscount();

    // ── Cake reference and sugar-print images ────────────────────────────────
    const imageCoverType      = document.getElementById('imageCoverType');
    const printImageGroup     = document.getElementById('printImageGroup');
    const printImageInput     = document.getElementById('printImage');
    const referenceImageInput = document.getElementById('referenceImage');
    const referencePreview    = document.getElementById('referenceImagePreview');
    const printPreview        = document.getElementById('printImagePreview');

    function updatePrintImageField() {
        const requiresPrintImage = imageCoverType.value === 'edible_sugar';
        printImageGroup.hidden = !requiresPrintImage;
        printImageInput.required = requiresPrintImage;

        if (!requiresPrintImage) {
            printImageInput.value = '';
            printPreview.innerHTML = '';
        }
    }

    function previewSingleImage(inputElement, previewElement) {
        previewElement.innerHTML = '';
        const file = inputElement.files?.[0];
        if (!file) return;

        const img = document.createElement('img');
        const imageUrl = URL.createObjectURL(file);
        img.src = imageUrl;
        img.onload = () => URL.revokeObjectURL(imageUrl);
        previewElement.appendChild(img);
    }

    referenceImageInput.addEventListener('change', () => {
        previewSingleImage(referenceImageInput, referencePreview);
    });

    printImageInput.addEventListener('change', () => {
        previewSingleImage(printImageInput, printPreview);
    });

    imageCoverType.addEventListener('change', updatePrintImageField);
    updatePrintImageField();

    // ── Payment arrangement conditional fields ──────────────────────────────
    const arrangementSelect  = document.getElementById('paymentArrangement');
    const paidAmountGroup    = document.getElementById('paidAmountGroup');
    const paidAmountInput    = document.getElementById('paidAmount');
    const verificationGroup  = document.getElementById('verificationGroup');
    const referenceInput     = document.getElementById('referenceNumber');
    const paymentProofInput  = document.getElementById('paymentProof');
    const paymentMethodInputs = [...document.querySelectorAll('[data-payment-method]')];
    const paymentAccountGroups = [...document.querySelectorAll('[data-payment-account-group]')];

    function updatePaymentAccountGroups(needsPaymentMethod) {
        const selectedMethod = document.querySelector('[data-payment-method]:checked')?.value || '';

        paymentAccountGroups.forEach((group) => {
            const visible = needsPaymentMethod
                && group.dataset.paymentAccountGroup === selectedMethod;
            const accountInputs = [...group.querySelectorAll('[data-payment-account]')];

            group.hidden = !visible;

            accountInputs.forEach((input) => {
                input.disabled = !visible;
                input.required = false;

                if (!visible) {
                    input.checked = false;
                }
            });

            if (visible && accountInputs.length > 0) {
                accountInputs[0].required = true;
            }
        });
    }

    function updatePaymentFields() {
        const val = arrangementSelect.value;
        const needsPaidAmount = (val === 'deposit' || val === 'partial_payment' || val === 'pay_now');
        const needsPaymentMethod = val !== '' && val !== 'pay_on_pickup';
        const selectedMethod = document.querySelector('[data-payment-method]:checked');
        const methodNeedsVerification = needsPaymentMethod
            && selectedMethod?.dataset.requiresVerification === '1';
        const methodNeedsReference = needsPaymentMethod
            && selectedMethod?.dataset.requiresReference === '1';
        const needsProof = val === 'pending_verification' || methodNeedsVerification;
        const needsReference = val === 'pending_verification' || methodNeedsReference;
        const showsVerification = needsProof || needsReference;

        paymentMethodInputs.forEach((input, index) => {
            input.required = needsPaymentMethod && index === 0;
        });

        updatePaymentAccountGroups(needsPaymentMethod);

        paidAmountGroup.hidden   = !needsPaidAmount;
        paidAmountInput.required = needsPaidAmount;

        verificationGroup.hidden    = !showsVerification;
        referenceInput.required      = needsReference;
        paymentProofInput.required   = needsProof;

        if (val === 'pay_now') {
            paidAmountInput.readOnly = true;
            recalcNet();
        } else {
            paidAmountInput.readOnly = false;
        }
    }
    arrangementSelect.addEventListener('change', updatePaymentFields);
    paymentMethodInputs.forEach((input) => {
        input.addEventListener('change', updatePaymentFields);
    });
    updatePaymentFields();

});
</script>
@endsection