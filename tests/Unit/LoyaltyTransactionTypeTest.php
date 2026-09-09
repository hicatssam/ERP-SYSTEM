<?php

namespace Tests\Unit;

use App\Enums\LoyaltyTransactionType;
use PHPUnit\Framework\TestCase;

class LoyaltyTransactionTypeTest extends TestCase
{
    public function test_loyalty_transaction_types_and_arabic_labels_are_stable(): void
    {
        $this->assertSame(
            ['earn', 'redeem', 'adjustment', 'reversal'],
            array_map(fn (LoyaltyTransactionType $type): string => $type->value, LoyaltyTransactionType::cases())
        );
        $this->assertSame('اكتساب نقاط', LoyaltyTransactionType::Earn->label());
        $this->assertSame('استبدال نقاط', LoyaltyTransactionType::Redeem->label());
    }
}
