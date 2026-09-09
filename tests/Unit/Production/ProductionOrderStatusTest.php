<?php

namespace Tests\Unit\Production;

use App\Enums\ProductionOrderStatus;
use PHPUnit\Framework\TestCase;

class ProductionOrderStatusTest extends TestCase
{
    public function test_expected_statuses_are_registered(): void
    {
        $this->assertSame(
            ['draft', 'released', 'in_progress', 'awaiting_quality', 'completed', 'rejected', 'cancelled'],
            array_map(fn ($status) => $status->value, ProductionOrderStatus::cases())
        );
    }
}
