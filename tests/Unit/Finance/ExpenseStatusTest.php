<?php

namespace Tests\Unit\Finance;

use App\Enums\ExpenseStatus;
use PHPUnit\Framework\TestCase;

class ExpenseStatusTest extends TestCase
{
    public function test_expense_workflow_status_values_are_stable(): void
    {
        $this->assertSame(
            ['draft', 'submitted', 'approved', 'rejected', 'posted', 'void'],
            array_map(fn (ExpenseStatus $status) => $status->value, ExpenseStatus::cases())
        );
    }
}
