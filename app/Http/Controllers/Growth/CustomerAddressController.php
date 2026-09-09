<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\ActivityLogger;
use App\Services\Growth\CustomerAddressService;
use App\Services\Growth\CustomerGrowthAccessService;
use Illuminate\Http\Request;

class CustomerAddressController extends Controller
{
    public function __construct(
        private readonly CustomerGrowthAccessService $access,
        private readonly CustomerAddressService $addresses
    ) {}

    public function store(Request $request, Customer $customer)
    {
        $user = $request->user();
        $this->access->ensureCustomerAccess($customer, $user);

        $address = $this->addresses->create(
            $customer,
            $this->validated($request),
            $user
        );

        ActivityLogger::log(
            userId: $user->id,
            action: 'crm.customer_address.created',
            module: 'crm',
            recordType: 'customer_addresses',
            recordId: $address->id,
            oldValues: null,
            newValues: $this->logValues($address),
            metadata: ['customer_id' => $customer->id],
        );

        return back()->with('success', 'تمت إضافة العنوان.');
    }

    public function update(
        Request $request,
        Customer $customer,
        CustomerAddress $address
    ) {
        $user = $request->user();
        $this->access->ensureCustomerAccess($customer, $user);
        $this->ensureBelongsToCustomer($address, $customer);

        $old = $this->logValues($address);
        $updated = $this->addresses->update($address, $this->validated($request));

        ActivityLogger::log(
            userId: $user->id,
            action: 'crm.customer_address.updated',
            module: 'crm',
            recordType: 'customer_addresses',
            recordId: $address->id,
            oldValues: $old,
            newValues: $this->logValues($updated),
            metadata: ['customer_id' => $customer->id],
        );

        return back()->with('success', 'تم تحديث العنوان.');
    }

    public function setDefault(
        Request $request,
        Customer $customer,
        CustomerAddress $address
    ) {
        $user = $request->user();
        $this->access->ensureCustomerAccess($customer, $user);
        $this->ensureBelongsToCustomer($address, $customer);

        $previousDefaultId = CustomerAddress::query()
            ->where('customer_id', $customer->id)
            ->where('is_default', true)
            ->value('id');

        $updated = $this->addresses->setDefault($address);

        ActivityLogger::log(
            userId: $user->id,
            action: 'crm.customer_address.default_changed',
            module: 'crm',
            recordType: 'customer_addresses',
            recordId: $updated->id,
            oldValues: ['default_address_id' => $previousDefaultId],
            newValues: ['default_address_id' => $updated->id],
            metadata: ['customer_id' => $customer->id],
        );

        return back()->with('success', 'تم تعيين العنوان الافتراضي.');
    }

    public function destroy(
        Request $request,
        Customer $customer,
        CustomerAddress $address
    ) {
        $user = $request->user();
        $this->access->ensureCustomerAccess($customer, $user);
        $this->ensureBelongsToCustomer($address, $customer);

        $old = $this->logValues($address);
        $this->addresses->deactivate($address);
        $address->refresh();

        ActivityLogger::log(
            userId: $user->id,
            action: 'crm.customer_address.deactivated',
            module: 'crm',
            recordType: 'customer_addresses',
            recordId: $address->id,
            oldValues: $old,
            newValues: $this->logValues($address),
            metadata: ['customer_id' => $customer->id],
        );

        return back()->with(
            'success',
            'تم تعطيل العنوان مع إبقاء السجل التاريخي.'
        );
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    private function ensureBelongsToCustomer(
        CustomerAddress $address,
        Customer $customer
    ): void {
        abort_unless(
            (int) $address->customer_id === (int) $customer->id,
            404
        );
    }

    private function logValues(CustomerAddress $address): array
    {
        return $address->only([
            'customer_id',
            'label',
            'recipient_name',
            'phone',
            'address_line1',
            'address_line2',
            'city',
            'area',
            'landmark',
            'is_default',
            'is_active',
        ]);
    }
}
