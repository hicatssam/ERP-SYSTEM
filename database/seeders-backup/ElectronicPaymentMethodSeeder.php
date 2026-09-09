<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Location;
use App\Models\LocationPaymentMethod;
use App\Models\PaymentMethod;

class ElectronicPaymentMethodSeeder extends Seeder
{
    /**
     * Seed Pal Pay, Bank of Palestine, and Jawwal Pay payment methods.
     * Safe to run multiple times (uses updateOrInsert).
     */
    public function run(): void
    {
        $methods = [
            [
                'name'                  => 'Cash',
                'name_ar'               => 'نقداً',
                'code'                  => 'CASH',
                'type'                  => 'cash',
                'requires_verification' => false,
                'requires_reference'    => false,
                'is_active'             => true,
                'sort_order'            => 1,
            ],
            [
                'name'                  => 'Pal Pay',
                'name_ar'               => 'بال باي',
                'code'                  => 'pal_pay',
                'type'                  => 'electronic_wallet',
                'requires_verification' => true,
                'requires_reference'    => true,
                'is_active'             => true,
                'sort_order'            => 5,
            ],
            [
                'name'                  => 'Bank of Palestine',
                'name_ar'               => 'بنك فلسطين',
                'code'                  => 'bank_of_palestine',
                'type'                  => 'bank_transfer',
                'requires_verification' => true,
                'requires_reference'    => true,
                'is_active'             => true,
                'sort_order'            => 6,
            ],
            [
                'name'                  => 'Jawwal Pay',
                'name_ar'               => 'جوال باي',
                'code'                  => 'jawwal_pay',
                'type'                  => 'electronic_wallet',
                'requires_verification' => true,
                'requires_reference'    => true,
                'is_active'             => true,
                'sort_order'            => 7,
            ],
        ];

        $locations = Location::query()->get();

        foreach ($methods as $data) {
            $method = PaymentMethod::withTrashed()->where('code', $data['code'])->first();

            if (! $method) {
                $method = new PaymentMethod(['code' => $data['code']]);
            } elseif (method_exists($method, 'trashed') && $method->trashed()) {
                $method->restore();
            }

            $method->fill($data);
            $method->save();

            foreach ($locations as $location) {
                LocationPaymentMethod::query()->updateOrCreate(
                    [
                        'location_id' => $location->id,
                        'payment_method_id' => $method->id,
                    ],
                    ['is_active' => true],
                );
            }
        }
    }
}
