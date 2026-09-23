@extends('layouts.app')

@section('title', 'الحوالات الواردة')
@section('page-title', 'الحوالات الواردة')

@section('content')
@php
    $hasCreateErrors =
        $errors->has('location_id')
        || $errors->has('payment_method_id')
        || $errors->has('location_payment_account_id')
        || $errors->has('sender_name')
        || $errors->has('sender_phone')
        || $errors->has('sender_account_number')
        || $errors->has('reference_number')
        || $errors->has('amount')
        || $errors->has('received_at')
        || $errors->has('payment_proof')
        || $errors->has('notes');
@endphp

<div class="page-actions incoming-transfer-header">
    <div>
        <div class="page-actions-title">الحوالات الواردة</div>
        <div class="text-muted incoming-transfer-subtitle">
            سجل مستقل للحوالات التي تصل إلى حسابات الفروع قبل مطابقتها مع الطلبات.
        </div>
    </div>

    <div class="incoming-transfer-header-actions">
        <a href="{{ route('payments.bank-sales') }}" class="btn btn-ghost btn-sm">
            المبيعات البنكية
        </a>

        @if(auth()->user()->isAdmin() || auth()->user()->can('payments.record'))
            <button
                type="button"
                class="btn btn-gold btn-sm"
                onclick="openIncomingTransferModal()"
            >
                + إضافة حوالة واردة
            </button>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">
        {{ session('success') }}
    </div>
@endif

<div class="stats-grid incoming-transfer-stats">
    <div class="stat-card stat-blue">
        <div class="stat-info">
            <div class="stat-value">{{ (int) ($summary->transfers_count ?? 0) }}</div>
            <div class="stat-label">عدد الحوالات</div>
        </div>
    </div>

    <div class="stat-card stat-green">
        <div class="stat-info">
            <div class="stat-value">₪{{ number_format((float) ($summary->confirmed_total ?? 0), 2) }}</div>
            <div class="stat-label">المعتمد</div>
        </div>
    </div>

    <div class="stat-card stat-gold">
        <div class="stat-info">
            <div class="stat-value">₪{{ number_format((float) ($summary->pending_total ?? 0), 2) }}</div>
            <div class="stat-label">بانتظار التحقق</div>
        </div>
    </div>

    <div class="stat-card stat-red">
        <div class="stat-info">
            <div class="stat-value">₪{{ number_format((float) ($summary->rejected_total ?? 0), 2) }}</div>
            <div class="stat-label">المرفوض</div>
        </div>
    </div>
</div>

@if($methodBreakdown->isNotEmpty())
    <div class="card incoming-method-card">
        <div class="card-header">
            <span class="card-title">ملخص حسب طريقة الدفع</span>
        </div>
        <div class="card-body incoming-method-grid">
            @foreach($paymentMethods as $method)
                @php
                    $row = $methodBreakdown->get($method->id);
                @endphp

                @if($row)
                    <a
                        href="{{ route('incoming-bank-transfers.index', array_merge(request()->except('page', 'payment_method_id'), ['payment_method_id' => $method->id])) }}"
                        class="incoming-method-item"
                    >
                        <span>{{ $method->name_ar ?: $method->name }}</span>
                        <strong>₪{{ number_format((float) $row->total_amount, 2) }}</strong>
                        <small>{{ (int) $row->transfers_count }} حوالة</small>
                    </a>
                @endif
            @endforeach
        </div>
    </div>
@endif

<div class="filter-row incoming-transfer-filters">
    <form
        method="GET"
        action="{{ route('incoming-bank-transfers.index') }}"
        class="filter-grid"
    >
        <div class="filter-group incoming-search">
            <label class="filter-label">بحث</label>
            <input
                type="search"
                name="search"
                value="{{ request('search') }}"
                class="form-input"
                placeholder="اسم المحوّل، الهاتف، الحساب، المرجع أو حساب الاستلام"
            >
        </div>

        <div class="filter-group">
            <label class="filter-label">الحالة</label>
            <select name="status" class="form-select">
                <option value="">كل الحالات</option>
                @foreach($statusOptions as $status)
                    <option
                        value="{{ $status->value }}"
                        @selected(request('status') === $status->value)
                    >
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">طريقة الدفع</label>
            <select name="payment_method_id" class="form-select">
                <option value="">كل طرق الدفع</option>
                @foreach($paymentMethods as $method)
                    <option
                        value="{{ $method->id }}"
                        @selected((string) request('payment_method_id') === (string) $method->id)
                    >
                        {{ $method->name_ar ?: $method->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">حساب الاستلام</label>
            <select name="location_payment_account_id" class="form-select">
                <option value="">كل الحسابات</option>
                @foreach($paymentAccounts as $account)
                    <option
                        value="{{ $account->id }}"
                        @selected((string) request('location_payment_account_id') === (string) $account->id)
                    >
                        {{ $account->name }}
                        @if($account->provider_name)
                            — {{ $account->provider_name }}
                        @endif
                    </option>
                @endforeach
            </select>
        </div>

        @if($locations->isNotEmpty())
            <div class="filter-group">
                <label class="filter-label">الفرع</label>
                <select name="location_id" class="form-select">
                    <option value="">كل الفروع</option>
                    @foreach($locations as $location)
                        <option
                            value="{{ $location->id }}"
                            @selected((string) request('location_id') === (string) $location->id)
                        >
                            {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="filter-group">
            <label class="filter-label">من تاريخ</label>
            <input
                type="date"
                name="date_from"
                value="{{ request('date_from') }}"
                class="form-input"
            >
        </div>

        <div class="filter-group">
            <label class="filter-label">إلى تاريخ</label>
            <input
                type="date"
                name="date_to"
                value="{{ request('date_to') }}"
                class="form-input"
            >
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-gold btn-sm">تطبيق</button>
            <a href="{{ route('incoming-bank-transfers.index') }}" class="btn btn-ghost btn-sm">
                مسح
            </a>
        </div>
    </form>
</div>

<div class="table-wrap incoming-transfer-table-wrap">
    <table class="data-table incoming-transfer-table">
        <thead>
            <tr>
                <th>تاريخ الوصول</th>
                <th>المحوّل</th>
                <th>الفرع</th>
                <th>طريقة الدفع</th>
                <th>حساب الاستلام</th>
                <th>المبلغ</th>
                <th>رقم الحوالة</th>
                <th>الإثبات</th>
                <th>الحالة</th>
                <th>سجلها</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transfers as $transfer)
                @php
                    $status = $transfer->status;
                    $statusValue = $transfer->statusValue();
                    $account = $transfer->locationPaymentAccount;
                    $accountNumber = $account?->iban
                        ?: ($account?->account_number ?: $account?->phone_number);
                @endphp

                <tr>
                    <td>
                        <strong>{{ $transfer->received_at?->format('Y-m-d') ?? '—' }}</strong>
                        <small class="incoming-muted">
                            {{ $transfer->received_at?->format('H:i') }}
                        </small>
                    </td>

                    <td>
                        <strong>{{ $transfer->sender_name }}</strong>

                        @if($transfer->sender_phone)
                            <small class="incoming-muted" dir="ltr">
                                {{ $transfer->sender_phone }}
                            </small>
                        @endif

                        @if($transfer->sender_account_number)
                            <small class="incoming-muted" dir="ltr">
                                {{ $transfer->sender_account_number }}
                            </small>
                        @endif
                    </td>

                    <td>
                        <strong>{{ $transfer->location?->name ?? '—' }}</strong>
                    </td>

                    <td>
                        <strong>
                            {{ $transfer->paymentMethod?->name_ar
                                ?: ($transfer->paymentMethod?->name ?? '—') }}
                        </strong>
                    </td>

                    <td>
                        @if($account)
                            <div class="incoming-account">
                                <strong>{{ $account->account_holder_name ?: $account->name }}</strong>
                                <small>{{ $account->provider_name ?: $account->name }}</small>
                                <span dir="ltr">{{ $accountNumber ?: 'بدون رقم' }}</span>
                            </div>
                        @else
                            <span class="incoming-muted">غير محدد</span>
                        @endif
                    </td>

                    <td>
                        <strong class="incoming-amount">
                            {{ $transfer->currency_code === 'ILS' ? '₪' : $transfer->currency_code }}
                            {{ number_format((float) $transfer->amount, 2) }}
                        </strong>
                    </td>

                    <td>
                        <strong class="incoming-reference" dir="ltr">
                            {{ $transfer->reference_number }}
                        </strong>
                    </td>

                    <td>
                        @if($transfer->payment_proof)
                            <a
                                href="{{ route('incoming-bank-transfers.proof', $transfer) }}"
                                target="_blank"
                                rel="noopener"
                                class="btn btn-outline btn-xs"
                            >
                                عرض الإثبات
                            </a>
                        @else
                            <span class="incoming-muted">بدون مرفق</span>
                        @endif
                    </td>

                    <td>
                        <span class="badge {{ $status?->badgeClass() ?? 'badge-secondary' }}">
                            {{ $status?->label() ?? $statusValue }}
                        </span>

                        @if($transfer->verifiedBy)
                            <small class="incoming-muted">
                                {{ $transfer->verifiedBy->display_name }}
                                @if($transfer->verified_at)
                                    · {{ $transfer->verified_at->format('Y-m-d H:i') }}
                                @endif
                            </small>
                        @endif

                        @if($statusValue === 'rejected' && $transfer->rejection_reason)
                            <small class="incoming-rejection">
                                {{ $transfer->rejection_reason }}
                            </small>
                        @endif
                    </td>

                    <td>
                        {{ $transfer->createdBy?->display_name ?? '—' }}
                        @if($transfer->notes)
                            <small class="incoming-muted">
                                {{ IlluminateSupportStr::limit($transfer->notes, 80) }}
                            </small>
                        @endif
                    </td>

                    <td>
                        @if(
                            $statusValue === 'pending_verification'
                            && (
                                auth()->user()->isAdmin()
                                || auth()->user()->can('payments.verify')
                            )
                        )
                            <div class="incoming-actions">
                                <form
                                    method="POST"
                                    action="{{ route('incoming-bank-transfers.verify', $transfer) }}"
                                >
                                    @csrf
                                    <input type="hidden" name="action" value="verify">
                                    <button
                                        type="submit"
                                        class="btn btn-success btn-xs"
                                        onclick="return confirm('اعتماد هذه الحوالة؟')"
                                    >
                                        اعتماد
                                    </button>
                                </form>

                                <form
                                    method="POST"
                                    action="{{ route('incoming-bank-transfers.verify', $transfer) }}"
                                    class="incoming-reject-form"
                                >
                                    @csrf
                                    <input type="hidden" name="action" value="reject">
                                    <input
                                        type="text"
                                        name="rejection_reason"
                                        class="form-input"
                                        maxlength="500"
                                        required
                                        placeholder="سبب الرفض"
                                    >
                                    <button
                                        type="submit"
                                        class="btn btn-danger btn-xs"
                                    >
                                        رفض
                                    </button>
                                </form>
                            </div>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">
                        <div class="empty-state-sm">
                            لا توجد حوالات واردة تطابق الفلاتر الحالية.
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($transfers->hasPages())
    <div style="margin-top:1rem">
        {{ $transfers->links() }}
    </div>
@endif

@if(auth()->user()->isAdmin() || auth()->user()->can('payments.record'))
    <div
        id="incomingTransferModal"
        class="incoming-transfer-modal {{ $hasCreateErrors ? 'is-open' : '' }}"
        aria-hidden="{{ $hasCreateErrors ? 'false' : 'true' }}"
        onclick="closeIncomingTransferModalFromBackdrop(event)"
    >
        <div
            class="incoming-transfer-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="incomingTransferModalTitle"
        >
            <div class="incoming-modal-head">
                <div>
                    <span>الحوالات الواردة</span>
                    <h3 id="incomingTransferModalTitle">تسجيل حوالة جديدة</h3>
                    <p>أدخل بيانات الحوالة كما ظهرت في كشف البنك أو المحفظة.</p>
                </div>

                <button
                    type="button"
                    class="incoming-modal-close"
                    onclick="closeIncomingTransferModal()"
                >
                    ×
                </button>
            </div>

            <form
                method="POST"
                action="{{ route('incoming-bank-transfers.store') }}"
                enctype="multipart/form-data"
                id="incomingTransferForm"
            >
                @csrf

                <div class="incoming-form-grid">
                    @if($canViewAll)
                        <div class="form-group">
                            <label class="form-label">الفرع *</label>
                            <select
                                name="location_id"
                                id="incomingLocation"
                                class="form-select"
                                required
                            >
                                <option value="">اختر الفرع</option>
                                @foreach($locations as $location)
                                    <option
                                        value="{{ $location->id }}"
                                        @selected((string) old('location_id', $currentLocationId) === (string) $location->id)
                                    >
                                        {{ $location->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('location_id')
                                <div class="incoming-error">{{ $message }}</div>
                            @enderror
                        </div>
                    @else
                        <input
                            type="hidden"
                            name="location_id"
                            id="incomingLocation"
                            value="{{ $currentLocationId }}"
                        >
                    @endif

                    <div class="form-group">
                        <label class="form-label">طريقة الدفع *</label>
                        <select
                            name="payment_method_id"
                            id="incomingPaymentMethod"
                            class="form-select"
                            required
                        >
                            <option value="">اختر طريقة الدفع</option>

                            @foreach($paymentMethods as $method)
                                @php
                                    $activeAssignments = $method->locationPaymentMethods
                                        ->where('is_active', true);

                                    $allowedLocations = $activeAssignments->isEmpty()
                                        ? '*'
                                        : $activeAssignments
                                            ->pluck('location_id')
                                            ->map(fn ($id) => (string) $id)
                                            ->implode(',');
                                @endphp

                                <option
                                    value="{{ $method->id }}"
                                    data-locations="{{ $allowedLocations }}"
                                    @selected((string) old('payment_method_id') === (string) $method->id)
                                >
                                    {{ $method->name_ar ?: $method->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('payment_method_id')
                            <div class="incoming-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">حساب الاستلام</label>
                        <select
                            name="location_payment_account_id"
                            id="incomingPaymentAccount"
                            class="form-select"
                        >
                            <option value="">اختر حساب الاستلام</option>
                            @foreach($paymentAccounts as $account)
                                @php
                                    $displayNumber = $account->iban
                                        ?: ($account->account_number ?: $account->phone_number);
                                @endphp

                                <option
                                    value="{{ $account->id }}"
                                    data-location="{{ $account->location_id }}"
                                    data-method="{{ $account->payment_method_id }}"
                                    @selected((string) old('location_payment_account_id') === (string) $account->id)
                                >
                                    {{ $account->name }}
                                    @if($account->provider_name)
                                        — {{ $account->provider_name }}
                                    @endif
                                    @if($displayNumber)
                                        — {{ $displayNumber }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <small id="incomingAccountHint" class="incoming-field-hint"></small>
                        @error('location_payment_account_id')
                            <div class="incoming-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">المبلغ *</label>
                        <input
                            type="number"
                            name="amount"
                            class="form-input"
                            step="0.01"
                            min="0.01"
                            required
                            value="{{ old('amount') }}"
                        >
                        @error('amount')
                            <div class="incoming-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">اسم المحوّل *</label>
                        <input
                            type="text"
                            name="sender_name"
                            class="form-input"
                            maxlength="150"
                            required
                            value="{{ old('sender_name') }}"
                        >
                        @error('sender_name')
                            <div class="incoming-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">رقم الجوال</label>
                        <input
                            type="text"
                            name="sender_phone"
                            class="form-input"
                            maxlength="50"
                            dir="ltr"
                            value="{{ old('sender_phone') }}"
                        >
                        @error('sender_phone')
                            <div class="incoming-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">رقم الحساب / المحفظة</label>
                        <input
                            type="text"
                            name="sender_account_number"
                            class="form-input"
                            maxlength="120"
                            dir="ltr"
                            value="{{ old('sender_account_number') }}"
                        >
                        <small class="incoming-field-hint">
                            يكفي رقم الجوال أو رقم الحساب/المحفظة.
                        </small>
                        @error('sender_account_number')
                            <div class="incoming-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">رقم الحوالة / المرجع *</label>
                        <input
                            type="text"
                            name="reference_number"
                            class="form-input"
                            maxlength="120"
                            required
                            dir="ltr"
                            value="{{ old('reference_number') }}"
                        >
                        @error('reference_number')
                            <div class="incoming-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">وقت وصول الحوالة</label>
                        <input
                            type="datetime-local"
                            name="received_at"
                            class="form-input"
                            value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}"
                        >
                        @error('received_at')
                            <div class="incoming-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">إثبات الحوالة</label>
                        <input
                            type="file"
                            name="payment_proof"
                            class="form-input"
                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                        >
                        <small class="incoming-field-hint">
                            صورة أو PDF — بحد أقصى 10MB
                        </small>
                        @error('payment_proof')
                            <div class="incoming-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group incoming-wide">
                        <label class="form-label">ملاحظات</label>
                        <textarea
                            name="notes"
                            class="form-input"
                            rows="3"
                            maxlength="2000"
                        >{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="incoming-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="incoming-modal-foot">
                    <button
                        type="button"
                        class="btn btn-ghost"
                        onclick="closeIncomingTransferModal()"
                    >
                        إلغاء
                    </button>

                    <button
                        type="submit"
                        class="btn btn-gold"
                        id="incomingTransferSubmit"
                    >
                        حفظ الحوالة
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

@push('styles')
<style>
.incoming-transfer-header {
    align-items: flex-start;
}

.incoming-transfer-header-actions {
    display:flex;
    gap:.55rem;
    flex-wrap:wrap;
}

.incoming-transfer-subtitle {
    margin-top:.25rem;
    font-size:.78rem;
}

.incoming-transfer-stats {
    grid-template-columns:repeat(4,minmax(0,1fr));
    margin-bottom:1rem;
}

.incoming-method-card {
    margin-bottom:1rem;
}

.incoming-method-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:.65rem;
}

.incoming-method-item {
    display:grid;
    gap:.18rem;
    padding:.75rem;
    border:1px solid var(--border);
    border-radius:12px;
    background:var(--off-white);
    text-decoration:none;
    color:var(--text);
}

.incoming-method-item span,
.incoming-method-item small {
    color:var(--text-muted);
    font-size:.7rem;
}

.incoming-method-item strong {
    font-size:.92rem;
}

.incoming-transfer-filters {
    margin-bottom:1rem;
}

.incoming-transfer-filters .filter-grid {
    grid-template-columns:repeat(4,minmax(0,1fr));
}

.incoming-search {
    grid-column:span 2;
}

.filter-actions {
    display:flex;
    align-items:flex-end;
    gap:.5rem;
}

.incoming-transfer-table {
    min-width:1550px;
}

.incoming-transfer-table td {
    vertical-align:top;
}

.incoming-muted,
.incoming-field-hint {
    display:block;
    margin-top:.18rem;
    color:var(--text-muted);
    font-size:.68rem;
    line-height:1.5;
}

.incoming-account {
    display:grid;
    gap:.15rem;
    min-width:150px;
}

.incoming-account small {
    color:var(--text-muted);
    font-size:.68rem;
}

.incoming-account span,
.incoming-reference {
    font-size:.72rem;
    overflow-wrap:anywhere;
}

.incoming-amount {
    color:#16845b;
    white-space:nowrap;
}

.incoming-rejection,
.incoming-error {
    display:block;
    margin-top:.25rem;
    color:#b42318;
    font-size:.7rem;
}

.incoming-actions {
    display:grid;
    gap:.4rem;
    min-width:230px;
}

.incoming-actions form {
    margin:0;
}

.incoming-reject-form {
    display:grid;
    grid-template-columns:minmax(130px,1fr) auto;
    gap:.35rem;
}

.incoming-reject-form .form-input {
    min-height:30px;
    padding:.35rem .5rem;
    font-size:.7rem;
}

.incoming-transfer-modal {
    position:fixed;
    inset:0;
    z-index:100000;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:1rem;
    background:rgba(15,23,42,.62);
    backdrop-filter:blur(3px);
    opacity:0;
    visibility:hidden;
    pointer-events:none;
    transition:.18s ease;
}

.incoming-transfer-modal.is-open {
    opacity:1;
    visibility:visible;
    pointer-events:auto;
}

.incoming-transfer-dialog {
    width:min(900px,100%);
    max-height:92vh;
    overflow:auto;
    border-radius:18px;
    border:1px solid var(--border);
    background:#fff;
    box-shadow:0 28px 70px rgba(0,0,0,.24);
}

.incoming-modal-head {
    position:sticky;
    top:0;
    z-index:2;
    display:flex;
    justify-content:space-between;
    gap:1rem;
    padding:1.1rem 1.25rem;
    border-bottom:1px solid var(--border);
    background:#fff;
}

.incoming-modal-head > div > span {
    color:var(--gold);
    font-size:.68rem;
    font-weight:900;
}

.incoming-modal-head h3 {
    margin:.2rem 0 0;
    font-size:1.05rem;
}

.incoming-modal-head p {
    margin:.3rem 0 0;
    color:var(--text-muted);
    font-size:.75rem;
}

.incoming-modal-close {
    width:34px;
    height:34px;
    border:1px solid var(--border);
    border-radius:50%;
    background:#fff;
    font-size:1.3rem;
    cursor:pointer;
}

.incoming-form-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:1rem;
    padding:1.25rem;
}

.incoming-wide {
    grid-column:1 / -1;
}

.incoming-modal-foot {
    position:sticky;
    bottom:0;
    display:flex;
    justify-content:flex-end;
    gap:.6rem;
    padding:1rem 1.25rem;
    border-top:1px solid var(--border);
    background:var(--off-white);
}

@media(max-width:1000px) {
    .incoming-transfer-stats,
    .incoming-method-grid,
    .incoming-transfer-filters .filter-grid {
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:650px) {
    .incoming-transfer-header,
    .incoming-transfer-header-actions {
        align-items:stretch;
        flex-direction:column;
    }

    .incoming-transfer-stats,
    .incoming-method-grid,
    .incoming-transfer-filters .filter-grid,
    .incoming-form-grid {
        grid-template-columns:1fr;
    }

    .incoming-search,
    .incoming-wide {
        grid-column:auto;
    }
}
</style>
@endpush

@push('scripts')
<script>
function openIncomingTransferModal() {
    const modal = document.getElementById('incomingTransferModal');

    if (!modal) {
        return;
    }

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    syncIncomingTransferFields();
}

function closeIncomingTransferModal() {
    const modal = document.getElementById('incomingTransferModal');

    if (!modal) {
        return;
    }

    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function closeIncomingTransferModalFromBackdrop(event) {
    if (event.target.id === 'incomingTransferModal') {
        closeIncomingTransferModal();
    }
}

function syncIncomingTransferFields() {
    const location = document.getElementById('incomingLocation');
    const method = document.getElementById('incomingPaymentMethod');
    const account = document.getElementById('incomingPaymentAccount');
    const accountHint = document.getElementById('incomingAccountHint');

    if (!location || !method || !account) {
        return;
    }

    const locationId = String(location.value || '');

    Array.from(method.options).forEach((option, index) => {
        if (index === 0) {
            return;
        }

        const allowed = String(option.dataset.locations || '*');
        const visible =
            allowed === '*'
            || (
                locationId !== ''
                && allowed.split(',').includes(locationId)
            );

        option.hidden = !visible;
        option.disabled = !visible;

        if (!visible && option.selected) {
            option.selected = false;
        }
    });

    const methodId = String(method.value || '');
    let accountCount = 0;

    Array.from(account.options).forEach((option, index) => {
        if (index === 0) {
            return;
        }

        const visible =
            locationId !== ''
            && methodId !== ''
            && option.dataset.location === locationId
            && option.dataset.method === methodId;

        option.hidden = !visible;
        option.disabled = !visible;

        if (visible) {
            accountCount += 1;
        } else if (option.selected) {
            option.selected = false;
        }
    });

    account.required = accountCount > 0;

    if (accountCount === 1 && !account.value) {
        const onlyOption = Array.from(account.options).find(
            option => !option.disabled && option.value !== ''
        );

        if (onlyOption) {
            onlyOption.selected = true;
        }
    }

    if (accountHint) {
        accountHint.textContent =
            methodId !== '' && accountCount === 0
                ? 'لا يوجد حساب استلام مفعّل لهذه الطريقة في الفرع، ويمكن حفظ الحوالة بدون حساب محدد.'
                : (
                    accountCount > 0
                        ? 'اختر الحساب الذي وصلت إليه الحوالة.'
                        : ''
                );
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const location = document.getElementById('incomingLocation');
    const method = document.getElementById('incomingPaymentMethod');
    const form = document.getElementById('incomingTransferForm');

    location?.addEventListener('change', syncIncomingTransferFields);
    method?.addEventListener('change', syncIncomingTransferFields);

    syncIncomingTransferFields();

    if (document.getElementById('incomingTransferModal')?.classList.contains('is-open')) {
        document.body.style.overflow = 'hidden';
    }

    form?.addEventListener('submit', function () {
        const button = document.getElementById('incomingTransferSubmit');

        if (button) {
            button.disabled = true;
            button.textContent = 'جاري حفظ الحوالة...';
        }
    });
});

document.addEventListener('keydown', function (event) {
    if (
        event.key === 'Escape'
        && document.getElementById('incomingTransferModal')?.classList.contains('is-open')
    ) {
        closeIncomingTransferModal();
    }
});
</script>
@endpush
