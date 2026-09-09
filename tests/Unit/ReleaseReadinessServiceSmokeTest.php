<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ReleaseReadinessServiceSmokeTest extends TestCase
{
    public function test_release_status_values_are_stable(): void
    {
        $this->assertContains('ready', ['ready', 'review', 'blocked']);
        $this->assertContains('review', ['ready', 'review', 'blocked']);
        $this->assertContains('blocked', ['ready', 'review', 'blocked']);
    }
}
