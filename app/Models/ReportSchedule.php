<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class ReportSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'report_type',
        'frequency',
        'day_of_week',
        'hour',
        'recipients',
        'location_id',
        'date_range',
        'is_active',
        'last_run_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'last_run_at' => 'datetime',
            'hour'        => 'integer',
            'day_of_week' => 'integer',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function location(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * New schedules store comma-separated addresses. Older seeded schedules
     * stored a JSON array, so accept both without ever treating JSON syntax
     * as part of an email address.
     *
     * @return list<string>
     */
    public function recipientList(): array
    {
        $raw = trim((string) $this->recipients);
        $decoded = json_decode($raw, true);
        $entries = is_array($decoded) && array_is_list($decoded)
            ? $decoded
            : explode(',', $raw);

        $emails = [];
        foreach ($entries as $entry) {
            if (! is_string($entry)) {
                throw new \InvalidArgumentException('قائمة مستلمي التقرير تحتوي على بريد إلكتروني غير صالح.');
            }

            $email = trim($entry);
            if ($email === '') {
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('قائمة مستلمي التقرير تحتوي على بريد إلكتروني غير صالح.');
            }

            $emails[] = $email;
        }

        if ($emails === []) {
            throw new \InvalidArgumentException('لا يوجد بريد إلكتروني صالح لاستلام التقرير.');
        }

        return $emails;
    }

    /** Human-readable frequency label (Arabic). */
    public function frequencyLabel(): string
    {
        return match ($this->frequency) {
            'daily'   => 'يومياً',
            'weekly'  => 'أسبوعياً',
            'monthly' => 'شهرياً',
            default   => $this->frequency,
        };
    }

    /** Human-readable day-of-week label (Arabic). */
    public function dayOfWeekLabel(): string
    {
        $days = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
        return $days[$this->day_of_week ?? 0] ?? '—';
    }

    /** Human-readable date range label (Arabic). */
    public function dateRangeLabel(): string
    {
        return match ($this->date_range) {
            'today'      => 'اليوم',
            'yesterday'  => 'أمس',
            'last_7_days'  => 'آخر 7 أيام',
            'last_30_days' => 'آخر 30 يوم',
            'last_week'    => 'الأسبوع الماضي',
            'this_month'   => 'هذا الشهر',
            'last_month'   => 'الشهر الماضي',
            default        => $this->date_range,
        };
    }

    /**
     * Resolve the date_from / date_to pair for the current run.
     *
     * @return array{date_from: string, date_to: string}
     */
    public function resolveDateRange(): array
    {
        $now = Carbon::now();

        [$from, $to] = match ($this->date_range) {
            'today'        => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday'    => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last_7_days'  => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30_days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'last_week'    => [
                $now->copy()->startOfWeek(Carbon::MONDAY)->subWeek()->startOfDay(),
                $now->copy()->startOfWeek(Carbon::MONDAY)->subDay()->endOfDay(),
            ],
            'this_month'   => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'last_month'   => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            default        => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
        };

        return [
            'date_from' => $from->toDateString(),
            'date_to'   => $to->toDateString(),
        ];
    }

    /**
     * Determine whether this schedule should fire at the given time.
     * Only checks hour and frequency — deduplication (last_run_at) is
     * handled atomically by the command so concurrent processes are safe.
     */
    public function isDue(?Carbon $at = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $at = $at ?? Carbon::now();

        if ($at->hour !== $this->hour) {
            return false;
        }

        return match ($this->frequency) {
            'daily'   => true,
            'weekly'  => $at->dayOfWeek === ($this->day_of_week ?? 0),
            'monthly' => $at->day === 1,
            default   => false,
        };
    }
}
