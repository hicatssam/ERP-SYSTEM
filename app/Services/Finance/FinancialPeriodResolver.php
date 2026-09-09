<?php

namespace App\Services\Finance;

use App\Enums\FinancialPeriodStatus;
use App\Models\FinancialPeriod;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class FinancialPeriodResolver
{
    public function openForDate(CarbonInterface|string $date, bool $lock = false): FinancialPeriod
    {
        $dateValue = $date instanceof CarbonInterface
            ? $date->toDateString()
            : (string) $date;

        $query = FinancialPeriod::query()
            ->whereDate('start_date', '<=', $dateValue)
            ->whereDate('end_date', '>=', $dateValue)
            ->where('status', FinancialPeriodStatus::Open->value);

        if ($lock) {
            $query->lockForUpdate();
        }

        $period = $query->first();

        if (! $period) {
            throw ValidationException::withMessages([
                'financial_period' => 'لا توجد فترة مالية مفتوحة تغطي تاريخ العملية. افتح الفترة المالية المناسبة أولًا.',
            ]);
        }

        return $period;
    }
}
