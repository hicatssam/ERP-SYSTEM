<?php

namespace App\Services\Inventory;

use App\Models\InventoryExpiryAlertDelivery;
use App\Models\InventoryExpiryScanRun;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\InventoryExpiryNotification;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class InventoryExpiryService
{
    public function __construct(
        private readonly MetaWhatsAppGateway $whatsApp
    ) {
    }

    public function monitoringEnabled(): bool
    {
        return $this->boolSetting(
            'inventory_expiry_monitoring_enabled',
            true
        );
    }

    /**
     * Public for deterministic unit testing and UI badges.
     *
     * null means the batch is outside the configured alert horizon.
     */
    public function thresholdForDays(int $daysLeft): ?int
    {
        $warning = $this->warningDays();
        $important = $this->importantDays();
        $critical = $this->criticalDays();

        if ($daysLeft <= 0) {
            return 0;
        }

        if ($daysLeft <= $critical) {
            return $critical;
        }

        if ($daysLeft <= $important) {
            return $important;
        }

        if ($daysLeft <= $warning) {
            return $warning;
        }

        return null;
    }

    public function severityForDays(int $daysLeft): string
    {
        if ($daysLeft < 0) {
            return 'expired';
        }

        if ($daysLeft === 0) {
            return 'today';
        }

        if ($daysLeft <= $this->criticalDays()) {
            return 'critical';
        }

        if ($daysLeft <= $this->importantDays()) {
            return 'important';
        }

        if ($daysLeft <= $this->warningDays()) {
            return 'warning';
        }

        return 'safe';
    }

    public function scanAndNotify(bool $force = false): array
    {
        if (! $force && ! $this->monitoringEnabled()) {
            return [
                'status' => 'disabled',
                'message' => 'مراقبة الصلاحية معطلة من الإعدادات.',
            ];
        }

        $run = InventoryExpiryScanRun::query()->create([
            'started_at' => now(),
            'status' => 'running',
        ]);

        $summary = [
            'status' => 'completed',
            'scanned_batches' => 0,
            'qualifying_batches' => 0,
            'recipient_count' => 0,
            'database_sent' => 0,
            'email_sent' => 0,
            'whatsapp_sent' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
        ];

        try {
            $today = Carbon::today();
            $warningDays = $this->warningDays();

            $batches = $this->baseBatchQuery()
                ->whereNotNull('b.expiry_date')
                ->where('b.available_quantity', '>', 0)
                ->whereDate(
                    'b.expiry_date',
                    '<=',
                    $today->copy()->addDays($warningDays)->toDateString()
                )
                ->orderBy('b.expiry_date')
                ->orderBy('b.id')
                ->get();

            $summary['scanned_batches'] = $batches->count();

            foreach ($batches as $batch) {
                $expiry = Carbon::parse($batch->expiry_date)->startOfDay();
                $daysLeft = (int) $today->diffInDays($expiry, false);
                $threshold = $this->thresholdForDays($daysLeft);

                if ($threshold === null) {
                    continue;
                }

                $summary['qualifying_batches']++;

                $recipients = $this->recipientsForLocation(
                    (int) $batch->location_id
                );

                $summary['recipient_count'] += $recipients->count();

                $payload = $this->payload(
                    $batch,
                    $daysLeft,
                    $threshold
                );

                foreach ($recipients as $user) {
                    $this->deliverToUser(
                        $run,
                        $user,
                        $batch,
                        $payload,
                        $threshold,
                        $daysLeft,
                        $summary
                    );
                }
            }

            $run->update([
                'completed_at' => now(),
                'status' => 'completed',
                'scanned_batches' => $summary['scanned_batches'],
                'qualifying_batches' => $summary['qualifying_batches'],
                'recipient_count' => $summary['recipient_count'],
                'database_sent' => $summary['database_sent'],
                'email_sent' => $summary['email_sent'],
                'whatsapp_sent' => $summary['whatsapp_sent'],
                'failed_count' => $summary['failed_count'],
                'summary' => $summary,
            ]);

            return $summary;
        } catch (Throwable $e) {
            $run->update([
                'completed_at' => now(),
                'status' => 'failed',
                'failed_count' => max(
                    1,
                    (int) $summary['failed_count']
                ),
                'summary' => array_merge(
                    $summary,
                    ['fatal_error' => $e->getMessage()]
                ),
            ]);

            throw $e;
        }
    }

    public function warningDays(): int
    {
        return max(
            1,
            (int) SystemSetting::get(
                'inventory_expiry_warning_days',
                60
            )
        );
    }

    public function importantDays(): int
    {
        return min(
            $this->warningDays(),
            max(
                1,
                (int) SystemSetting::get(
                    'inventory_expiry_important_days',
                    30
                )
            )
        );
    }

    public function criticalDays(): int
    {
        return min(
            $this->importantDays(),
            max(
                1,
                (int) SystemSetting::get(
                    'inventory_expiry_critical_days',
                    7
                )
            )
        );
    }

    public function channelEnabled(string $channel): bool
    {
        return match ($channel) {
            'database' => $this->boolSetting(
                'inventory_expiry_notify_database',
                true
            ),
            'email' => $this->boolSetting(
                'inventory_expiry_notify_email',
                true
            ),
            'whatsapp' => $this->boolSetting(
                'inventory_expiry_notify_whatsapp',
                false
            ),
            default => false,
        };
    }

    public function whatsappConfigured(): bool
    {
        return $this->whatsApp->configured();
    }

    private function baseBatchQuery(): Builder
    {
        return DB::table('inventory_batches as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('locations as l', 'l.id', '=', 'b.location_id')
            ->select([
                'b.id',
                'b.goods_receipt_item_id',
                'b.product_id',
                'b.location_id',
                'b.batch_number',
                'b.manufacturing_date',
                'b.expiry_date',
                'b.received_quantity',
                'b.available_quantity',
                'p.name as product_name',
                'p.name_ar as product_name_ar',
                'l.name as location_name',
            ])
            ->where('p.tracks_expiry', true);
    }

    private function recipientsForLocation(
        int $locationId
    ): Collection {
        return User::query()
            ->where('is_active', true)
            ->get()
            ->filter(function (User $user) use ($locationId): bool {
                if (
                    $user->can(
                        'inventory.expiry-alerts.receive_all'
                    )
                ) {
                    return true;
                }

                if (
                    ! $user->can(
                        'inventory.expiry-alerts.receive'
                    )
                ) {
                    return false;
                }

                $employeeId = (int) ($user->employee_id ?? 0);

                if ($employeeId <= 0) {
                    return false;
                }

                return DB::table('employee_locations')
                    ->where('employee_id', $employeeId)
                    ->where('location_id', $locationId)
                    ->where('is_primary', true)
                    ->whereNull('ended_at')
                    ->exists();
            })
            ->values();
    }

    private function deliverToUser(
        InventoryExpiryScanRun $run,
        User $user,
        object $batch,
        array $payload,
        int $threshold,
        int $daysLeft,
        array &$summary
    ): void {
        foreach (['database', 'email', 'whatsapp'] as $channel) {
            if (! $this->channelEnabled($channel)) {
                continue;
            }

            $delivery = InventoryExpiryAlertDelivery::query()
                ->firstOrCreate(
                    [
                        'inventory_batch_id' => $batch->id,
                        'recipient_user_id' => $user->id,
                        'expiry_date' => $batch->expiry_date,
                        'threshold_days' => $threshold,
                        'channel' => $channel,
                    ],
                    [
                        'scan_run_id' => $run->id,
                        'product_id' => $batch->product_id,
                        'location_id' => $batch->location_id,
                        'days_left' => $daysLeft,
                        'severity' => $payload['severity'],
                        'status' => 'pending',
                        'payload' => $payload,
                    ]
                );

            if ($delivery->status === 'sent') {
                $summary['skipped_count']++;
                continue;
            }

            $delivery->update([
                'scan_run_id' => $run->id,
                'days_left' => $daysLeft,
                'severity' => $payload['severity'],
                'attempted_at' => now(),
                'failure_reason' => null,
                'payload' => $payload,
            ]);

            try {
                match ($channel) {
                    'database' => $this->sendDatabase(
                        $user,
                        $payload
                    ),
                    'email' => $this->sendEmail(
                        $user,
                        $payload
                    ),
                    'whatsapp' => $this->sendWhatsApp(
                        $user,
                        $payload
                    ),
                };

                $delivery->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'failure_reason' => null,
                ]);

                $summary[$channel . '_sent']++;
            } catch (Throwable $e) {
                $delivery->update([
                    'status' => $channel === 'whatsapp'
                        && ! $this->whatsApp->configured()
                            ? 'skipped'
                            : 'failed',
                    'failure_reason' => mb_substr(
                        $e->getMessage(),
                        0,
                        4000
                    ),
                ]);

                if ($delivery->status === 'failed') {
                    $summary['failed_count']++;
                } else {
                    $summary['skipped_count']++;
                }
            }
        }
    }

    private function sendDatabase(
        User $user,
        array $payload
    ): void {
        DB::transaction(function () use ($user, $payload): void {
            $user->notify(
                new InventoryExpiryNotification($payload)
            );
        });
    }

    private function sendEmail(
        User $user,
        array $payload
    ): void {
        $email = $this->recipientEmail($user);

        if ($email === '') {
            throw new \RuntimeException(
                'لا يوجد بريد إلكتروني صالح للمستلم.'
            );
        }

        Mail::send(
            'emails.inventory-expiry-alert',
            ['alert' => $payload],
            function ($message) use ($email, $payload): void {
                $message
                    ->to($email)
                    ->subject($payload['title']);
            }
        );
    }

    private function sendWhatsApp(
        User $user,
        array $payload
    ): void {
        $phone = $this->recipientPhone($user);

        if ($phone === '') {
            throw new \RuntimeException(
                'لا يوجد رقم هاتف/واتساب للمستلم.'
            );
        }

        $this->whatsApp->sendText(
            $phone,
            $this->whatsAppMessage($payload)
        );
    }

    private function recipientEmail(User $user): string
    {
        $employeeId = (int) ($user->employee_id ?? 0);

        $employeeEmail = $employeeId > 0
            ? DB::table('employees')
                ->where('id', $employeeId)
                ->value('email')
            : null;

        return trim(
            (string) ($employeeEmail ?: $user->email ?: '')
        );
    }

    private function recipientPhone(User $user): string
    {
        $employeeId = (int) ($user->employee_id ?? 0);

        if ($employeeId <= 0) {
            return '';
        }

        return trim(
            (string) (
                DB::table('employees')
                    ->where('id', $employeeId)
                    ->value('phone')
                ?? ''
            )
        );
    }

    private function payload(
        object $batch,
        int $daysLeft,
        int $threshold
    ): array {
        $productName =
            $batch->product_name_ar
            ?: $batch->product_name
            ?: ('منتج #' . $batch->product_id);

        $severity = $this->severityForDays($daysLeft);
        $statusText = $this->statusText($daysLeft);

        return [
            'fingerprint' => implode(
                ':',
                [
                    'inventory_expiry',
                    $batch->id,
                    $batch->expiry_date,
                    $threshold,
                ]
            ),
            'type' => 'inventory_expiry_alert',
            'title' => $daysLeft < 0
                ? 'مخزون منتهي الصلاحية'
                : 'تنبيه قرب انتهاء صلاحية مخزون',
            'message' =>
                "{$productName} — {$batch->location_name} — "
                . "{$statusText}. الكمية المتبقية: "
                . number_format(
                    (float) $batch->available_quantity,
                    3
                ),
            'priority' => in_array(
                $severity,
                ['expired', 'today', 'critical'],
                true
            ) ? 'high' : 'medium',
            'icon' => 'alert-triangle',
            'url' => '/inventory/expiry?batch_id=' . $batch->id,
            'inventory_batch_id' => (int) $batch->id,
            'product_id' => (int) $batch->product_id,
            'product_name' => $productName,
            'location_id' => (int) $batch->location_id,
            'location_name' => (string) $batch->location_name,
            'batch_number' => (string) ($batch->batch_number ?: '—'),
            'manufacturing_date' => $batch->manufacturing_date,
            'expiry_date' => $batch->expiry_date,
            'available_quantity' => (float) $batch->available_quantity,
            'days_left' => $daysLeft,
            'threshold_days' => $threshold,
            'severity' => $severity,
            'status_text' => $statusText,
        ];
    }

    private function statusText(int $daysLeft): string
    {
        if ($daysLeft < 0) {
            return 'منتهي منذ ' . abs($daysLeft) . ' يوم';
        }

        if ($daysLeft === 0) {
            return 'ينتهي اليوم';
        }

        return 'متبقي ' . $daysLeft . ' يوم';
    }

    private function whatsAppMessage(array $payload): string
    {
        $business = trim(
            (string) SystemSetting::get(
                'business_legal_name',
                SystemSetting::get(
                    'system_name',
                    config('app.name')
                )
            )
        );

        return implode("\n", [
            "⚠️ {$payload['title']}",
            $business !== '' ? "المنشأة: {$business}" : null,
            "المنتج: {$payload['product_name']}",
            "الموقع: {$payload['location_name']}",
            "التشغيلة: {$payload['batch_number']}",
            "تاريخ الانتهاء: {$payload['expiry_date']}",
            "الحالة: {$payload['status_text']}",
            'الكمية المتبقية: '
                . number_format(
                    (float) $payload['available_quantity'],
                    3
                ),
        ]);
    }

    private function boolSetting(
        string $key,
        bool $default
    ): bool {
        return filter_var(
            SystemSetting::get(
                $key,
                $default ? 1 : 0
            ),
            FILTER_VALIDATE_BOOL
        );
    }
}
