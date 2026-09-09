@extends('layouts.app')
@section('title','مهمة توصيل جديدة')

@section('content')
@include('growth._styles')

<div class="page-header">
    <div>
        <h1 class="page-heading">مهمة توصيل جديدة</h1>
        <p class="page-subheading">
            <a href="{{ route('delivery.tasks.index') }}">التوصيل</a> ‹ جديد
        </p>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul style="margin:0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('delivery.tasks.store') }}" id="deliveryTaskForm">
            @csrf

            <div class="growth-form-grid">
                <div>
                    <label class="form-label">الطلب *</label>
                    <select class="form-select" name="order_id" id="deliveryOrder" required>
                        <option value="">اختر طلباً</option>
                        @foreach($orders as $order)
                            <option
                                value="{{ $order->id }}"
                                data-customer="{{ $order->customer_id }}"
                                data-location="{{ $order->location_id }}"
                                data-recipient="{{ $order->customer?->name ?? '' }}"
                                data-phone="{{ $order->customer?->phone ?? '' }}"
                                @selected((string)old('order_id', request('order_id')) === (string)$order->id)
                            >
                                {{ $order->order_number }} —
                                {{ $order->customer?->name ?? 'عميل نقدي' }} —
                                ₪{{ number_format((float)$order->total_amount,2) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">منطقة التوصيل</label>
                    <select class="form-select" name="delivery_zone_id" id="deliveryZone">
                        <option value="">بدون منطقة محددة</option>
                        @foreach($zones as $zone)
                            <option
                                value="{{ $zone->id }}"
                                data-location="{{ $zone->location_id }}"
                                data-minimum="{{ (float)$zone->minimum_order_amount }}"
                                @selected((string)old('delivery_zone_id') === (string)$zone->id)
                            >
                                {{ $zone->location?->name }} — {{ $zone->name }} —
                                ₪{{ number_format((float)$zone->fee,2) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">عنوان محفوظ</label>
                    <select class="form-select" name="customer_address_id" id="deliveryAddress">
                        <option value="">اكتب العنوان يدوياً</option>
                        @foreach($addresses as $customerId => $rows)
                            @foreach($rows as $address)
                                <option
                                    value="{{ $address->id }}"
                                    data-customer="{{ $customerId }}"
                                    data-recipient="{{ $address->recipient_name ?? '' }}"
                                    data-phone="{{ $address->phone ?? '' }}"
                                    @selected((string)old('customer_address_id') === (string)$address->id)
                                >
                                    {{ $address->customer?->name ?? 'عميل' }} —
                                    {{ $address->label }} — {{ $address->displayAddress() }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                    <small class="text-muted" id="deliveryAddressHint"></small>
                </div>

                <div>
                    <label class="form-label">اسم المستلم</label>
                    <input
                        class="form-input"
                        name="recipient_name"
                        id="deliveryRecipient"
                        value="{{ old('recipient_name') }}"
                    >
                </div>

                <div>
                    <label class="form-label">هاتف المستلم</label>
                    <input
                        class="form-input"
                        name="recipient_phone"
                        id="deliveryPhone"
                        value="{{ old('recipient_phone') }}"
                    >
                </div>

                <div>
                    <label class="form-label">العنوان اليدوي</label>
                    <input
                        class="form-input"
                        name="address_snapshot"
                        value="{{ old('address_snapshot') }}"
                        placeholder="يستخدم إذا لم تختَر عنواناً محفوظاً"
                    >
                </div>

                <div style="grid-column:1/-1">
                    <label class="form-label">ملاحظات</label>
                    <textarea class="form-input" name="notes">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="growth-note" style="margin:.8rem 0">
                المناطق والعناوين تُفلتر تلقائياً بحسب الطلب المختار. رسوم المنطقة تُحفظ
                Snapshot داخل مهمة التوصيل فقط، ولا يتم تعديل إجمالي الطلب أو الفاتورة في Sprint 08.
            </div>

            <button class="btn btn-gold">إنشاء المهمة</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const order = document.getElementById('deliveryOrder');
    const zone = document.getElementById('deliveryZone');
    const address = document.getElementById('deliveryAddress');
    const recipient = document.getElementById('deliveryRecipient');
    const phone = document.getElementById('deliveryPhone');
    const addressHint = document.getElementById('deliveryAddressHint');

    const syncOptions = () => {
        const selectedOrder = order.options[order.selectedIndex];
        const customerId = selectedOrder?.dataset.customer || '';
        const locationId = selectedOrder?.dataset.location || '';

        [...zone.options].forEach((option, index) => {
            if (index === 0) return;
            const visible = !locationId || option.dataset.location === locationId;
            option.hidden = !visible;
            option.disabled = !visible;
        });
        if (zone.selectedOptions[0]?.disabled) zone.value = '';

        let visibleAddresses = 0;
        [...address.options].forEach((option, index) => {
            if (index === 0) return;
            const visible = customerId && option.dataset.customer === customerId;
            option.hidden = !visible;
            option.disabled = !visible;
            if (visible) visibleAddresses++;
        });
        if (address.selectedOptions[0]?.disabled) address.value = '';

        addressHint.textContent = customerId
            ? (visibleAddresses ? `${visibleAddresses} عنوان محفوظ متاح لهذا العميل.` : 'لا توجد عناوين محفوظة لهذا العميل.')
            : 'الطلب النقدي يستخدم عنواناً يدوياً فقط.';

        if (!recipient.value) recipient.value = selectedOrder?.dataset.recipient || '';
        if (!phone.value) phone.value = selectedOrder?.dataset.phone || '';
    };

    order.addEventListener('change', () => {
        recipient.value = '';
        phone.value = '';
        address.value = '';
        zone.value = '';
        syncOptions();
    });

    address.addEventListener('change', () => {
        const selected = address.options[address.selectedIndex];
        if (!selected?.value) return;
        if (selected.dataset.recipient) recipient.value = selected.dataset.recipient;
        if (selected.dataset.phone) phone.value = selected.dataset.phone;
    });

    syncOptions();
});
</script>
@endpush
