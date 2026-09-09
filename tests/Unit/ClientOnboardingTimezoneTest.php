<?php

namespace Tests\Unit;

use App\Services\BusinessProfileService;
use App\Services\ClientOnboardingService;
use App\Services\ModuleService;
use PHPUnit\Framework\TestCase;

class ClientOnboardingTimezoneTest extends TestCase
{
    public function test_legacy_gaza_timezone_is_normalized(): void
    {
        $service = new ClientOnboardingService(
            $this->createMock(BusinessProfileService::class),
            $this->createMock(ModuleService::class),
        );

        $this->assertSame(
            'Asia/Gaza',
            $service->normalizedTimezone('Gaza/Palestine')
        );
    }

    public function test_valid_timezone_is_kept(): void
    {
        $service = new ClientOnboardingService(
            $this->createMock(BusinessProfileService::class),
            $this->createMock(ModuleService::class),
        );

        $this->assertSame(
            'Europe/London',
            $service->normalizedTimezone('Europe/London')
        );
    }
}
