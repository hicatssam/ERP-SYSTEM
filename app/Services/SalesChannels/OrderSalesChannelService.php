<?php

namespace App\Services\SalesChannels;

use App\Enums\DiscountType;
use App\Models\Order;
use App\Models\SalesChannel;
use Illuminate\Validation\ValidationException;

class OrderSalesChannelService
{
    public function __construct(private SalesChannelPricingService $pricing) {}

    public function apply(
        Order $order,
        int $salesChannelId,
        string $productDiscountType = 'none',
        mixed $productDiscountValue = 0,
    ): Order {
        $channel = SalesChannel::query()
            ->active()
            ->whereKey($salesChannelId)
            ->first();

        if (! $channel) {
            throw ValidationException::withMessages([
                'sales_channel_id' => 'قناة البيع المحددة غير موجودة أو غير مفعّلة.',
            ]);
        }

        $subtotal = $this->money($order->subtotal);
        $productDiscount = $this->productDiscount(
            $subtotal,
            $productDiscountType,
            $productDiscountValue
        );

        $result = $this->pricing->calculate(
            $channel,
            $subtotal,
            $productDiscount,
            $order->delivery_fee ?? 0,
            $order->tax_amount ?? 0,
        );

        $order->forceFill(array_merge(
            [
                'discount_type' => $productDiscountType,
                'discount_value' => $productDiscountValue,
                'discount_amount' => $productDiscount,
                'total_amount' => $result['customer_total'],
            ],
            $this->pricing->orderSnapshot($channel, $result)
        ))->save();

        return $order->refresh();
    }

    public function invoiceSnapshot(Order $order): array
    {
        return $order->only([
            'sales_channel_id',
            'channel_discount_type',
            'channel_discount_value',
            'channel_discount_amount',
            'channel_discount_funding_rate',
            'channel_discount_funded_by_channel',
            'channel_discount_funded_by_restaurant',
            'total_after_channel_discount',
            'channel_commission_type',
            'channel_commission_value',
            'channel_commission_base',
            'channel_commission_amount',
            'channel_net_revenue',
        ]);
    }

    private function productDiscount(
        string $subtotal,
        string $type,
        mixed $value,
    ): string {
        $value = number_format((float) ($value ?? 0), 3, '.', '');

        if ($type === 'none' || bccomp($value, '0.000', 3) === 0) {
            return '0.00';
        }

        if (bccomp($value, '0.000', 3) < 0) {
            throw ValidationException::withMessages(['discount_value' => 'قيمة الخصم لا يمكن أن تكون سالبة.']);
        }

        if ($type === DiscountType::Percentage->value) {
            if (bccomp($value, '100.000', 3) > 0) {
                throw ValidationException::withMessages(['discount_value' => 'نسبة الخصم لا يمكن أن تتجاوز 100%.']);
            }

            return bcdiv(bcmul($subtotal, $value, 5), '100', 2);
        }

        if ($type !== DiscountType::Fixed->value) {
            throw ValidationException::withMessages(['discount_type' => 'نوع خصم الطلب غير صالح.']);
        }

        $fixed = $this->money($value);
        if (bccomp($fixed, $subtotal, 2) > 0) {
            throw ValidationException::withMessages(['discount_value' => 'قيمة الخصم الثابت لا يمكن أن تتجاوز إجمالي الطلب.']);
        }

        return $fixed;
    }

    private function money(mixed $amount): string
    {
        return number_format((float) ($amount ?? 0), 2, '.', '');
    }
}
