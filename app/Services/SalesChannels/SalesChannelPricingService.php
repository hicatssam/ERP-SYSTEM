<?php

namespace App\Services\SalesChannels;

use App\Enums\CommissionBase;
use App\Enums\DeliveryFeeRecipient;
use App\Enums\DiscountType;
use App\Models\SalesChannel;
use BackedEnum;
use Illuminate\Validation\ValidationException;

class SalesChannelPricingService
{
    public function calculate(
        SalesChannel $channel,
        mixed $subtotal,
        mixed $productDiscount = 0,
        mixed $deliveryFee = 0,
        mixed $tax = 0,
    ): array {
        return $this->calculateFromRules([
            'discount_type' => $this->value($channel->discount_type),
            'discount_value' => $channel->discount_value,
            'discount_funded_by_channel' => $channel->discount_funded_by_channel,
            'commission_type' => $this->value($channel->commission_type),
            'commission_value' => $channel->commission_value,
            'commission_base' => $this->value($channel->commission_base),
            'delivery_fee_recipient' => $this->value($channel->delivery_fee_recipient),
        ], $subtotal, $productDiscount, $deliveryFee, $tax);
    }

    public function calculateFromRules(
        array $rules,
        mixed $subtotal,
        mixed $productDiscount = 0,
        mixed $deliveryFee = 0,
        mixed $tax = 0,
    ): array {
        $subtotal = $this->money($subtotal);
        $productDiscount = $this->money($productDiscount);
        $deliveryFee = $this->money($deliveryFee);
        $tax = $this->money($tax);

        if (bccomp($subtotal, '0.00', 2) < 0) {
            throw ValidationException::withMessages(['subtotal' => 'الإجمالي لا يمكن أن يكون سالبًا.']);
        }

        if (bccomp($productDiscount, '0.00', 2) < 0 || bccomp($productDiscount, $subtotal, 2) > 0) {
            throw ValidationException::withMessages(['discount_value' => 'خصم المنتجات يجب أن يكون بين صفر وإجمالي الطلب.']);
        }

        $grossSales = bcsub($subtotal, $productDiscount, 2);
        $channelDiscount = $this->ruleAmount(
            (string) ($rules['discount_type'] ?? DiscountType::Percentage->value),
            $rules['discount_value'] ?? 0,
            $grossSales,
            'خصم قناة البيع'
        );
        $netSales = bcsub($grossSales, $channelDiscount, 2);

        $fundingRate = $this->percentage($rules['discount_funded_by_channel'] ?? 0, 'نسبة تحمّل القناة للخصم');
        $channelFundedDiscount = bcdiv(bcmul($channelDiscount, $fundingRate, 4), '100', 2);
        $restaurantFundedDiscount = bcsub($channelDiscount, $channelFundedDiscount, 2);

        $commissionBaseType = (string) ($rules['commission_base'] ?? CommissionBase::NetSales->value);
        $commissionBase = $commissionBaseType === CommissionBase::GrossSales->value ? $grossSales : $netSales;
        $commission = $this->ruleAmount(
            (string) ($rules['commission_type'] ?? DiscountType::Percentage->value),
            $rules['commission_value'] ?? 0,
            $commissionBase,
            'عمولة قناة البيع'
        );

        $restaurantDelivery = ($rules['delivery_fee_recipient'] ?? DeliveryFeeRecipient::Restaurant->value)
            === DeliveryFeeRecipient::Restaurant->value ? $deliveryFee : '0.00';

        $customerTotal = bcadd(bcadd($netSales, $deliveryFee, 2), $tax, 2);
        $restaurantNetRevenue = bcadd(
            bcsub(bcadd($netSales, $channelFundedDiscount, 2), $commission, 2),
            $restaurantDelivery,
            2
        );

        return [
            'subtotal' => $subtotal,
            'product_discount' => $productDiscount,
            'gross_sales' => $grossSales,
            'channel_discount' => $channelDiscount,
            'channel_discount_funding_rate' => number_format((float) $fundingRate, 2, '.', ''),
            'channel_funded_discount' => $channelFundedDiscount,
            'restaurant_funded_discount' => $restaurantFundedDiscount,
            'net_sales' => $netSales,
            'commission_base_amount' => $commissionBase,
            'channel_commission' => $commission,
            'delivery_fee' => $deliveryFee,
            'tax' => $tax,
            'customer_total' => $customerTotal,
            'restaurant_net_revenue' => max(0, (float) $restaurantNetRevenue) === 0.0
                ? '0.00'
                : $restaurantNetRevenue,
        ];
    }

    public function orderSnapshot(SalesChannel $channel, array $result): array
    {
        return [
            'sales_channel_id' => $channel->id,
            'channel_discount_type' => $this->value($channel->discount_type),
            'channel_discount_value' => $channel->discount_value,
            'channel_discount_amount' => $result['channel_discount'],
            'channel_discount_funding_rate' => $result['channel_discount_funding_rate'],
            'channel_discount_funded_by_channel' => $result['channel_funded_discount'],
            'channel_discount_funded_by_restaurant' => $result['restaurant_funded_discount'],
            'total_after_channel_discount' => $result['net_sales'],
            'channel_commission_type' => $this->value($channel->commission_type),
            'channel_commission_value' => $channel->commission_value,
            'channel_commission_base' => $this->value($channel->commission_base),
            'channel_commission_amount' => $result['channel_commission'],
            'channel_net_revenue' => $result['restaurant_net_revenue'],
        ];
    }

    private function ruleAmount(string $type, mixed $value, string $base, string $label): string
    {
        $value = number_format((float) ($value ?? 0), 3, '.', '');

        if (bccomp($value, '0.000', 3) < 0) {
            throw ValidationException::withMessages(['discount_value' => "{$label} لا يمكن أن تكون سالبة."]);
        }

        if ($type === DiscountType::Percentage->value) {
            $this->percentage($value, $label);
            return bcdiv(bcmul($base, $value, 5), '100', 2);
        }

        if ($type !== DiscountType::Fixed->value) {
            throw ValidationException::withMessages(['discount_type' => "نوع {$label} غير صالح."]);
        }

        $fixed = $this->money($value);
        if (bccomp($fixed, $base, 2) > 0) {
            throw ValidationException::withMessages(['discount_value' => "{$label} الثابتة لا يمكن أن تتجاوز المبلغ المحتسب عليه."]);
        }

        return $fixed;
    }

    private function percentage(mixed $value, string $label): string
    {
        $value = number_format((float) ($value ?? 0), 2, '.', '');
        if (bccomp($value, '0.00', 2) < 0 || bccomp($value, '100.00', 2) > 0) {
            throw ValidationException::withMessages(['discount_value' => "{$label} يجب أن تكون بين 0 و100."]);
        }

        return $value;
    }

    private function money(mixed $amount): string
    {
        return number_format((float) ($amount ?? 0), 2, '.', '');
    }

    private function value(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }
}
