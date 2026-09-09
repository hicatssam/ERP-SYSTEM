<?php

namespace Database\Seeders;

use App\Enums\CommissionBase;
use App\Enums\DeliveryFeeRecipient;
use App\Enums\DiscountType;
use App\Enums\SalesChannelType;
use App\Enums\SettlementCycle;
use App\Models\SalesChannel;
use Illuminate\Database\Seeder;

class SalesChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            ['name' => 'Branch', 'slug' => 'branch', 'type' => SalesChannelType::Direct, 'discount_value' => 0, 'sort_order' => 10],
            ['name' => 'Seeder', 'slug' => 'seeder', 'type' => SalesChannelType::DeliveryApp, 'discount_value' => 5, 'sort_order' => 20],
            ['name' => 'Website', 'slug' => 'website', 'type' => SalesChannelType::Website, 'discount_value' => 0, 'sort_order' => 30],
            ['name' => 'WhatsApp', 'slug' => 'whatsapp', 'type' => SalesChannelType::Social, 'discount_value' => 0, 'sort_order' => 40],
            ['name' => 'Phone', 'slug' => 'phone', 'type' => SalesChannelType::Phone, 'discount_value' => 0, 'sort_order' => 50],
        ];

        foreach ($channels as $channel) {
            SalesChannel::withTrashed()->updateOrCreate(
                ['slug' => $channel['slug']],
                [
                    'name' => $channel['name'],
                    'type' => $channel['type'],
                    'discount_type' => DiscountType::Percentage,
                    'discount_value' => $channel['discount_value'],
                    'discount_funded_by_channel' => 0,
                    'commission_type' => DiscountType::Percentage,
                    'commission_value' => 0,
                    'commission_base' => CommissionBase::NetSales,
                    'delivery_fee_recipient' => DeliveryFeeRecipient::Restaurant,
                    'settlement_cycle' => SettlementCycle::Monthly,
                    'settlement_days' => 0,
                    'is_active' => true,
                    'sort_order' => $channel['sort_order'],
                    'deleted_at' => null,
                ]
            );
        }
    }
}
