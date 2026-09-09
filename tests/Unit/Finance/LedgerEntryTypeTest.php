<?php

namespace Tests\Unit\Finance;

use App\Enums\LedgerEntryType;
use PHPUnit\Framework\TestCase;

class LedgerEntryTypeTest extends TestCase
{
    public function test_expense_ledger_types_exist(): void
    {
        $this->assertSame('expense', LedgerEntryType::Expense->value);
        $this->assertSame('expense_reversal', LedgerEntryType::ExpenseReversal->value);
    }
}
