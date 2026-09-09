<?php

namespace Tests\Unit;

use App\Models\ReportSchedule;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for ReportSchedule::isDue() and ReportSchedule::resolveDateRange().
 *
 * These tests never hit the database — every ReportSchedule is constructed with
 * make() (or plain new) so RefreshDatabase is not needed here.
 */
class ReportScheduleTest extends TestCase
{
    // =========================================================================
    //  Helpers
    // =========================================================================

    /** Build an unsaved ReportSchedule with sensible defaults that can be overridden. */
    private function schedule(array $attrs = []): ReportSchedule
    {
        return ReportSchedule::make(array_merge([
            'name'        => 'Test Schedule',
            'report_type' => 'orders',
            'frequency'   => 'daily',
            'hour'        => 8,
            'day_of_week' => null,
            'recipients'  => 'test@example.com',
            'date_range'  => 'last_7_days',
            'is_active'   => true,
        ], $attrs));
    }

    // =========================================================================
    //  isDue() — inactive schedule
    // =========================================================================

    #[Test]
    public function is_due_returns_false_when_inactive(): void
    {
        $s  = $this->schedule(['is_active' => false, 'frequency' => 'daily', 'hour' => 8]);
        $at = Carbon::create(2026, 8, 1, 8, 0, 0); // Saturday, hour 8 — would otherwise be due

        $this->assertFalse($s->isDue($at));
    }

    // =========================================================================
    //  isDue() — hour mismatch
    // =========================================================================

    #[Test]
    public function is_due_returns_false_when_hour_does_not_match(): void
    {
        $s  = $this->schedule(['frequency' => 'daily', 'hour' => 8]);
        $at = Carbon::create(2026, 8, 1, 9, 0, 0); // hour 9 ≠ 8

        $this->assertFalse($s->isDue($at));
    }

    // =========================================================================
    //  isDue() — daily
    // =========================================================================

    #[Test]
    public function is_due_daily_returns_true_when_hour_matches(): void
    {
        $s  = $this->schedule(['frequency' => 'daily', 'hour' => 7]);
        $at = Carbon::create(2026, 8, 1, 7, 30, 0);

        $this->assertTrue($s->isDue($at));
    }

    #[Test]
    public function is_due_daily_returns_false_when_hour_differs(): void
    {
        $s  = $this->schedule(['frequency' => 'daily', 'hour' => 7]);
        $at = Carbon::create(2026, 8, 1, 8, 0, 0);

        $this->assertFalse($s->isDue($at));
    }

    // =========================================================================
    //  isDue() — weekly
    // =========================================================================

    /** @return array<string, array{int, Carbon, bool}> */
    public static function weeklyProvider(): array
    {
        // Carbon day-of-week: 0=Sun, 1=Mon … 6=Sat
        // 2026-08-03 = Monday (dayOfWeek = 1)
        // 2026-08-04 = Tuesday (dayOfWeek = 2)
        return [
            'matching day and hour'    => [1, Carbon::create(2026, 8, 3, 9, 0, 0), true],
            'wrong day, correct hour'  => [2, Carbon::create(2026, 8, 3, 9, 0, 0), false],
            'correct day, wrong hour'  => [1, Carbon::create(2026, 8, 3, 10, 0, 0), false],
        ];
    }

    #[Test]
    #[DataProvider('weeklyProvider')]
    public function is_due_weekly(int $dayOfWeek, Carbon $at, bool $expected): void
    {
        $s = $this->schedule(['frequency' => 'weekly', 'hour' => 9, 'day_of_week' => $dayOfWeek]);

        $this->assertSame($expected, $s->isDue($at));
    }

    // =========================================================================
    //  isDue() — monthly
    // =========================================================================

    #[Test]
    public function is_due_monthly_returns_true_on_first_day_at_correct_hour(): void
    {
        $s  = $this->schedule(['frequency' => 'monthly', 'hour' => 6]);
        $at = Carbon::create(2026, 8, 1, 6, 0, 0); // 1st of month, hour 6

        $this->assertTrue($s->isDue($at));
    }

    #[Test]
    public function is_due_monthly_returns_false_on_non_first_day(): void
    {
        $s  = $this->schedule(['frequency' => 'monthly', 'hour' => 6]);
        $at = Carbon::create(2026, 8, 2, 6, 0, 0); // 2nd of month

        $this->assertFalse($s->isDue($at));
    }

    #[Test]
    public function is_due_monthly_returns_false_on_first_day_wrong_hour(): void
    {
        $s  = $this->schedule(['frequency' => 'monthly', 'hour' => 6]);
        $at = Carbon::create(2026, 8, 1, 7, 0, 0); // 1st but wrong hour

        $this->assertFalse($s->isDue($at));
    }

    // =========================================================================
    //  isDue() — unknown frequency
    // =========================================================================

    #[Test]
    public function is_due_returns_false_for_unknown_frequency(): void
    {
        $s  = $this->schedule(['frequency' => 'hourly', 'hour' => 8]);
        $at = Carbon::create(2026, 8, 1, 8, 0, 0);

        $this->assertFalse($s->isDue($at));
    }

    // =========================================================================
    //  resolveDateRange()
    // =========================================================================

    /** @return array<string, array{string, string, string}> */
    public static function dateRangeProvider(): array
    {
        // We freeze Carbon::now() inside each test; use today = 2026-08-02.
        return [
            'today'        => ['today',        '2026-08-02', '2026-08-02'],
            'yesterday'    => ['yesterday',     '2026-08-01', '2026-08-01'],
            'last_7_days'  => ['last_7_days',   '2026-07-27', '2026-08-02'],
            'last_30_days' => ['last_30_days',  '2026-07-04', '2026-08-02'],
            'this_month'   => ['this_month',    '2026-08-01', '2026-08-02'],
            'last_month'   => ['last_month',    '2026-07-01', '2026-07-31'],
            'unknown_key'  => ['unknown_key',   '2026-07-27', '2026-08-02'], // falls to default (last_7_days)
        ];
    }

    #[Test]
    #[DataProvider('dateRangeProvider')]
    public function resolve_date_range_returns_correct_from_and_to(
        string $key,
        string $expectedFrom,
        string $expectedTo,
    ): void {
        // Freeze time so the date arithmetic is deterministic.
        Carbon::setTestNow(Carbon::create(2026, 8, 2, 12, 0, 0));

        try {
            $s = $this->schedule(['date_range' => $key]);
            $result = $s->resolveDateRange();

            $this->assertSame($expectedFrom, $result['date_from'],
                "date_from mismatch for date_range='{$key}'");
            $this->assertSame($expectedTo, $result['date_to'],
                "date_to mismatch for date_range='{$key}'");
        } finally {
            Carbon::setTestNow(null);
        }
    }
}
