<?php

namespace App\Services\Finance;

use App\Models\FinancialPeriod;
use App\Models\FinancialPeriodSummary;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialPeriodService
{
    /**
     * فتح فترة مالية جديدة.
     */
    public function openPeriod(
        array $data,
        User $user
    ): FinancialPeriod {
        return DB::transaction(
            function () use ($data, $user): FinancialPeriod {
                [
                    $startDate,
                    $endDate,
                    $year,
                    $month,
                ] = $this->resolvePeriodDates($data);

                $duplicateExists = FinancialPeriod::query()
                    ->where('year', $year)
                    ->where('month', $month)
                    ->exists();

                if ($duplicateExists) {
                    throw ValidationException::withMessages([
                        'month' =>
                            'توجد فترة مالية مسجلة مسبقاً لهذا الشهر.',
                    ]);
                }

                $overlapExists = FinancialPeriod::query()
                    ->whereDate(
                        'start_date',
                        '<=',
                        $endDate->toDateString()
                    )
                    ->whereDate(
                        'end_date',
                        '>=',
                        $startDate->toDateString()
                    )
                    ->exists();

                if ($overlapExists) {
                    throw ValidationException::withMessages([
                        'month' =>
                            'تتداخل هذه الفترة مع فترة مالية موجودة مسبقاً.',
                    ]);
                }

                $period = FinancialPeriod::create([
                    'name' =>
                        $startDate
                            ->copy()
                            ->locale('ar')
                            ->translatedFormat('F Y'),
                    'year' => $year,
                    'month' => $month,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'status' => 'open',
                    'opened_at' => now(),
                    'opened_by' => $user->id,
                    'closed_at' => null,
                    'closed_by' => null,
                    'opening_balance' => round(
                        (float) ($data['opening_balance'] ?? 0),
                        2
                    ),
                    'closing_balance' => null,
                    'notes' =>
                        isset($data['notes'])
                        && trim((string) $data['notes']) !== ''
                            ? trim((string) $data['notes'])
                            : null,
                ]);

                ActivityLogger::log(
                    userId: $user->id,
                    action: 'financial_period.opened',
                    module: 'finance',
                    recordType: 'financial_periods',
                    recordId: $period->id,
                    oldValues: null,
                    newValues: [
                        'status' => 'open',
                        'year' => $period->year,
                        'month' => $period->month,
                        'start_date' =>
                            $period->start_date?->toDateString(),
                        'end_date' =>
                            $period->end_date?->toDateString(),
                        'opening_balance' =>
                            (float) $period->opening_balance,
                    ],
                    metadata: [
                        'period_label' =>
                            $this->periodLabel($period),
                    ],
                );

                return $period->fresh([
                    'openedBy',
                    'closedBy',
                ]);
            }
        );
    }

    /**
     * إعادة فتح فترة مغلقة بشكل صحيح.
     *
     * مهم:
     * لا نكتفي بتغيير status فقط، لأن ذلك يترك:
     * closed_at / closed_by / closing_balance / summaries
     * من الإغلاق السابق ويصبح السجل متناقضاً.
     */
    public function reopenPeriod(
        FinancialPeriod $period,
        User $user
    ): FinancialPeriod {
        return DB::transaction(
            function () use ($period, $user): FinancialPeriod {
                $locked = FinancialPeriod::query()
                    ->whereKey($period->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($this->isOpen($locked)) {
                    throw ValidationException::withMessages([
                        'period' =>
                            'الفترة المالية مفتوحة بالفعل.',
                    ]);
                }

                $oldValues = [
                    'status' => $this->statusValue($locked),
                    'closing_balance' =>
                        $locked->closing_balance,
                    'closed_at' => $locked->closed_at,
                    'closed_by' => $locked->closed_by,
                ];

                /*
                 * Snapshot الإغلاق السابق لم يعد صالحاً بعد إعادة الفتح.
                 */
                FinancialPeriodSummary::query()
                    ->where(
                        'financial_period_id',
                        $locked->id
                    )
                    ->delete();

                $locked->update([
                    'status' => 'open',
                    'opened_at' => now(),
                    'opened_by' => $user->id,
                    'closed_at' => null,
                    'closed_by' => null,
                    'closing_balance' => null,
                ]);

                ActivityLogger::log(
                    userId: $user->id,
                    action: 'financial_period.reopened',
                    module: 'finance',
                    recordType: 'financial_periods',
                    recordId: $locked->id,
                    oldValues: $oldValues,
                    newValues: [
                        'status' => 'open',
                        'closing_balance' => null,
                        'closed_at' => null,
                        'closed_by' => null,
                    ],
                    metadata: [
                        'period_label' =>
                            $this->periodLabel($locked),
                        'old_summary_deleted' => true,
                    ],
                );

                return $locked->fresh([
                    'openedBy',
                    'closedBy',
                    'summaries.location',
                    'adjustments',
                ]);
            }
        );
    }

    /**
     * Preview حي لصفحة العرض.
     *
     * لا يكتب أي صف في financial_period_summaries.
     * لذلك يمكن عرض بيانات الفترة المفتوحة أو الفترات القديمة
     * التي أنشأها Seeder بدون snapshots.
     */
    public function previewSummaries(
        FinancialPeriod $period
    ): Collection {
        return $this->calculateSummaryRows($period);
    }

    /**
     * إغلاق الفترة وتوليد Snapshot ثابت لكل موقع.
     */
    public function closePeriod(
        FinancialPeriod $period,
        User $user,
        ?string $notes = null
    ): FinancialPeriod {
        return DB::transaction(
            function () use (
                $period,
                $user,
                $notes
            ): FinancialPeriod {
                $locked = FinancialPeriod::query()
                    ->whereKey($period->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $this->isOpen($locked)) {
                    throw ValidationException::withMessages([
                        'period' =>
                            'الفترة المالية مغلقة بالفعل.',
                    ]);
                }

                $hasPendingAdjustments =
                    $locked
                        ->adjustments()
                        ->where('status', 'pending')
                        ->exists();

                if (
                    $hasPendingAdjustments
                    && ! $user->can(
                        'financial.periods.override_close'
                    )
                ) {
                    throw ValidationException::withMessages([
                        'period' =>
                            'لا يمكن إغلاق الفترة لوجود تسويات مالية معلّقة بانتظار الاعتماد.',
                    ]);
                }

                /*
                 * تفعيل الإعداد الموجود فعلياً في النظام:
                 * block_financial_period_close_with_open_cash_sessions
                 */
                $this->assertNoBlockingOpenCashSessions(
                    $locked
                );

                $rows = $this->calculateSummaryRows($locked);

                FinancialPeriodSummary::query()
                    ->where(
                        'financial_period_id',
                        $locked->id
                    )
                    ->delete();

                $totalOutstanding = round((float) $rows->sum('outstanding_amount'), 2);
                $netCashMovement = round(
                    (float) $rows->sum('confirmed_collections')
                    - (float) $rows->sum('refunds')
                    - $this->periodExpenseNet($locked),
                    2
                );

                $closingBalance = round(
                    (float) $locked->opening_balance
                    + $netCashMovement,
                    2
                );

                foreach ($rows as $row) {
                    FinancialPeriodSummary::create([
                        'financial_period_id' =>
                            $locked->id,
                        'location_id' =>
                            $row->location_id,
                        'gross_sales' =>
                            $row->gross_sales,
                        'discounts' =>
                            $row->discounts,
                        'net_sales' =>
                            $row->net_sales,
                        'confirmed_collections' =>
                            $row->confirmed_collections,
                        'refunds' =>
                            $row->refunds,
                        'outstanding_amount' =>
                            $row->outstanding_amount,
                        'invoice_count' =>
                            $row->invoice_count,
                        'order_count' =>
                            $row->order_count,
                        'average_order_value' =>
                            $row->average_order_value,
                        'opening_balance' =>
                            round(
                                (float) $locked->opening_balance,
                                2
                            ),
                        'closing_balance' =>
                            $closingBalance,
                        'generated_at' => now(),
                        'generated_by' => $user->id,
                    ]);
                }

                $oldValues = [
                    'status' =>
                        $this->statusValue($locked),
                    'closing_balance' =>
                        $locked->closing_balance,
                    'closed_at' =>
                        $locked->closed_at,
                    'closed_by' =>
                        $locked->closed_by,
                ];

                $update = [
                    'status' => 'closed',
                    'closing_balance' =>
                        $closingBalance,
                    'closed_at' => now(),
                    'closed_by' => $user->id,
                ];

                if ($notes !== null) {
                    $update['notes'] =
                        trim($notes) !== ''
                            ? trim($notes)
                            : null;
                }

                $locked->update($update);

                ActivityLogger::log(
                    userId: $user->id,
                    action: 'financial_period.closed',
                    module: 'finance',
                    recordType: 'financial_periods',
                    recordId: $locked->id,
                    oldValues: $oldValues,
                    newValues: [
                        'status' => 'closed',
                        'closing_balance' =>
                            $closingBalance,
                        'closed_at' =>
                            $locked->fresh()->closed_at,
                        'closed_by' => $user->id,
                    ],
                    metadata: [
                        'period_label' =>
                            $this->periodLabel($locked),
                        'summaries_generated' =>
                            $rows->count(),
                        'total_outstanding' =>
                            $totalOutstanding,
                        'net_cash_movement' =>
                            $netCashMovement,
                    ],
                );

                return $locked->fresh([
                    'openedBy',
                    'closedBy',
                    'summaries.location',
                    'adjustments',
                ]);
            }
        );
    }

    /**
     * المصدر الواحد لحساب ملخص الفروع.
     *
     * لاحظ:
     * confirmed_collections أبقيناه مطابقاً للسلوك الموجود
     * في خدمتك الحالية (invoice.paid_amount) حتى لا نغير
     * تعريفاً محاسبياً قائماً دون قرار مستقل.
     */
    private function calculateSummaryRows(
        FinancialPeriod $period
    ): Collection {
        $start = Carbon::parse(
            $period->start_date
        )->startOfDay();

        $end = Carbon::parse(
            $period->end_date
        )->endOfDay();

        $locations = Location::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        return $locations
            ->map(
                function (
                    Location $location
                ) use (
                    $period,
                    $start,
                    $end
                ): object {
                    $invoiceQuery = Invoice::query()
                        ->where(
                            'location_id',
                            $location->id
                        )
                        ->where('status', 'active')
                        ->whereBetween(
                            'issued_at',
                            [$start, $end]
                        );

                    $grossSales = round(
                        (float)
                        (clone $invoiceQuery)
                            ->sum('subtotal'),
                        2
                    );

                    $discounts = round(
                        (float)
                        (clone $invoiceQuery)
                            ->sum('discount_amount'),
                        2
                    );

                    $netSales = round(
                        (float)
                        (clone $invoiceQuery)
                            ->sum('total_amount'),
                        2
                    );

                    $orderCollections = (float) DB::table('payments')
                        ->where('location_id', $location->id)
                        ->whereIn('status', ['confirmed', 'corrected', 'refunded'])
                        ->whereBetween('paid_at', [$start, $end])
                        ->sum('amount');

                    $customerCollections = (float) DB::table('customer_payments')
                        ->where('location_id', $location->id)
                        ->where('status', 'confirmed')
                        ->whereBetween('paid_at', [$start, $end])
                        ->sum('amount');

                    $confirmedCollections = round($orderCollections + $customerCollections, 2);

                    $outstanding = round(
                        (float)
                        (clone $invoiceQuery)
                            ->sum('remaining_amount'),
                        2
                    );

                    $invoiceCount = (int)
                        (clone $invoiceQuery)->count();

                    $orderCount = (int)
                        Order::query()
                            ->where(
                                'location_id',
                                $location->id
                            )
                            ->where(
                                'status',
                                '!=',
                                'cancelled'
                            )
                            ->whereBetween(
                                'created_at',
                                [$start, $end]
                            )
                            ->count();

                    /*
                     * refunds لا يحمل location_id في البنية الحالية.
                     * لذلك يتم تحديد الفرع من payment.location_id.
                     */
                    $refunds = round(
                        (float)
                        DB::table('refunds')
                            ->join(
                                'payments',
                                'payments.id',
                                '=',
                                'refunds.payment_id'
                            )
                            ->where(
                                'payments.location_id',
                                $location->id
                            )
                            ->whereBetween(
                                'refunds.processed_at',
                                [$start, $end]
                            )
                            ->sum('refunds.amount'),
                        2
                    );

                    /*
                     * أبقينا المعادلة الحالية كما هي حتى لا نغير
                     * المعنى المحاسبي للحقل في هذه الصيانة.
                     */
                    $averageOrderValue =
                        $invoiceCount > 0
                            ? round(
                                $netSales / $invoiceCount,
                                2
                            )
                            : 0.0;

                    return (object) [
                        'location_id' =>
                            $location->id,
                        'location' => $location,
                        'gross_sales' =>
                            $grossSales,
                        'discounts' =>
                            $discounts,
                        'net_sales' =>
                            $netSales,
                        'confirmed_collections' =>
                            $confirmedCollections,
                        'refunds' =>
                            $refunds,
                        'outstanding_amount' =>
                            $outstanding,
                        'invoice_count' =>
                            $invoiceCount,
                        'order_count' =>
                            $orderCount,
                        'average_order_value' =>
                            $averageOrderValue,
                        'opening_balance' =>
                            round(
                                (float)
                                $period->opening_balance,
                                2
                            ),
                        'closing_balance' =>
                            $period->closing_balance,
                    ];
                }
            )
            ->values();
    }

    private function periodExpenseNet(FinancialPeriod $period): float
    {
        $posted = (float) DB::table('sales_ledger_entries')
            ->where('financial_period_id', $period->id)
            ->where('entry_type', 'expense')
            ->sum('amount');
        $reversed = (float) DB::table('sales_ledger_entries')
            ->where('financial_period_id', $period->id)
            ->where('entry_type', 'expense_reversal')
            ->sum('amount');

        return round(max(0, $posted - $reversed), 2);
    }

    /**
     * يمنع إغلاق الفترة إذا كان الإعداد مفعلاً
     * ويوجد كاشير مفتوح يتقاطع زمنياً مع الفترة.
     */
    private function assertNoBlockingOpenCashSessions(
        FinancialPeriod $period
    ): void {
        $shouldBlock = (bool) SystemSetting::get(
            'block_financial_period_close_with_open_cash_sessions',
            true
        );

        if (! $shouldBlock) {
            return;
        }

        $start = Carbon::parse(
            $period->start_date
        )->startOfDay();

        $end = Carbon::parse(
            $period->end_date
        )->endOfDay();

        $openSessions = DB::table('cash_sessions')
            ->leftJoin(
                'locations',
                'locations.id',
                '=',
                'cash_sessions.location_id'
            )
            ->where(
                'cash_sessions.status',
                'open'
            )
            ->where(
                'cash_sessions.opened_at',
                '<=',
                $end
            )
            ->where(function ($query) use ($start) {
                $query
                    ->whereNull(
                        'cash_sessions.closed_at'
                    )
                    ->orWhere(
                        'cash_sessions.closed_at',
                        '>=',
                        $start
                    );
            })
            ->select([
                'cash_sessions.id',
                'cash_sessions.location_id',
                'cash_sessions.opened_at',
                'locations.name as location_name',
            ])
            ->orderBy('cash_sessions.id')
            ->get();

        if ($openSessions->isEmpty()) {
            return;
        }

        $locations = $openSessions
            ->pluck('location_name')
            ->filter()
            ->unique()
            ->values()
            ->implode('، ');

        $suffix = $locations !== ''
            ? " الفروع: {$locations}."
            : '';

        throw ValidationException::withMessages([
            'period' =>
                'لا يمكن إغلاق الفترة المالية لوجود جلسات كاشير مفتوحة تتقاطع مع هذه الفترة.'
                . $suffix
                . ' أغلق جلسات الكاشير أولاً ثم أعد المحاولة.',
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: int, 3: int}
     */
    private function resolvePeriodDates(
        array $data
    ): array {
        $year = isset($data['year'])
            ? (int) $data['year']
            : null;

        $month = isset($data['month'])
            ? (int) $data['month']
            : null;

        if (
            $month !== null
            && ($month < 1 || $month > 12)
        ) {
            throw ValidationException::withMessages([
                'month' =>
                    'الشهر يجب أن يكون بين 1 و12.',
            ]);
        }

        if (! $year || ! $month) {
            throw ValidationException::withMessages([
                'month' =>
                    'يجب تحديد السنة والشهر.',
            ]);
        }

        $startDate = Carbon::create(
            $year,
            $month,
            1
        )->startOfMonth();

        $endDate = $startDate
            ->copy()
            ->endOfMonth()
            ->endOfDay();

        return [
            $startDate,
            $endDate,
            $year,
            $month,
        ];
    }

    private function isOpen(
        FinancialPeriod $period
    ): bool {
        return $this->statusValue($period)
            === 'open';
    }

    private function statusValue(
        FinancialPeriod $period
    ): string {
        $status = $period->status;

        return $status instanceof \BackedEnum
            ? (string) $status->value
            : (string) $status;
    }

    private function periodLabel(
        FinancialPeriod $period
    ): string {
        return $period->year
            . '-'
            . str_pad(
                (string) $period->month,
                2,
                '0',
                STR_PAD_LEFT
            );
    }
}
