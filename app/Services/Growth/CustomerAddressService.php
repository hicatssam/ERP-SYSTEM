<?php

namespace App\Services\Growth;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerAddressService
{
    public function create(Customer $customer, array $data, User $user): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $data, $user): CustomerAddress {
            $hasActive = CustomerAddress::query()->where('customer_id', $customer->id)->where('is_active', true)->lockForUpdate()->exists();
            $makeDefault = (bool) ($data['is_default'] ?? false) || ! $hasActive;

            if ($makeDefault) {
                CustomerAddress::query()->where('customer_id', $customer->id)->update(['is_default' => false]);
            }

            return CustomerAddress::query()->create(array_merge($data, [
                'customer_id' => $customer->id,
                'created_by' => $user->id,
                'is_default' => $makeDefault,
                'is_active' => true,
            ]));
        });
    }

    public function update(CustomerAddress $address, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($address, $data): CustomerAddress {
            $locked = CustomerAddress::query()->whereKey($address->id)->lockForUpdate()->firstOrFail();
            $makeDefault = (bool) ($data['is_default'] ?? false);
            if ($makeDefault) {
                CustomerAddress::query()->where('customer_id', $locked->customer_id)->whereKeyNot($locked->id)->update(['is_default' => false]);
            }
            $locked->update(array_merge($data, ['is_default' => $makeDefault ?: $locked->is_default]));
            return $locked->fresh();
        });
    }

    public function setDefault(CustomerAddress $address): CustomerAddress
    {
        return DB::transaction(function () use ($address): CustomerAddress {
            $locked = CustomerAddress::query()->whereKey($address->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->is_active, 422, 'لا يمكن تعيين عنوان غير فعال كعنوان افتراضي.');
            CustomerAddress::query()->where('customer_id', $locked->customer_id)->update(['is_default' => false]);
            $locked->update(['is_default' => true]);
            return $locked->fresh();
        });
    }

    public function deactivate(CustomerAddress $address): void
    {
        DB::transaction(function () use ($address): void {
            $locked = CustomerAddress::query()->whereKey($address->id)->lockForUpdate()->firstOrFail();
            $wasDefault = $locked->is_default;
            $locked->update(['is_active' => false, 'is_default' => false]);
            if ($wasDefault) {
                $replacement = CustomerAddress::query()->where('customer_id', $locked->customer_id)->where('is_active', true)->orderBy('id')->lockForUpdate()->first();
                $replacement?->update(['is_default' => true]);
            }
        });
    }
}
