<?php

namespace Tests\Unit;

use App\Enums\RestaurantServiceType;
use PHPUnit\Framework\TestCase;

class RestaurantServiceTypeTest extends TestCase
{
    public function test_restaurant_service_types_are_stable(): void
    {
        $this->assertSame('dine_in', RestaurantServiceType::DineIn->value);
        $this->assertSame('takeaway', RestaurantServiceType::Takeaway->value);
        $this->assertSame('delivery', RestaurantServiceType::Delivery->value);
        $this->assertSame('phone', RestaurantServiceType::Phone->value);
        $this->assertSame('web', RestaurantServiceType::Web->value);
    }

    public function test_labels_are_arabic(): void
    {
        $this->assertSame('داخل المطعم', RestaurantServiceType::DineIn->label());
        $this->assertSame('سفري', RestaurantServiceType::Takeaway->label());
    }
}
