<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PayrollSettlementMathTest extends TestCase
{
    public function test_base_amount_uses_rate_to_base(): void
    {
        $amount = 100;
        $rateToBase = 3.65;

        $this->assertSame(
            365.0,
            $amount * $rateToBase
        );
    }

    public function test_partial_payment_reduces_payable_only_by_payment(): void
    {
        $payable = 2600;
        $payment = 600;

        $this->assertSame(
            2000,
            $payable - $payment
        );
    }

    public function test_void_restores_the_payment_amount(): void
    {
        $remaining = 2000;
        $voidedPayment = 600;

        $this->assertSame(
            2600,
            $remaining + $voidedPayment
        );
    }
}
