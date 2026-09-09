<?php

namespace Tests\Unit;

use App\Services\SalesChannels\SalesChannelPricingService;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SalesChannelPricingServiceTest extends TestCase
{
    #[DataProvider('pricingCases')]
    public function test_it_calculates_channel_pricing(array $rules, array $expected): void
    {
        $result = app(SalesChannelPricingService::class)
            ->calculateFromRules($rules, '100.00', '0.00');

        foreach ($expected as $key => $value) {
            $this->assertSame($value, $result[$key]);
        }
    }

    public static function pricingCases(): array
    {
        return [
            'five percent discount' => [[
                'discount_type' => 'percentage',
                'discount_value' => 5,
                'discount_funded_by_channel' => 0,
                'commission_type' => 'percentage',
                'commission_value' => 0,
                'commission_base' => 'net_sales',
                'delivery_fee_recipient' => 'restaurant',
            ], [
                'channel_discount' => '5.00',
                'net_sales' => '95.00',
                'restaurant_net_revenue' => '95.00',
            ]],
            'fixed discount with platform funding and commission' => [[
                'discount_type' => 'fixed',
                'discount_value' => 10,
                'discount_funded_by_channel' => 50,
                'commission_type' => 'percentage',
                'commission_value' => 10,
                'commission_base' => 'net_sales',
                'delivery_fee_recipient' => 'channel',
            ], [
                'channel_discount' => '10.00',
                'channel_funded_discount' => '5.00',
                'channel_commission' => '9.00',
                'restaurant_net_revenue' => '86.00',
            ]],
        ];
    }

    public function test_it_rejects_percentage_above_one_hundred(): void
    {
        $this->expectException(ValidationException::class);

        app(SalesChannelPricingService::class)->calculateFromRules([
            'discount_type' => 'percentage',
            'discount_value' => 101,
        ], 100);
    }

    public function test_it_rejects_fixed_discount_above_the_base(): void
    {
        $this->expectException(ValidationException::class);

        app(SalesChannelPricingService::class)->calculateFromRules([
            'discount_type' => 'fixed',
            'discount_value' => 101,
        ], 100);
    }
}
