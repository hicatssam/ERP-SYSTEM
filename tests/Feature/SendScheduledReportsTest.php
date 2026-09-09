<?php

namespace Tests\Feature;

use App\Exports\ChunkedQueryReportExport;
use App\Mail\ScheduledReportMail;
use App\Models\ActivityLog;
use App\Models\Location;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Services\ReportScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Tests\TestCase;

/**
 * Feature tests for the SendScheduledReports Artisan command.
 *
 * These tests exercise the full command pipeline:
 *   ReportSchedule (isDue + claim) → ReportScheduleService::send() → Mail
 *
 * Mail::fake() is used so no real SMTP connection is made and we can assert
 * that the correct mailable was dispatched with an xlsx attachment.
 */
class SendScheduledReportsTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    //  Helpers
    // =========================================================================

    private function location(): Location
    {
        return Location::create([
            'name'      => 'Test Branch',
            'code'      => 'TB-' . uniqid(),
            'type'      => 'branch',
            'is_active' => true,
        ]);
    }

    private function user(): User
    {
        return User::factory()->create(['username' => 'sched_tester_' . uniqid()]);
    }

    /**
     * Insert a ReportSchedule that will be isDue() for the given Carbon instant.
     */
    private function dueSchedule(array $overrides = []): ReportSchedule
    {
        // Default: daily at hour 8 (matches Carbon::now() pinned to 08:xx in tests).
        return ReportSchedule::create(array_merge([
            'name'        => 'Test Daily Schedule',
            'report_type' => 'activity-logs',
            'frequency'   => 'daily',
            'hour'        => 8,
            'day_of_week' => null,
            'recipients'  => 'manager@example.com',
            'date_range'  => 'last_7_days',
            'is_active'   => true,
            'location_id' => null,
            'created_by'  => null,
        ], $overrides));
    }

    // =========================================================================
    //  Command sends mail with xlsx attachment for a due schedule
    // =========================================================================

    #[Test]
    public function command_sends_mailable_with_xlsx_attachment_for_due_schedule(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 8, 0, 0)); // Monday 08:00

        try {
            $schedule = $this->dueSchedule(['hour' => 8, 'frequency' => 'daily']);

            $this->artisan('reports:send-scheduled')->assertExitCode(0);

            Mail::assertSent(ScheduledReportMail::class, function (ScheduledReportMail $mail) use ($schedule) {
                // Verify it belongs to the correct schedule (schedule is a public readonly property).
                return $mail->schedule->id === $schedule->id;
            });
        } finally {
            Carbon::setTestNow(null);
        }
    }

    #[Test]
    public function command_sends_to_every_exact_recipient_in_comma_separated_list(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 9, 0, 0));

        try {
            $this->dueSchedule([
                'hour'       => 9,
                'frequency'  => 'daily',
                'recipients' => 'alice@example.com, bob@example.com, carol@example.com',
            ]);

            $this->artisan('reports:send-scheduled')->assertExitCode(0);

            // Three separate send() calls are made — one per recipient.
            Mail::assertSent(ScheduledReportMail::class, 3);

            // Each address must appear in at least one of the captured mailables.
            foreach (['alice@example.com', 'bob@example.com', 'carol@example.com'] as $addr) {
                Mail::assertSent(ScheduledReportMail::class, fn (ScheduledReportMail $m) => $m->hasTo($addr));
            }
        } finally {
            Carbon::setTestNow(null);
        }
    }

    // =========================================================================
    //  Command does NOT send mail for schedules that are not due
    // =========================================================================

    #[Test]
    public function command_skips_schedule_when_hour_does_not_match(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 10, 0, 0)); // hour 10

        try {
            $this->dueSchedule(['hour' => 8]); // due at 08:xx, not 10:xx

            $this->artisan('reports:send-scheduled')->assertExitCode(0);

            Mail::assertNothingSent();
        } finally {
            Carbon::setTestNow(null);
        }
    }

    #[Test]
    public function command_skips_inactive_schedule(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 8, 0, 0));

        try {
            $this->dueSchedule(['hour' => 8, 'is_active' => false]);

            $this->artisan('reports:send-scheduled')->assertExitCode(0);

            Mail::assertNothingSent();
        } finally {
            Carbon::setTestNow(null);
        }
    }

    #[Test]
    public function command_skips_weekly_schedule_on_wrong_day(): void
    {
        Mail::fake();
        // 2026-08-03 is Monday (day_of_week = 1); schedule is set for Tuesday (2).
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 8, 0, 0));

        try {
            $this->dueSchedule(['frequency' => 'weekly', 'day_of_week' => 2, 'hour' => 8]);

            $this->artisan('reports:send-scheduled')->assertExitCode(0);

            Mail::assertNothingSent();
        } finally {
            Carbon::setTestNow(null);
        }
    }

    // =========================================================================
    //  Deduplication: second run in same period is skipped
    // =========================================================================

    #[Test]
    public function command_does_not_send_twice_in_the_same_period(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 8, 0, 0));

        try {
            $this->dueSchedule(['hour' => 8, 'frequency' => 'daily']);

            // First run — should send.
            $this->artisan('reports:send-scheduled')->assertExitCode(0);
            Mail::assertSent(ScheduledReportMail::class, 1);

            Mail::fake(); // reset the sent-mail log

            // Second run in the same period — should skip (last_run_at was set).
            $this->artisan('reports:send-scheduled')->assertExitCode(0);
            Mail::assertNothingSent();
        } finally {
            Carbon::setTestNow(null);
        }
    }

    // =========================================================================
    //  XLSX attachment opens without errors (PhpSpreadsheet round-trip)
    // =========================================================================

    /**
     * Runs the REAL export pipeline end-to-end and validates the XLSX bytes
     * with PhpSpreadsheet before the service's finally-block deletes the file.
     *
     *   ReportSchedule → ReportScheduleService::send()
     *     → buildExportQuery / formatRowForExport (ReportController via reflection)
     *     → ChunkedQueryReportExport → Excel::raw() → temp file
     *     → ScheduledReportMail (array transport, real Symfony MIME rendering)
     *
     * The array transport stores attachments as lazy File references so we
     * cannot call getBody() after the finally-block deletes the temp file.
     * Instead we subscribe to the MessageSending event, which fires
     * synchronously inside Mail::send() — before the finally-block runs —
     * and eagerly copy the attachment bytes into a local variable.
     */
    #[Test]
    public function service_generates_openxml_spreadsheet_that_phpspreadsheet_can_open(): void
    {
        config(['mail.default' => 'array']);
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 8, 0, 0));

        // Capture attachment bytes eagerly during the send() call while the
        // temp file still exists (before the service's finally-block deletes it).
        $capturedXlsxBytes = null;
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$capturedXlsxBytes) {
            $message = $event->message;
            if (!$message instanceof SymfonyEmail || $capturedXlsxBytes !== null) {
                return;
            }
            $parts = $message->getAttachments();
            if (!empty($parts)) {
                $capturedXlsxBytes = $parts[0]->getBody(); // reads file while it still exists
            }
        });

        try {
            $user = User::factory()->create(['username' => 'xlsx_int_' . uniqid()]);

            ActivityLog::create([
                'user_id'     => $user->id,
                'action'      => 'scheduled-report-integration',
                'module'      => 'reports',
                'record_type' => 'Report',
                'record_id'   => 1,
                'created_at'  => now(),
            ]);

            $schedule = $this->dueSchedule([
                'report_type' => 'activity-logs',
                'hour'        => 8,
                'recipients'  => 'manager@example.com',
            ]);

            // Drive the service directly so the real export pipeline runs.
            app(ReportScheduleService::class)->send($schedule);
        } finally {
            Carbon::setTestNow(null);
        }

        // Verify the event captured bytes before the temp file was deleted.
        $this->assertNotNull($capturedXlsxBytes,
            'MessageSending event must have fired and captured attachment bytes.');
        $this->assertNotEmpty($capturedXlsxBytes,
            'Captured attachment bytes must not be empty.');

        // Parse with PhpSpreadsheet to confirm the file is a valid OpenXML document.
        $tmpPath = tempnam(sys_get_temp_dir(), 'test_sched_xlsx_') . '.xlsx';
        file_put_contents($tmpPath, $capturedXlsxBytes);

        try {
            $spreadsheet = IOFactory::load($tmpPath);
            $rows        = array_values(
                $spreadsheet->getActiveSheet()->toArray(null, false, false, false)
            );

            $this->assertGreaterThanOrEqual(2, count($rows),
                'Spreadsheet must have a header row plus at least one data row.');

            // Header row must contain the expected Arabic column names.
            $header = $rows[0];
            $this->assertContains('المستخدم', $header,
                'Header row must contain the "المستخدم" (user) column.');
            $this->assertContains('الإجراء', $header,
                'Header row must contain the "الإجراء" (action) column.');

            // The seeded action must appear in the data rows.
            $actionColIndex = array_search('الإجراء', $header);
            $actions        = array_column(array_slice($rows, 1), $actionColIndex);
            $this->assertContains('scheduled-report-integration', $actions,
                'Seeded action must appear in a data row of the generated spreadsheet.');
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Mail::fake() path — confirms the mailable's filename ends in .xlsx
     * and the schedule reference is correct.
     */
    #[Test]
    public function sent_mail_has_xlsx_filename_and_correct_schedule_reference(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 8, 0, 0));

        try {
            $schedule = $this->dueSchedule(['hour' => 8, 'frequency' => 'daily', 'report_type' => 'activity-logs']);

            $this->artisan('reports:send-scheduled')->assertExitCode(0);

            Mail::assertSent(ScheduledReportMail::class, function (ScheduledReportMail $mail) use ($schedule) {
                return str_ends_with($mail->fileName, '.xlsx')
                    && $mail->schedule->id === $schedule->id;
            });
        } finally {
            Carbon::setTestNow(null);
        }
    }

    // =========================================================================
    //  last_run_at is reset when service throws, allowing a retry next hour
    // =========================================================================

    #[Test]
    public function last_run_at_is_reset_to_null_when_service_throws(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 8, 0, 0));

        try {
            $schedule = $this->dueSchedule(['hour' => 8, 'frequency' => 'daily']);

            // Bind a fake service that always throws.
            $this->app->bind(ReportScheduleService::class, function () {
                $mock = $this->createMock(ReportScheduleService::class);
                $mock->method('send')->willThrowException(new \RuntimeException('export failed'));
                return $mock;
            });

            $this->artisan('reports:send-scheduled')->assertExitCode(0);

            $schedule->refresh();
            $this->assertNull($schedule->last_run_at,
                'last_run_at must be rolled back to null after a service failure so the next run retries.');
        } finally {
            Carbon::setTestNow(null);
        }
    }

    // =========================================================================
    //  Weekly schedule fires on the correct day
    // =========================================================================

    #[Test]
    public function command_sends_weekly_schedule_on_matching_day(): void
    {
        Mail::fake();
        // 2026-08-03 is Monday (day_of_week = 1).
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 10, 0, 0));

        try {
            $this->dueSchedule(['frequency' => 'weekly', 'day_of_week' => 1, 'hour' => 10]);

            $this->artisan('reports:send-scheduled')->assertExitCode(0);

            Mail::assertSent(ScheduledReportMail::class, 1);
        } finally {
            Carbon::setTestNow(null);
        }
    }

    // =========================================================================
    //  Monthly schedule fires on the 1st
    // =========================================================================

    #[Test]
    public function command_sends_monthly_schedule_on_first_of_month(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::create(2026, 8, 1, 7, 0, 0)); // 1st of August, hour 7

        try {
            $this->dueSchedule(['frequency' => 'monthly', 'hour' => 7]);

            $this->artisan('reports:send-scheduled')->assertExitCode(0);

            Mail::assertSent(ScheduledReportMail::class, 1);
        } finally {
            Carbon::setTestNow(null);
        }
    }
}
