<?php

namespace Tests\Unit;

use App\Enums\KitchenTicketStatus;
use PHPUnit\Framework\TestCase;

class KitchenTicketStatusTest extends TestCase
{
    public function test_kitchen_ticket_lifecycle_values_are_stable(): void
    {
        $this->assertSame(
            ['queued', 'preparing', 'ready', 'served', 'cancelled'],
            array_map(
                fn (KitchenTicketStatus $status) => $status->value,
                KitchenTicketStatus::cases()
            )
        );
    }

    public function test_only_served_and_cancelled_are_terminal(): void
    {
        $this->assertFalse(KitchenTicketStatus::QUEUED->isTerminal());
        $this->assertFalse(KitchenTicketStatus::PREPARING->isTerminal());
        $this->assertFalse(KitchenTicketStatus::READY->isTerminal());
        $this->assertTrue(KitchenTicketStatus::SERVED->isTerminal());
        $this->assertTrue(KitchenTicketStatus::CANCELLED->isTerminal());
    }
}
