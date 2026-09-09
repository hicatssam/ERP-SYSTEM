@extends('layouts.app')
@section('title', 'طلب جديد')

@section('content')
@php
    $products = $products ?? \App\Models\Product::active()
        ->with('category')
        ->orderBy('name')
        ->get();

    $selectedPaymentMethodId = (string) old('payment_method_id', '');
    $initialQuickSale = (bool) old('quick_sale', request('mode') === 'quick');

    $paymentLogoUrl = function ($method) {
        $logo = $method->logo_path ?: $method->logo;
        if (!$logo) return null;

        $logo = str_replace('\\\\', '/', trim($logo));

        if (str_starts_with($logo, 'http://') ||
            str_starts_with($logo, 'https://') ||
            str_starts_with($logo, 'data:image/')) {
            return $logo;
        }

        $logo = preg_replace('#^/?(?:public/|storage/)+#', '', $logo);
        return asset('storage/' . ltrim($logo, '/'));
    };

    // أخطاء المخزون تظهر في Popup مستقل، وباقي الأخطاء تبقى أعلى النموذج.
    $stockErrors = $errors->get('stock');
    $otherErrors = collect($errors->messages())
        ->except('stock')
        ->flatten();
@endphp

<div class="page-header">
    <div>
        <h1 class="page-heading">طلب جديد</h1>
        <p class="page-subheading"><a href="{{ route('orders.index') }}">الطلبات</a> &laquo; جديد</p>
    </div>
</div>

@if($otherErrors->isNotEmpty())
    <div class="order-errors" role="alert">
        <strong>تعذر إنشاء الطلب. يرجى تصحيح الأخطاء التالية:</strong>
        <ul>
            @foreach($otherErrors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(session('error'))
    <div class="order-errors" role="alert">
        {{ session('error') }}
    </div>
@endif

<form action="{{ route('orders.store') }}" method="POST" enctype="multipart/form-data" id="orderForm">
    @csrf
    <input type="hidden" name="quick_sale" id="quickSaleInput" value="{{ $initialQuickSale ? 1 : 0 }}">
    <input type="hidden" name="payment_arrangement" id="quickPaymentArrangementInput" value="pay_now" {{ $initialQuickSale ? '' : 'disabled' }}>
    <div class="order-form-wrap">

        <div class="sale-mode-switch" id="saleModeSwitch">
            <button type="button" class="sale-mode-btn {{ $initialQuickSale ? 'active' : '' }}" data-mode="quick">
                <span class="sale-mode-icon">⚡</span>
                <span><strong>بيع سريع</strong><small>بدون بيانات عميل — نقدي أو تحويل أو أي طريقة دفع متاحة</small></span>
            </button>
            <button type="button" class="sale-mode-btn {{ ! $initialQuickSale ? 'active' : '' }}" data-mode="registered">
                <span class="sale-mode-icon">👤</span>
                <span><strong>عميل مسجل / مؤسسة</strong><small>اختيار عميل، دفع جزئي أو بيع على الحساب</small></span>
            </button>
        </div>

        <div class="quick-sale-note" id="quickSaleNote" {{ $initialQuickSale ? '' : 'hidden' }}>
            <strong>البيع السريع:</strong>
            اختر طريقة الدفع — نقدي أو تحويل أو أي وسيلة دفع متاحة.
            <div id="quickPaymentHint" class="quick-payment-hint"></div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">بيانات الطلب</span></div>
            <div class="card-body order-grid order-grid-2">
                <div class="form-group" id="customerGroup">
                    <label class="form-label">العميل</label>
                    <select name="customer_id" id="customerSelect" class="form-select @error('customer_id') is-invalid @enderror">
                        <option value="">عميل نقدي</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}"
                                    data-type="{{ $customer->typeLabel() }}"
                                    data-scope="{{ $customer->scopeLabel() }}"
                                    data-allow-credit="{{ $customer->allow_credit ? '1' : '0' }}"
                                    data-credit-limit="{{ $customer->credit_limit ?? '' }}"
                                    data-balance="{{ (float) ($customer->account_outstanding ?? 0) }}"
                                    data-billing="{{ $customer->billingCycleLabel() }}"
                                    data-terms="{{ $customer->payment_terms_days }}"
                                    @selected((string) old('customer_id') === (string) $customer->id)>
                                {{ $customer->name }} — {{ $customer->phone }}{{ $customer->isInstitutional() ? ' — '.$customer->typeLabel() : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('customer_id')<span class="form-error">{{ $message }}</span>@enderror
                    <div class="customer-credit-info" id="customerCreditInfo" hidden></div>
                </div>
                <div class="form-group">
                    <label class="form-label">ترتيب الدفع *</label>
                    <select name="payment_arrangement" id="paymentArrangement" class="form-select @error('payment_arrangement') is-invalid @enderror" required>
                        <option value="">اختر ترتيب الدفع</option>
                        @foreach(\App\Enums\PaymentArrangement::cases() as $arrangement)
                            <option value="{{ $arrangement->value }}" @selected(old('payment_arrangement') === $arrangement->value)>
                                {{ $arrangement->label() }}
                            </option>
                        @endforeach
                    </select>
                    @error('payment_arrangement')<span class="form-error">{{ $message }}</span>@enderror
                    <small class="form-help" id="paymentArrangementHelp" hidden>في البيع السريع يكون ترتيب الدفع: دفع فوري.</small>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">المنتجات</span>
                <button type="button" class="btn btn-ghost btn-sm" id="addItem">+ إضافة منتج</button>
            </div>
            <div class="card-body">
                <div id="itemsContainer">
                    @php $oldItems = old('items', [['product_id'=>'','quantity'=>1]]); @endphp
                    @foreach($oldItems as $index => $oldItem)
                        <div class="item-row">
                            <div class="form-group">
                                <label class="form-label item-label">المنتج</label>
                                <select name="items[{{ $index }}][product_id]" class="form-select product-select" required>
                                    <option value="">اختر منتجًا</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}"
                                                data-price="{{ (float) $product->base_selling_price }}"
                                                @selected((string) ($oldItem['product_id'] ?? '') === (string) $product->id)>
                                            {{ $product->name_ar ?? $product->name }} — ₪{{ number_format((float) $product->base_selling_price, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label item-label">الكمية</label>
                                <input type="number" name="items[{{ $index }}][quantity]" class="form-input quantity-input"
                                       value="{{ $oldItem['quantity'] ?? 1 }}" min="0.001" step="0.001" required>
                            </div>
                            <div class="line-total">₪0.00</div>
                            <button type="button" class="remove-item" title="حذف">×</button>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>


        <x-sales-channel-picker :channels="$salesChannels" />

        <div class="card">
            <div class="card-header"><span class="card-title">خصم إضافي على الطلب</span></div>
            <div class="card-body">
                <div class="order-grid order-grid-3">
                    <div class="form-group">
                        <label class="form-label">السعر الإجمالي (₪)</label>
                        <input type="number" name="total_price" id="totalPrice" class="form-input total-readonly"
                               value="{{ old('total_price', 0) }}" min="0" step="0.01" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">نوع الخصم</label>
                        <select name="discount_type" id="discountType" class="form-select @error('discount_type') is-invalid @enderror">
                            <option value="none" @selected(old('discount_type', 'none') === 'none')>بدون خصم</option>
                            <option value="percentage" @selected(old('discount_type') === 'percentage')>نسبة مئوية (%)</option>
                            <option value="fixed" @selected(old('discount_type') === 'fixed')>مبلغ ثابت (₪)</option>
                        </select>
                        @error('discount_type')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group" id="discountValueGroup" hidden>
                        <label class="form-label" id="discountValueLabel">قيمة الخصم</label>
                        <input type="number" name="discount_value" id="discountValue" class="form-input @error('discount_value') is-invalid @enderror"
                               value="{{ old('discount_value', 0) }}" min="0" step="0.01">
                        @error('discount_value')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="price-breakdown">
    <div>
        <span>إجمالي المنتجات</span>
        <strong id="summaryTotal">₪0.00</strong>
    </div>

    <div>
        <span>خصم الطلب</span>
        <strong id="summaryDiscount">− ₪0.00</strong>
    </div>

    <div>
        <span>الإجمالي بعد خصم الطلب</span>
        <strong id="summaryAfterOrderDiscount">₪0.00</strong>
    </div>

    <div>
        <span>خصم قناة البيع</span>
        <strong id="summaryChannelDiscount">− ₪0.00</strong>
    </div>

    <div class="net-row">
        <span>المبلغ النهائي</span>
        <strong id="summaryNet">₪0.00</strong>
    </div>
</div>
            </div>
        </div>

        <div class="card" id="paymentMethodCard">
            <div class="card-header"><span class="card-title">طريقة الدفع</span></div>
            <div class="card-body">
                @if($paymentMethods->isNotEmpty())
                    <div class="payment-methods-grid">
                        @foreach($paymentMethods as $method)
                            @php
                                $methodName = $method->name_ar ?: $method->name;
                                $logoUrl = $paymentLogoUrl($method);
                            @endphp
                            <label class="payment-method-card">
                                <input type="radio" name="payment_method_id" value="{{ $method->id }}"
                                       data-name="{{ $methodName }}"
                                       data-requires-reference="{{ $method->requires_reference ? '1' : '0' }}"
                                       data-requires-verification="{{ $method->requires_verification ? '1' : '0' }}"
                                       @checked($selectedPaymentMethodId === (string) $method->id)>
                                <span class="payment-method-content">
                                    <span class="payment-method-logo">
                                        @if($logoUrl)
                                            <img src="{{ $logoUrl }}" alt="{{ $methodName }}"
                                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                        @endif
                                        <span class="payment-method-fallback" style="{{ $logoUrl ? 'display:none' : 'display:flex' }}">
                                            {{ mb_substr($methodName, 0, 2) }}
                                        </span>
                                    </span>
                                    <span class="payment-method-details">
                                        <strong>{{ $methodName }}</strong>
                                        @if($method->name && $method->name_ar && $method->name !== $method->name_ar)<small>{{ $method->name }}</small>@endif
                                    </span>
                                    <span class="payment-method-check">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <div class="payment-methods-empty">لا توجد طرق دفع مفعّلة حاليًا.</div>
                @endif
                @error('payment_method_id')<span class="form-error">{{ $message }}</span>@enderror

                <div class="order-grid order-grid-2 conditional-row" id="paidAmountGroup" hidden>
                    <div class="form-group">
                        <label class="form-label">المبلغ المدفوع الآن (₪) *</label>
                        <input type="number" name="paid_amount" id="paidAmount" class="form-input @error('paid_amount') is-invalid @enderror"
                               value="{{ old('paid_amount') }}" min="0" step="0.01">
                        @error('paid_amount')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="order-grid order-grid-2 conditional-row" id="verificationGroup" hidden>
                    <div class="form-group">
                        <label class="form-label">رقم عملية الدفع / الحوالة *</label>
                        <input name="reference_number" id="referenceNumber" class="form-input @error('reference_number') is-invalid @enderror"
                               value="{{ old('reference_number') }}" placeholder="مثال: TXN-84579213">
                        <small class="form-help">يظهر فقط عندما تكون طريقة الدفع بحاجة إلى رقم مرجع أو تحقق.</small>
                        @error('reference_number')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">إثبات الدفع (صورة) *</label>
                        <input type="file" name="payment_proof" id="paymentProof" class="form-input @error('payment_proof') is-invalid @enderror"
                               accept="image/jpeg,image/png,image/webp">
                        @error('payment_proof')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">ملاحظات</label>
            <textarea name="notes" class="form-textarea" rows="4">{{ old('notes') }}</textarea>
        </div>

        <div class="form-actions">
            <a href="{{ route('orders.index') }}" class="btn btn-ghost">إلغاء</a>
            <button class="btn btn-gold" type="submit" id="submitOrderBtn">{{ $initialQuickSale ? 'تنفيذ البيع وطباعة الفاتورة' : 'إنشاء الطلب' }}</button>
        </div>
    </div>
</form>

<template id="itemRowTemplate">
    <div class="item-row">
        <div class="form-group">
            <label class="form-label item-label">المنتج</label>
            <select class="form-select product-select" required>
                <option value="">اختر منتجًا</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" data-price="{{ (float) $product->base_selling_price }}">
                        {{ $product->name_ar ?? $product->name }} — ₪{{ number_format((float) $product->base_selling_price, 2) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label class="form-label item-label">الكمية</label>
            <input type="number" class="form-input quantity-input" value="1" min="0.001" step="0.001" required>
        </div>
        <div class="line-total">₪0.00</div>
        <button type="button" class="remove-item" title="حذف">×</button>
    </div>
</template>


{{-- =========================================================
     Popup نقص المخزون
========================================================= --}}
@if(!empty($stockErrors))
    <div
        id="createStockErrorModal"
        class="stock-modal is-open"
        aria-hidden="false"
        onclick="closeCreateStockErrorFromBackdrop(event)"
    >
        <div
            class="stock-modal-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="createStockErrorTitle"
        >
            <div class="stock-modal-header">
                <div>
                    <span class="stock-modal-eyebrow">تعذر تنفيذ العملية</span>
                    <h3 id="createStockErrorTitle">المخزون غير كافٍ</h3>
                    <p>لم يتم إنشاء أو تأكيد الطلب، ولم يتم خصم أي كمية.</p>
                </div>

                <button
                    type="button"
                    class="stock-modal-close"
                    onclick="closeCreateStockErrorModal(false)"
                    aria-label="إغلاق"
                >×</button>
            </div>

            <div class="stock-modal-body">
                <div class="stock-error-box">
                    <div class="stock-error-icon">!</div>

                    <div class="stock-error-content">
                        <strong>لا يمكن تنفيذ الطلب بالكميات الحالية</strong>
                        <p>راجع الكميات المطلوبة مقارنة بالمخزون المتاح ثم أعد المحاولة.</p>

                        <ul>
                            @foreach($stockErrors as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="stock-modal-footer">
                <button
                    type="button"
                    class="btn btn-ghost"
                    onclick="closeCreateStockErrorModal(false)"
                >
                    إغلاق
                </button>

                <button
                    type="button"
                    class="btn btn-gold"
                    id="editCreateQuantitiesBtn"
                    onclick="closeCreateStockErrorModal(true)"
                >
                    تعديل الكميات
                </button>
            </div>
        </div>
    </div>
@endif

<style>
.sale-mode-switch{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem}.sale-mode-btn{display:flex;align-items:center;gap:.85rem;padding:1rem;text-align:right;color:var(--text);background:var(--surface);border:1px solid var(--border);border-radius:14px;cursor:pointer;transition:.18s}.sale-mode-btn:hover{border-color:rgba(212,175,55,.55);transform:translateY(-1px)}.sale-mode-btn.active{border-color:var(--gold);background:rgba(212,175,55,.09);box-shadow:0 0 0 2px rgba(212,175,55,.08)}.sale-mode-btn span:not(.sale-mode-icon){display:flex;flex-direction:column;gap:.18rem}.sale-mode-btn small{color:var(--text-muted);font-size:.72rem}.sale-mode-icon{width:42px;height:42px;display:flex;align-items:center;justify-content:center;flex:0 0 42px;border-radius:11px;background:rgba(212,175,55,.12);font-size:1.25rem}.quick-sale-note{padding:.85rem 1rem;color:#745c0a;background:rgba(212,175,55,.1);border:1px solid rgba(212,175,55,.28);border-radius:11px;font-size:.82rem}.quick-payment-hint{margin-top:.4rem;font-weight:700;line-height:1.7}.customer-credit-info{margin-top:.55rem;padding:.6rem .7rem;background:rgba(212,175,55,.06);border:1px solid rgba(212,175,55,.2);border-radius:9px;color:var(--text-muted);font-size:.75rem}.customer-credit-info strong{color:var(--text)}
.order-form-wrap{display:grid;gap:1.5rem;max-width:1000px}.order-grid{display:grid;gap:1rem}.order-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}.order-grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}.order-errors{max-width:1000px;margin-bottom:1.25rem;padding:1rem 1.25rem;color:#dc3545;background:rgba(220,53,69,.1);border:1px solid rgba(220,53,69,.45);border-radius:12px}.order-errors ul{margin:.6rem 0 0;padding-inline-start:1.2rem}.form-help{display:block;margin-top:.35rem;color:var(--text-muted);font-size:.78rem}.form-error{display:block;margin-top:.35rem;color:#dc3545;font-size:.82rem}.is-invalid{border-color:#dc3545!important}.item-row{display:grid;grid-template-columns:minmax(0,1fr) 130px 110px 38px;gap:.75rem;align-items:end;padding:.8rem 0;border-bottom:1px solid var(--border)}.item-row:first-child{padding-top:0}.item-row:last-child{border-bottom:0}.line-total{height:44px;display:flex;align-items:center;justify-content:center;color:var(--gold);font-weight:800;background:rgba(212,175,55,.08);border-radius:8px}.remove-item{width:38px;height:44px;color:#dc3545;background:rgba(220,53,69,.08);border:0;border-radius:8px;font-size:1.3rem;cursor:pointer}.total-readonly{font-weight:800;color:var(--gold)}.price-breakdown{margin-top:1rem;padding:1rem;background:rgba(212,175,55,.06);border:1px solid rgba(212,175,55,.25);border-radius:12px}.price-breakdown>div{display:flex;justify-content:space-between;padding:.35rem 0;color:var(--text-muted)}.price-breakdown .net-row{margin-top:.5rem;padding-top:.8rem;color:var(--text);border-top:1px solid var(--border)}.price-breakdown .net-row strong{color:var(--gold);font-size:1.2rem}.payment-methods-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}.payment-method-card{position:relative;cursor:pointer}.payment-method-card input{position:absolute;width:1px;height:1px;opacity:0}.payment-method-content{min-height:76px;padding:.75rem;display:flex;align-items:center;gap:.7rem;color:var(--text);background:var(--surface);border:1px solid var(--border);border-radius:12px;transition:.2s}.payment-method-card:hover .payment-method-content{border-color:rgba(212,175,55,.6);transform:translateY(-2px)}.payment-method-card input:checked+.payment-method-content{color:var(--gold);background:rgba(212,175,55,.1);border-color:var(--gold)}.payment-method-logo{width:48px;height:48px;flex:0 0 48px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fff;border:1px solid var(--border);border-radius:10px}.payment-method-logo img{width:100%;height:100%;padding:5px;object-fit:contain}.payment-method-fallback{width:100%;height:100%;align-items:center;justify-content:center;color:var(--gold);background:var(--gold-ultra);font-size:.75rem;font-weight:800}.payment-method-details{min-width:0;display:flex;flex:1;flex-direction:column}.payment-method-details strong,.payment-method-details small{overflow:hidden;white-space:nowrap;text-overflow:ellipsis}.payment-method-details small{color:var(--text-muted);font-size:.7rem}.payment-method-check{width:22px;height:22px;display:flex;align-items:center;justify-content:center;color:#fff;background:var(--gold);border-radius:50%;opacity:0;transform:scale(.6);transition:.2s}.payment-method-check svg{width:13px;height:13px}.payment-method-card input:checked+.payment-method-content .payment-method-check{opacity:1;transform:scale(1)}.payment-methods-empty{padding:1rem;color:#dc3545;text-align:center;border:1px dashed rgba(220,53,69,.45);border-radius:10px}.conditional-row{margin-top:1rem}.form-actions{display:flex;justify-content:flex-end;gap:.75rem}
@media(max-width:800px){.sale-mode-switch{grid-template-columns:1fr}.order-grid-2,.order-grid-3{grid-template-columns:1fr}.payment-methods-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.item-row{grid-template-columns:minmax(0,1fr) 100px 38px}.line-total{grid-column:1/3}.remove-item{grid-column:3;grid-row:1}}
@media(max-width:520px){.payment-methods-grid{grid-template-columns:1fr}.item-row{grid-template-columns:1fr}.line-total,.remove-item{grid-column:auto;grid-row:auto}.remove-item{width:100%}}


/* =========================================================
   Popup نقص المخزون
========================================================= */
.stock-modal{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;padding:1rem;background:rgba(15,23,42,.58);backdrop-filter:blur(3px);opacity:0;visibility:hidden;pointer-events:none;transition:opacity .18s ease,visibility .18s ease}.stock-modal.is-open{opacity:1;visibility:visible;pointer-events:auto}.stock-modal-dialog{width:100%;max-width:520px;background:#fff;border:1px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.22);transform:translateY(15px) scale(.98);transition:transform .18s ease}.stock-modal.is-open .stock-modal-dialog{transform:translateY(0) scale(1)}.stock-modal-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1.2rem 1.3rem;border-bottom:1px solid var(--border)}.stock-modal-eyebrow{display:block;margin-bottom:.2rem;color:#dc3545;font-size:.68rem;font-weight:800}.stock-modal-header h3{margin:0;color:var(--text);font-size:1.05rem;font-weight:900}.stock-modal-header p{margin:.3rem 0 0;color:var(--text-muted);font-size:.72rem;line-height:1.7}.stock-modal-close{width:34px;height:34px;display:flex;align-items:center;justify-content:center;flex:0 0 34px;padding:0;border:1px solid var(--border);border-radius:50%;background:#fff;color:var(--text-muted);font-size:1.4rem;cursor:pointer}.stock-modal-close:hover{color:#dc3545;border-color:rgba(220,53,69,.25);background:rgba(220,53,69,.06)}.stock-modal-body{padding:1.2rem 1.3rem}.stock-error-box{display:flex;align-items:flex-start;gap:.9rem;padding:1rem;border:1px solid rgba(220,53,69,.20);border-radius:13px;background:rgba(220,53,69,.055)}.stock-error-icon{width:42px;height:42px;flex:0 0 42px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:rgba(220,53,69,.12);color:#dc3545;font-size:1.2rem;font-weight:900}.stock-error-content{flex:1;min-width:0}.stock-error-content>strong{display:block;margin-bottom:.25rem;color:#b42318;font-size:.86rem;font-weight:900}.stock-error-content p{margin:0;color:var(--text-muted);font-size:.72rem;line-height:1.7}.stock-error-content ul{margin:.65rem 0 0;padding-right:1.2rem;color:#7a2930;font-size:.76rem;line-height:1.9}.stock-modal-footer{display:flex;align-items:center;justify-content:flex-end;gap:.65rem;padding:1rem 1.3rem;border-top:1px solid var(--border);background:var(--off-white)}@media(max-width:600px){.stock-modal{padding:.75rem}.stock-modal-footer{flex-direction:column-reverse}.stock-modal-footer .btn{width:100%}}

</style>

<script>

function closeCreateStockErrorModal(focusQuantity = false) {
    const modal = document.getElementById('createStockErrorModal');

    if (modal) {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.body.style.overflow = '';

    if (!focusQuantity) {
        return;
    }

    window.setTimeout(() => {
        const firstQuantity = document.querySelector('#itemsContainer .quantity-input');

        if (!firstQuantity) {
            return;
        }

        firstQuantity.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });

        firstQuantity.focus();
        firstQuantity.select();
    }, 180);
}

function closeCreateStockErrorFromBackdrop(event) {
    if (event.target.id === 'createStockErrorModal') {
        closeCreateStockErrorModal(false);
    }
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeCreateStockErrorModal(false);
    }
});

document.addEventListener('DOMContentLoaded', () => {

    const stockErrorModal = document.getElementById('createStockErrorModal');
    if (stockErrorModal && stockErrorModal.classList.contains('is-open')) {
        document.body.style.overflow = 'hidden';
    }

    const container = document.getElementById('itemsContainer');
    const template = document.getElementById('itemRowTemplate');
    const addItemButton = document.getElementById('addItem');
    const totalInput = document.getElementById('totalPrice');
    const discountType = document.getElementById('discountType');
    const discountValue = document.getElementById('discountValue');
    const discountValueGroup = document.getElementById('discountValueGroup');
    const discountValueLabel = document.getElementById('discountValueLabel');
    const arrangement = document.getElementById('paymentArrangement');
    const paymentArrangementHelp = document.getElementById('paymentArrangementHelp');
    const customerSelect = document.getElementById('customerSelect');
    const customerGroup = document.getElementById('customerGroup');
    const customerCreditInfo = document.getElementById('customerCreditInfo');
    const quickSaleInput = document.getElementById('quickSaleInput');
    const quickPaymentArrangementInput = document.getElementById('quickPaymentArrangementInput');
    const saleModeButtons = document.querySelectorAll('.sale-mode-btn');
    const quickSaleNote = document.getElementById('quickSaleNote');
    const quickPaymentHint = document.getElementById('quickPaymentHint');
    const paymentMethodCard = document.getElementById('paymentMethodCard');
    const submitOrderBtn = document.getElementById('submitOrderBtn');
    const paidGroup = document.getElementById('paidAmountGroup');
    const paidInput = document.getElementById('paidAmount');
    const verificationGroup = document.getElementById('verificationGroup');
    const referenceInput = document.getElementById('referenceNumber');
    const proofInput = document.getElementById('paymentProof');
    const paymentMethodInputs = document.querySelectorAll('input[name="payment_method_id"]');

    let nextIndex = container.querySelectorAll('.item-row').length;
    let currentNet = 0;

    function renameRows() {
        container.querySelectorAll('.item-row').forEach((row, index) => {
            row.querySelector('.product-select').name = `items[${index}][product_id]`;
            row.querySelector('.quantity-input').name = `items[${index}][quantity]`;
        });
        nextIndex = container.querySelectorAll('.item-row').length;
    }

    function calculateTotals() {
    let total = 0;

    container.querySelectorAll('.item-row').forEach(row => {
        const select = row.querySelector('.product-select');

        const quantity = Math.max(
            0,
            parseFloat(row.querySelector('.quantity-input').value) || 0
        );

        const price = parseFloat(
            select.selectedOptions[0]?.dataset.price
        ) || 0;

        const lineTotal = price * quantity;

        row.querySelector('.line-total').textContent =
            '₪' + lineTotal.toFixed(2);

        total += lineTotal;
    });

    /*
     * خصم الطلب الإضافي
     */
    const orderDiscountType = discountType.value;

    const orderDiscountValue = Math.max(
        0,
        parseFloat(discountValue.value) || 0
    );

    let orderDiscountAmount = 0;

    if (orderDiscountType === 'percentage') {
        orderDiscountAmount =
            total * Math.min(orderDiscountValue, 100) / 100;
    }

    if (orderDiscountType === 'fixed') {
        orderDiscountAmount = Math.min(
            orderDiscountValue,
            total
        );
    }

    const afterOrderDiscount = Math.max(
        0,
        total - orderDiscountAmount
    );

    /*
     * خصم قناة البيع
     */
    const selectedChannel = document.querySelector(
        'input[name="sales_channel_id"]:checked'
    );

    const channelDiscountType =
        selectedChannel?.dataset.discountType || 'none';

    const channelDiscountValue = Math.max(
        0,
        parseFloat(
            selectedChannel?.dataset.discountValue
        ) || 0
    );

    let channelDiscountAmount = 0;

    if (channelDiscountType === 'percentage') {
        channelDiscountAmount =
            afterOrderDiscount *
            Math.min(channelDiscountValue, 100) /
            100;
    }

    if (channelDiscountType === 'fixed') {
        channelDiscountAmount = Math.min(
            channelDiscountValue,
            afterOrderDiscount
        );
    }

    /*
     * الصافي النهائي
     */
    currentNet = Math.max(
        0,
        afterOrderDiscount - channelDiscountAmount
    );

    totalInput.value = total.toFixed(2);

    document.getElementById('summaryTotal').textContent =
        '₪' + total.toFixed(2);

    document.getElementById('summaryDiscount').textContent =
        '− ₪' + orderDiscountAmount.toFixed(2);

    document.getElementById(
        'summaryAfterOrderDiscount'
    ).textContent =
        '₪' + afterOrderDiscount.toFixed(2);

    document.getElementById(
        'summaryChannelDiscount'
    ).textContent =
        '− ₪' + channelDiscountAmount.toFixed(2);

    document.getElementById('summaryNet').textContent =
        '₪' + currentNet.toFixed(2);

    /*
     * تحديث معاينة قناة البيع
     */
    const channelPreview = document.getElementById(
        'salesChannelPreview'
    );

    if (channelPreview && selectedChannel) {
        channelPreview.textContent =
            selectedChannel.dataset.name +
            ': خصم القناة ₪' +
            channelDiscountAmount.toFixed(2) +
            ' — المبلغ النهائي ₪' +
            currentNet.toFixed(2) +
            '.';
    }

    /*
     * تحديث المبلغ المدفوع
     */
    paidInput.max = currentNet.toFixed(2);

    if (arrangement.value === 'pay_now') {
        paidInput.value = currentNet.toFixed(2);
    } else if (
        (parseFloat(paidInput.value) || 0) > currentNet
    ) {
        paidInput.value = currentNet.toFixed(2);
    }
}
    function updateDiscountField() {
        const type = discountType.value;
        discountValueGroup.hidden = type === 'none';
        discountValue.required = type !== 'none';
        discountValueLabel.textContent = type === 'percentage' ? 'نسبة الخصم (%)' : 'مبلغ الخصم (₪)';
        if (type === 'none') discountValue.value = 0;
        calculateTotals();
    }

    function selectedPaymentMethod() {
        return document.querySelector('input[name="payment_method_id"]:checked');
    }

    function updateCustomerInfo() {
        const option = customerSelect.selectedOptions[0];
        if (!customerSelect.value || !option) {
            customerCreditInfo.hidden = true;
            customerCreditInfo.innerHTML = '';
            return;
        }

        const balance = parseFloat(option.dataset.balance || 0) || 0;
        const creditLimit = option.dataset.creditLimit;
        const allowCredit = option.dataset.allowCredit === '1';

        customerCreditInfo.hidden = false;
        customerCreditInfo.innerHTML =
            `<strong>${option.dataset.type || 'عميل'}</strong> · ${option.dataset.scope || ''}` +
            ` — رصيد هذا الفرع: <strong>₪${balance.toFixed(2)}</strong>` +
            (allowCredit ? ` — آجل: <strong>مسموح</strong> (${option.dataset.billing || 'فوري'}, ${option.dataset.terms || 0} يوم)` : ' — آجل: غير مسموح');
    }

    function updatePaymentFields() {
        const value = arrangement.value;
        const method = selectedPaymentMethod();
        const quick = quickSaleInput.value === '1';

        const noImmediatePayment = ['pay_on_pickup', 'on_account'].includes(value);
        const needsPaid = ['deposit', 'partial_payment', 'pay_now'].includes(value);
        const requiresVerification = method?.dataset.requiresVerification === '1';
        const requiresReference = method?.dataset.requiresReference === '1';
        const needsVerification = value === 'pending_verification' || requiresVerification;
        const needsReference = needsVerification || requiresReference;

        paymentMethodCard.hidden = noImmediatePayment;

        paidGroup.hidden = !needsPaid || noImmediatePayment;
        paidInput.required = needsPaid && !noImmediatePayment;
        paidInput.readOnly = value === 'pay_now';

        verificationGroup.hidden = noImmediatePayment || (!needsReference && !needsVerification);
        referenceInput.required = !noImmediatePayment && needsReference;
        proofInput.required = !noImmediatePayment && needsVerification;

        if (noImmediatePayment) {
            referenceInput.required = false;
            proofInput.required = false;
        }

        if (value === 'pay_now') {
            paidInput.value = currentNet.toFixed(2);
        }

        // البيع السريع يجب أن يملك طريقة دفع.
        paymentMethodInputs.forEach(input => {
            input.required = quick && !noImmediatePayment;
        });

        // رسالة البيع السريع حسب طريقة الدفع.
        if (quick && quickPaymentHint) {
            if (!method) {
                quickPaymentHint.innerHTML = 'اختر طريقة الدفع للمتابعة.';
                quickPaymentHint.style.color = 'var(--text-muted)';
                submitOrderBtn.textContent = 'تنفيذ البيع وطباعة الفاتورة';
            } else if (requiresVerification) {
                quickPaymentHint.innerHTML = 'هذه الطريقة تحتاج تحقق من الدفع. سيتم تسجيل العملية بانتظار التحقق، ولن يتم خصم المخزون أو إصدار الفاتورة قبل اعتماد الدفع.';
                quickPaymentHint.style.color = '#b42318';
                submitOrderBtn.textContent = 'تسجيل الدفع للمراجعة';
            } else {
                const methodName = method.dataset.name || 'طريقة الدفع المحددة';
                quickPaymentHint.innerHTML = `طريقة الدفع: <strong>${methodName}</strong> — سيتم تأكيد البيع فورًا وخصم المخزون وإنشاء الفاتورة وفتحها للطباعة.`;
                quickPaymentHint.style.color = '#198754';
                submitOrderBtn.textContent = 'تنفيذ البيع وطباعة الفاتورة';
            }
        } else if (quickPaymentHint) {
            quickPaymentHint.innerHTML = '';
        }

        if (value === 'on_account') {
            const option = customerSelect.selectedOptions[0];

            if (!customerSelect.value || option?.dataset.allowCredit !== '1') {
                arrangement.setCustomValidity('اختر عميلاً مسموحًا له بالشراء على الحساب.');
            } else {
                arrangement.setCustomValidity('');
            }
        } else {
            arrangement.setCustomValidity('');
        }
    }

    function setSaleMode(mode) {
        const quick = mode === 'quick';

        quickSaleInput.value = quick ? '1' : '0';

        saleModeButtons.forEach(button => {
            button.classList.toggle('active', button.dataset.mode === mode);
        });

        quickSaleNote.hidden = !quick;
        customerGroup.style.display = quick ? 'none' : '';

        if (quick) {
            // البيع السريع = عميل غير مسجل.
            customerSelect.value = '';

            // البيع السريع = دفع فوري، لكن طريقة الدفع يختارها المستخدم.
            arrangement.value = 'pay_now';
            arrangement.disabled = true;
            paymentArrangementHelp.hidden = false;
            quickPaymentArrangementInput.disabled = false;

            submitOrderBtn.textContent = 'تنفيذ البيع وطباعة الفاتورة';
        } else {
            arrangement.disabled = false;
            paymentArrangementHelp.hidden = true;
            quickPaymentArrangementInput.disabled = true;

            submitOrderBtn.textContent = 'إنشاء الطلب';
        }

        updateCustomerInfo();
        calculateTotals();
        updatePaymentFields();
    }


    addItemButton.addEventListener('click', () => {
        const row = template.content.firstElementChild.cloneNode(true);
        container.appendChild(row);
        renameRows();
        calculateTotals();
    });

    container.addEventListener('input', event => {
        if (event.target.matches('.product-select,.quantity-input')) calculateTotals();
    });
    container.addEventListener('change', event => {
        if (event.target.matches('.product-select')) calculateTotals();
    });
    container.addEventListener('click', event => {
        if (!event.target.matches('.remove-item')) return;
        if (container.querySelectorAll('.item-row').length === 1) return;
        event.target.closest('.item-row').remove();
        renameRows();
        calculateTotals();
    });



   discountType.addEventListener('change', updateDiscountField);
discountValue.addEventListener('input', calculateTotals);

/* تحديث الحسبة عند تغيير قناة البيع */
document
    .querySelectorAll('input[name="sales_channel_id"]')
    .forEach(input => {
        input.addEventListener('change', calculateTotals);
    });

arrangement.addEventListener('change', updatePaymentFields);
customerSelect.addEventListener('change', () => { updateCustomerInfo(); updatePaymentFields(); });
paymentMethodInputs.forEach(input => input.addEventListener('change', updatePaymentFields));
saleModeButtons.forEach(button => button.addEventListener('click', () => setSaleMode(button.dataset.mode)));

updateDiscountField();
updateCustomerInfo();
setSaleMode(quickSaleInput.value === '1' ? 'quick' : 'registered');
updatePaymentFields();
calculateTotals();

});
</script>
@endsection