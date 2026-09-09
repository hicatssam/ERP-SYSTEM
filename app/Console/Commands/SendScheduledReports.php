<?php

namespace App\Console\Commands;

use App\Models\ReportSchedule;
use App\Services\ReportScheduleService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fired every hour by the scheduler. Iterates all active schedules and sends
 * those that are due for the current hour and have not already been sent this
 * cadence period.
 *
 * Deduplication strategy:
 * ────────────────────────
 * Before doing any work for a schedule we attempt an atomic DB UPDATE with a
 * WHERE clause that ensures last_run_at is either NULL or before the start of
 * the current cadence period. Only the process that wins the UPDATE proceeds;
 * all others skip. This makes concurrent invocations (e.g. overlapping cron
 * runs) safe without requiring Redis or a separate jobs table.
 */
class SendScheduledReports extends Command
{
    protected $signature   = 'reports:send-scheduled';
    protected $description = 'Send scheduled report emails that are due now';

    /** Maximum rows included in a scheduled Excel export. */
    private const CAP = 10000;

    public function handle(ReportScheduleService $service): int
    {
        $now       = Carbon::now();
        $schedules = ReportSchedule::where('is_active', true)->get();

        $sent = 0;

        foreach ($schedules as $schedule) {
            if (! $schedule->isDue($now)) {
                continue;
            }

            // Atomic claim: only the first process to win this UPDATE will send.
            $periodStart = $this->periodStart($schedule, $now);
            $claimed = DB::table('report_schedules')
                ->where('id', $schedule->id)
                ->where('is_active', true)
                ->where(function ($q) use ($periodStart) {
                    $q->whereNull('last_run_at')
                      ->orWhere('last_run_at', '<', $periodStart);
                })
                ->update(['last_run_at' => $now]);

            if (! $claimed) {
                $this->line("Skipped (already sent this period): [{$schedule->id}] {$schedule->name}");
                continue;
            }

            try {
                $service->send($schedule);
                $sent++;
                $this->info("Sent: [{$schedule->id}] {$schedule->name}");
            } catch (\Throwable $e) {
                // Roll back last_run_at so the next hourly run retries.
                DB::table('report_schedules')
                    ->where('id', $schedule->id)
                    ->update(['last_run_at' => null]);

                Log::error("Scheduled report [{$schedule->id}] failed: " . $e->getMessage(), [
                    'exception' => $e,
                ]);
                $this->error("Failed [{$schedule->id}] {$schedule->name}: " . $e->getMessage());
            }
        }

        $this->info("Done. {$sent} schedule(s) sent.");

        return self::SUCCESS;
    }

    /**
     * Returns the start of the current cadence period — used to decide whether
     * the schedule has already run this period.
     *
     * daily:   midnight of today
     * weekly:  midnight of today (we only run once per matching weekday)
     * monthly: midnight of the 1st of this month
     */
    private function periodStart(ReportSchedule $schedule, Carbon $now): Carbon
    {
        return match ($schedule->frequency) {
            'monthly' => $now->copy()->startOfMonth(),
            default   => $now->copy()->startOfDay(), // daily and weekly
        };
    }
}
