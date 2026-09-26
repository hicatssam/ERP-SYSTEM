<?php

namespace App\Support;

use App\Models\SystemSetting;
use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class ArabicDate
{
    private const DAYS = [
        0 => 'الأحد',
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];

    private const MONTHS = [
        1 => 'يناير',
        2 => 'فبراير',
        3 => 'مارس',
        4 => 'أبريل',
        5 => 'مايو',
        6 => 'يونيو',
        7 => 'يوليو',
        8 => 'أغسطس',
        9 => 'سبتمبر',
        10 => 'أكتوبر',
        11 => 'نوفمبر',
        12 => 'ديسمبر',
    ];

    public static function date(
        mixed $value,
        bool $relativeDay = true
    ): string {
        $date = self::parse($value);

        if (! $date) {
            return self::fallback($value);
        }

        $dayLabel = self::dayLabel(
            $date,
            $relativeDay
        );

        return sprintf(
            '%s، %d %s %d',
            $dayLabel,
            $date->day,
            self::MONTHS[$date->month],
            $date->year
        );
    }

    public static function time(
        mixed $value
    ): string {
        $date = self::parse($value);

        if (! $date) {
            return self::fallback($value);
        }

        $hour = $date->hour;
        $hour12 = $hour % 12;

        if ($hour12 === 0) {
            $hour12 = 12;
        }

        return sprintf(
            '%d:%02d %s',
            $hour12,
            $date->minute,
            $hour < 12 ? 'صباحًا' : 'مساءً'
        );
    }

    public static function dateTime(
        mixed $value,
        bool $relativeDay = true,
        bool $includeYear = true
    ): string {
        $date = self::parse($value);

        if (! $date) {
            return self::fallback($value);
        }

        $dayLabel = self::dayLabel(
            $date,
            $relativeDay
        );

        $datePart = $includeYear
            ? sprintf(
                '%d %s %d',
                $date->day,
                self::MONTHS[$date->month],
                $date->year
            )
            : sprintf(
                '%d %s',
                $date->day,
                self::MONTHS[$date->month]
            );

        return sprintf(
            '%s، %s · %s',
            $dayLabel,
            $datePart,
            self::time($date)
        );
    }

    public static function compactDateTime(
        mixed $value
    ): string {
        $date = self::parse($value);

        if (! $date) {
            return self::fallback($value);
        }

        $today = self::now()
            ->startOfDay();

        $target = $date->startOfDay();

        $dayDiff = (int) round(
            $today->diffInDays(
                $target,
                false
            )
        );

        if ($dayDiff === 0) {
            return sprintf(
                'اليوم، %s · %s',
                self::DAYS[$date->dayOfWeek],
                self::time($date)
            );
        }

        if ($dayDiff === 1) {
            return sprintf(
                'غدًا، %s · %s',
                self::DAYS[$date->dayOfWeek],
                self::time($date)
            );
        }

        if ($dayDiff === -1) {
            return sprintf(
                'أمس، %s · %s',
                self::DAYS[$date->dayOfWeek],
                self::time($date)
            );
        }

        return sprintf(
            '%s، %d %s %d · %s',
            self::DAYS[$date->dayOfWeek],
            $date->day,
            self::MONTHS[$date->month],
            $date->year,
            self::time($date)
        );
    }

    public static function timezone(): string
    {
        try {
            $timezone = (string) SystemSetting::get(
                'timezone',
                config(
                    'app.timezone',
                    'Asia/Jerusalem'
                )
            );

            if ($timezone !== '') {
                return $timezone;
            }
        } catch (\Throwable) {
            // The settings table may not exist during early migrations/tests.
        }

        return (string) config(
            'app.timezone',
            'Asia/Jerusalem'
        );
    }

    private static function parse(
        mixed $value
    ): ?CarbonImmutable {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof CarbonInterface) {
                return CarbonImmutable::instance(
                    $value
                )->setTimezone(
                    self::timezone()
                );
            }

            if ($value instanceof DateTimeInterface) {
                return CarbonImmutable::instance(
                    $value
                )->setTimezone(
                    self::timezone()
                );
            }

            return CarbonImmutable::parse(
                (string) $value,
                self::timezone()
            )->setTimezone(
                self::timezone()
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(
            self::timezone()
        );
    }

    private static function dayLabel(
        CarbonImmutable $date,
        bool $relativeDay
    ): string {
        if ($relativeDay) {
            $today = self::now()
                ->startOfDay();

            $target = $date->startOfDay();

            $dayDiff = $today->diffInDays(
                $target,
                false
            );

            if ($dayDiff === 0) {
                return 'اليوم، '
                    . self::DAYS[
                        $date->dayOfWeek
                    ];
            }

            if ($dayDiff === 1) {
                return 'غدًا، '
                    . self::DAYS[
                        $date->dayOfWeek
                    ];
            }

            if ($dayDiff === -1) {
                return 'أمس، '
                    . self::DAYS[
                        $date->dayOfWeek
                    ];
            }
        }

        return self::DAYS[
            $date->dayOfWeek
        ];
    }

    private static function fallback(
        mixed $value
    ): string {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '—';
    }
}
