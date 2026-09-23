<?php

namespace Tests\Feature\Restaurant;

use App\Http\Requests\Restaurant\StoreRestaurantPosOrderRequest;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RestaurantPosNonCashDetailsValidationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function non_cash_payment_requires_reference_sender_name_and_phone_or_account(): void
    {
        $method = $this->paymentMethod('bank_transfer');

        $errors = $this->afterValidationErrors([
            'service_type' => 'takeaway',
            'payment_arrangement' => 'pay_now',
            'payment_method_id' => $method->id,
        ]);

        $this->assertTrue($errors->has('reference_number'));
        $this->assertTrue($errors->has('sender_name'));
        $this->assertTrue($errors->has('sender_phone'));
    }

    #[Test]
    public function non_cash_payment_accepts_phone_as_sender_contact(): void
    {
        $method = $this->paymentMethod('electronic_wallet');

        $errors = $this->afterValidationErrors([
            'service_type' => 'takeaway',
            'payment_arrangement' => 'pay_now',
            'payment_method_id' => $method->id,
            'reference_number' => 'TX-7788',
            'sender_name' => 'أحمد محمد',
            'sender_phone' => '0599000000',
        ]);

        $this->assertFalse($errors->has('reference_number'));
        $this->assertFalse($errors->has('sender_name'));
        $this->assertFalse($errors->has('sender_phone'));
    }

    #[Test]
    public function non_cash_payment_accepts_account_number_without_phone(): void
    {
        $method = $this->paymentMethod('card_pos');

        $errors = $this->afterValidationErrors([
            'service_type' => 'takeaway',
            'payment_arrangement' => 'pay_now',
            'payment_method_id' => $method->id,
            'reference_number' => 'POS-9911',
            'sender_name' => 'صاحب البطاقة',
            'sender_account_number' => 'CARD-4455',
        ]);

        $this->assertFalse($errors->has('reference_number'));
        $this->assertFalse($errors->has('sender_name'));
        $this->assertFalse($errors->has('sender_phone'));
    }

    #[Test]
    public function cash_payment_does_not_require_transfer_details(): void
    {
        $method = $this->paymentMethod('cash');

        $errors = $this->afterValidationErrors([
            'service_type' => 'takeaway',
            'payment_arrangement' => 'pay_now',
            'payment_method_id' => $method->id,
        ]);

        $this->assertFalse($errors->has('reference_number'));
        $this->assertFalse($errors->has('sender_name'));
        $this->assertFalse($errors->has('sender_phone'));
    }

    private function paymentMethod(string $type): PaymentMethod
    {
        return PaymentMethod::query()->create([
            'name' => 'POS Test ' . $type,
            'name_ar' => 'اختبار ' . $type,
            'code' => 'pos-' . $type . '-' . Str::lower(Str::random(6)),
            'type' => $type,
            'requires_verification' => false,
            'requires_reference' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function afterValidationErrors(array $data)
    {
        $request = StoreRestaurantPosOrderRequest::create(
            '/restaurant/pos/orders',
            'POST',
            $data
        );

        $validator = Validator::make([], []);

        $request->withValidator($validator);
        $validator->passes();

        return $validator->errors();
    }
}
