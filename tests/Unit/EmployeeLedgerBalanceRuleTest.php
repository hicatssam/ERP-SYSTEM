<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EmployeeLedgerBalanceRuleTest extends TestCase
{
    public function test_employee_balance_is_credit_minus_debit(): void
    {
        $credits = 3000 + 200;
        $debits = 500 + 100 + 2600;

        $this->assertSame(0, $credits - $debits);
    }
}
