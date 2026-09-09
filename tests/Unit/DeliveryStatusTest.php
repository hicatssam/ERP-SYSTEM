<?php

namespace Tests\Unit;

use App\Enums\DeliveryStatus;
use PHPUnit\Framework\TestCase;

class DeliveryStatusTest extends TestCase
{
    public function test_delivery_state_machine_allows_only_expected_transitions(): void
    {
        $this->assertTrue(DeliveryStatus::Pending->canTransitionTo(DeliveryStatus::Assigned));
        $this->assertFalse(DeliveryStatus::Pending->canTransitionTo(DeliveryStatus::Delivered));
        $this->assertTrue(DeliveryStatus::Assigned->canTransitionTo(DeliveryStatus::PickedUp));
        $this->assertTrue(DeliveryStatus::PickedUp->canTransitionTo(DeliveryStatus::OutForDelivery));
        $this->assertTrue(DeliveryStatus::OutForDelivery->canTransitionTo(DeliveryStatus::Delivered));
        $this->assertFalse(DeliveryStatus::Delivered->canTransitionTo(DeliveryStatus::Pending));
    }
}
