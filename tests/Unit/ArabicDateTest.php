<?php

namespace Tests\Unit;

use App\Support\ArabicDate;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ArabicDateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'Asia/Jerusalem',
        ]);

        CarbonImmutable::setTestNow(
            CarbonImmutable::create(
                2026,
                9,
                26,
                16,
                46,
                0,
                'Asia/Jerusalem'
            )
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_today_datetime_uses_weekday_and_arabic_daypart(): void
    {
        $this->assertSame(
            'اليوم، السبت · 4:05 مساءً',
            ArabicDate::compactDateTime(
                '2026-09-26 16:05:00'
            )
        );
    }

    public function test_tomorrow_and_yesterday_are_human_readable(): void
    {
        $this->assertSame(
            'غدًا، الأحد · 8:15 صباحًا',
            ArabicDate::compactDateTime(
                '2026-09-27 08:15:00'
            )
        );

        $this->assertSame(
            'أمس، الجمعة · 11:30 مساءً',
            ArabicDate::compactDateTime(
                '2026-09-25 23:30:00'
            )
        );
    }

    public function test_date_only_never_invents_a_time(): void
    {
        $this->assertSame(
            'الاثنين، 28 سبتمبر 2026',
            ArabicDate::date(
                '2026-09-28'
            )
        );
    }

    public function test_separate_delivery_date_and_time_are_combined_consistently(): void
    {
        $this->assertSame(
            'اليوم، السبت · 4:00 مساءً',
            ArabicDate::dateWithOptionalTime(
                '2026-09-26',
                '16:00'
            )
        );

        $this->assertSame(
            'اليوم، السبت، 26 سبتمبر 2026',
            ArabicDate::dateWithOptionalTime(
                '2026-09-26',
                null
            )
        );
    }

    public function test_time_handles_midnight_noon_and_evening(): void
    {
        $this->assertSame(
            '12:00 صباحًا',
            ArabicDate::time(
                '2026-09-26 00:00:00'
            )
        );

        $this->assertSame(
            '12:00 مساءً',
            ArabicDate::time(
                '2026-09-26 12:00:00'
            )
        );

        $this->assertSame(
            '4:45 مساءً',
            ArabicDate::time(
                '2026-09-26 16:45:00'
            )
        );
    }

    public function test_invalid_values_fail_safely(): void
    {
        $this->assertSame(
            '—',
            ArabicDate::date(null)
        );

        $this->assertSame(
            'not-a-date',
            ArabicDate::date('not-a-date')
        );
    }
}
