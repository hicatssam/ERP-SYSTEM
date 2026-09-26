<?php

namespace App\Services\SpecialCakes;

use App\Models\Location;
use App\Models\Payment;
use App\Models\SpecialCakeOrder;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\Procurement\DocumentNumberService;
use App\Services\Payments\PaymentService;
use Illuminate\Validation\ValidationException;

class SpecialCakeOrderService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly PaymentService $payments,
    ) {}

    public function createOrder(array $data, User $user): SpecialCakeOrder
    {
        return DB::transaction(function () use ($data, $user) {
            $prefix   = SystemSetting::get('cake_order_prefix', 'CKO');
            $orderNum = $this->numbers->next('special_cake_order', $prefix);

            $branch  = $user->primaryLocation();
            $factory = Location::where('type', 'factory')->first();

            if (! $branch || ! $factory) {
                throw ValidationException::withMessages([
                    'location' => 'يجب ربط المستخدم بفرع رئيسي وتعريف موقع مصنع قبل إنشاء طلب الكيك.',
                ]);
            }

            $arrangement = $data['payment_arrangement'];

            // Calculate discount amounts if not already computed by the caller
            $totalPrice    = (float) ($data['total_price'] ?? 0);
            $discountType  = $data['discount_type']   ?? 'none';
            $discountValue = (float) ($data['discount_value']  ?? 0);

            $discountAmount = $data['discount_amount'] ?? (function () use ($discountType, $discountValue, $totalPrice) {
                if ($discountType === 'percentage') return $totalPrice * $discountValue / 100;
                if ($discountType === 'fixed')      return min($discountValue, $totalPrice);
                return 0;
            })();

            $netPrice = $data['net_price'] ?? max(0, $totalPrice - $discountAmount);

            $order = SpecialCakeOrder::create([
                'order_number'           => $orderNum,
                'customer_id'            => $data['customer_id'],
                'origin_branch_id'       => $branch?->id,
                'factory_location_id'    => $factory?->id,
                'required_date'          => $data['required_date'],
                'required_time'          => $data['required_time'] ?? null,
                'is_urgent'              => ! empty($data['is_urgent']),
                'urgent_reason'          => ! empty($data['is_urgent'])
                    ? trim((string) ($data['urgent_reason'] ?? ''))
                    : null,
                'cake_type'              => $data['cake_type'] ?? null,
                'cake_size'              => $data['cake_size'] ?? null,
                'cake_weight'            => $data['cake_weight'] ?? null,
                'persons_count'          => $data['persons_count'] ?? null,
                'flavor'                 => $data['flavor'] ?? null,
                'filling'                => $data['filling'] ?? null,
                'shape'                  => $data['shape'] ?? null,
                'color'                  => $data['color'] ?? null,
                'cake_text'              => $data['cake_text'] ?? null,
                'theme'                  => $data['theme'] ?? null,
                'special_instructions'   => $data['special_instructions'] ?? null,
                'image_cover_type'       => $data['image_cover_type'] ?? 'none',
                'total_price'            => $totalPrice,
                'discount_type'          => $discountType,
                'discount_value'         => $discountValue,
                'discount_amount'        => $discountAmount,
                'net_price'              => $netPrice,
                'payment_arrangement'    => $arrangement,
                'status'                 => 'draft',
                'created_by'             => $user->id,
            ]);

            $this->createPaymentIfNeeded($order, $data, $branch?->id, $user);

            return $order;
        });
    }

    private function createPaymentIfNeeded(SpecialCakeOrder $order, array $data, ?int $locationId, User $user): void
    {
        $arrangement = $data['payment_arrangement'];

        // No payment recorded yet if paying on pickup — recorded later from the show page.
        if ($arrangement === 'pay_on_pickup') {
            return;
        }

        if (empty($data['payment_method_id'])) {
            return;
        }

        $this->payments->recordPayment([
            'order_type' => 'special_cake_order',
            'order_id' => $order->id,
            'payment_method_id' => $data['payment_method_id'],
            'location_payment_account_id' => $data['location_payment_account_id'] ?? null,
            'amount' => (float) ($data['paid_amount'] ?? $order->net_price),
            'reference_number' => $data['reference_number'] ?? null,
            'payment_proof' => $data['payment_proof'] ?? null,
            'force_verification' => $arrangement === 'pending_verification',
        ], $user);
    }
}
