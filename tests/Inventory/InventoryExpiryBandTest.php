<?php

namespace Tests\Inventory;

use App\Services\Inventory\InventoryExpiryService;
use App\Services\Inventory\MetaWhatsAppGateway;
use Tests\TestCase;

class InventoryExpiryBandTest extends TestCase
{
    public function test_default_threshold_bands_are_non_overlapping(): void
    {
        $service = new InventoryExpiryService(
            new MetaWhatsAppGateway()
        );

        $this->assertNull($service->thresholdForDays(61));
        $this->assertSame(60, $service->thresholdForDays(60));
        $this->assertSame(60, $service->thresholdForDays(31));
        $this->assertSame(30, $service->thresholdForDays(30));
        $this->assertSame(30, $service->thresholdForDays(8));
        $this->assertSame(7, $service->thresholdForDays(7));
        $this->assertSame(7, $service->thresholdForDays(1));
        $this->assertSame(0, $service->thresholdForDays(0));
        $this->assertSame(0, $service->thresholdForDays(-1));
    }
}
