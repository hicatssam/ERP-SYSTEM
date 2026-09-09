<?php

namespace App\Services\Inventory;

use App\Models\InventoryExpiryAlertDelivery;
use App\Models\User;
use App\Notifications\InventoryExpiryNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class InventoryExpiryMonitorService
{
    public function __construct(
        private readonly InventoryExpirySettings $settings,
        private readonly InventoryExpiryWhatsAppSender $whatsApp,
    ) {
    }

    public function run(bool $force = false): array
    {
        $stats = [
            'batches_scanned' => 0,
            'alerts_due' => 0,
            'database_sent' => 0,
            'email_sent' => 0,
            'whatsapp_sent' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        if (! $force && ! $this->settings->enabled()) {
            $stats['skipped']++;
            return $stats;
        }

        $today = Carbon::today();
        $maxDays = max($this->settings->thresholds() ?: [60]);

        $batches = DB::table('inventory_batches as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoin('locations as l', 'l.id', '=', 'b.location_id')
            ->whereNotNull('b.expiry_date')
            ->where('b.available_quantity', '>', 0)
            ->whereDate(
                'b.expiry_date',
                '<=',
                $today->copy()->addDays($maxDays)->toDateString()
            )
            ->orderBy('b.expiry_date')
            ->get([
                'b.id',
                'b.product_id',
                'b.location_id',
                'b.batch_number',
                'b.manufacturing_date',
                'b.expiry_date',
                'b.available_quantity',
                'p.name',
                'p.name_ar',
                'p.sku',
                'l.name as location_name',
            ]);

        $stats['batches_scanned'] = $batches->count();

        foreach ($batches as $batch) {
            $expiry = Carbon::parse($batch->expiry_date)->startOfDay();

            $daysLeft = (int) $today->diffInDays($expiry, false);
            $stage = $this->stageForDays($daysLeft);

            if ($stage === null) {
                continue;
            }

            $stats['alerts_due']++;

            $payload = $this->payload($batch, $stage, $daysLeft);
            $recipients = $this->recipients((int) $batch->location_id);

            if ($recipients->isEmpty()) {
                $stats['skipped']++;
                continue;
            }

            foreach ($recipients as $user) {
                if ($this->settings->databaseEnabled()) {
                    $this->deliverNotification(
                        $user,
                        $payload,
                        'database',
                        $stats
                    );
                }

                if ($this->settings->emailEnabled()) {
                    if (filled($user->email)) {
                        $this->deliverNotification(
                            $user,
                            $payload,
                            'mail',
                            $stats
                        );
                    } else {
                        $stats['skipped']++;
                    }
                }

                if ($this->settings->whatsAppEnabled()) {
                    $this->deliverWhatsApp(
                        $user,
                        $payload,
                        $stats
                    );
                }
            }
        }

        return $stats;
    }

    public function stageForDays(int $daysLeft): ?string
    {
        if ($daysLeft <= 0) {
            return '0';
        }

        if ($daysLeft <= $this->settings->criticalDays()) {
            return '7';
        }

        if ($daysLeft <= $this->settings->warningDays()) {
            return '30';
        }

        if ($daysLeft <= $this->settings->earlyDays()) {
            return '60';
        }

        return null;
    }

    private function recipients(int $locationId): Collection
    {
        $users = User::permission('inventory.expiry.receive')->get();

        return $users
            ->filter(function (User $user) use ($locationId): bool {
                if ($user->can('inventory.expiry.receive_all')) {
                    return true;
                }

                return $this->userHasLocation($user, $locationId);
            })
            ->values();
    }

    private function userHasLocation(User $user, int $locationId): bool
    {
        if (
            ! Schema::hasColumn('users', 'employee_id')
            || ! Schema::hasTable('employee_locations')
        ) {
            return false;
        }

        $employeeId = (int) ($user->getAttribute('employee_id') ?? 0);

        if ($employeeId <= 0) {
            return false;
        }

        $today = now()->toDateString();

        return DB::table('employee_locations')
            ->where('employee_id', $employeeId)
            ->where('location_id', $locationId)
            ->where(function ($query) use ($today): void {
                $query
                    ->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', $today);
            })
            ->where(function ($query) use ($today): void {
                $query
                    ->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', $today);
            })
            ->exists();
    }

    private function deliverNotification(
        User $user,
        array $payload,
        string $channel,
        array &$stats
    ): void {
        $fingerprint = $this->fingerprint(
            (int) $payload['inventory_batch_id'],
            (string) $payload['stage'],
            (int) $user->id,
            $channel
        );

        $delivery = InventoryExpiryAlertDelivery::query()
            ->firstOrNew(['fingerprint' => $fingerprint]);

        if ($delivery->exists && $delivery->status === 'sent') {
            $stats['skipped']++;
            return;
        }

        $delivery->fill([
            'inventory_batch_id' => $payload['inventory_batch_id'],
            'product_id' => $payload['product_id'],
            'location_id' => $payload['location_id'],
            'recipient_user_id' => $user->id,
            'stage' => $payload['stage'],
            'channel' => $channel,
            'days_left' => $payload['days_left'],
            'message' => $payload['message'],
            'attempts' => (int) $delivery->attempts + 1,
            'last_attempt_at' => now(),
            'status' => 'pending',
            'error' => null,
        ])->save();

        try {
            $user->notify(
                new InventoryExpiryNotification($payload, $channel)
            );

            $delivery->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error' => null,
            ]);

            $channel === 'database'
                ? $stats['database_sent']++
                : $stats['email_sent']++;
        } catch (Throwable $e) {
            report($e);

            $delivery->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 5000),
            ]);

            $stats['failed']++;
        }
    }

    private function deliverWhatsApp(
        User $user,
        array $payload,
        array &$stats
    ): void {
        $fingerprint = $this->fingerprint(
            (int) $payload['inventory_batch_id'],
            (string) $payload['stage'],
            (int) $user->id,
            'whatsapp'
        );

        $delivery = InventoryExpiryAlertDelivery::query()
            ->firstOrNew(['fingerprint' => $fingerprint]);

        if ($delivery->exists && $delivery->status === 'sent') {
            $stats['skipped']++;
            return;
        }

        $phone = $this->userPhone($user);

        if ($phone === null) {
            $delivery->fill([
                'inventory_batch_id' => $payload['inventory_batch_id'],
                'product_id' => $payload['product_id'],
                'location_id' => $payload['location_id'],
                'recipient_user_id' => $user->id,
                'stage' => $payload['stage'],
                'channel' => 'whatsapp',
                'days_left' => $payload['days_left'],
                'message' => $payload['message'],
                'attempts' => (int) $delivery->attempts + 1,
                'last_attempt_at' => now(),
                'status' => 'skipped',
                'error' => 'Recipient has no employee phone.',
            ])->save();

            $stats['skipped']++;
            return;
        }

        $delivery->fill([
            'inventory_batch_id' => $payload['inventory_batch_id'],
            'product_id' => $payload['product_id'],
            'location_id' => $payload['location_id'],
            'recipient_user_id' => $user->id,
            'stage' => $payload['stage'],
            'channel' => 'whatsapp',
            'days_left' => $payload['days_left'],
            'message' => $payload['message'],
            'attempts' => (int) $delivery->attempts + 1,
            'last_attempt_at' => now(),
            'status' => 'pending',
            'error' => null,
        ])->save();

        $result = $this->whatsApp->send(
            $phone,
            (string) $payload['message']
        );

        if ($result['success']) {
            $delivery->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error' => null,
            ]);

            $stats['whatsapp_sent']++;
            return;
        }

        $delivery->update([
            'status' => 'failed',
            'error' => $result['error'],
        ]);

        $stats['failed']++;
    }

    private function userPhone(User $user): ?string
    {
        if (
            ! Schema::hasColumn('users', 'employee_id')
            || ! Schema::hasTable('employees')
        ) {
            return null;
        }

        $employeeId = (int) ($user->getAttribute('employee_id') ?? 0);

        if ($employeeId <= 0) {
            return null;
        }

        $phone = trim((string) DB::table('employees')
            ->where('id', $employeeId)
            ->value('phone'));

        return $phone !== '' ? $phone : null;
    }

    private function payload(
        object $batch,
        string $stage,
        int $daysLeft
    ): array {
        $productName = trim((string) (
            $batch->name_ar
            ?: $batch->name
            ?: $batch->sku
            ?: ('#' . $batch->product_id)
        ));

        $statusLabel = $daysLeft < 0
            ? 'منتهي الصلاحية منذ ' . abs($daysLeft) . ' يوم'
            : (
                $daysLeft === 0
                    ? 'ينتهي اليوم'
                    : 'متبقي ' . $daysLeft . ' يوم'
            );

        $message =
            "تنبيه صلاحية مخزون\n"
            . "المنتج: {$productName}\n"
            . "التشغيلة: " . ($batch->batch_number ?: '—') . "\n"
            . "الموقع: " . ($batch->location_name ?: '—') . "\n"
            . "الكمية المتبقية: "
            . number_format((float) $batch->available_quantity, 3)
            . "\n"
            . "تاريخ الانتهاء: "
            . Carbon::parse($batch->expiry_date)->format('Y-m-d')
            . "\n"
            . "الحالة: {$statusLabel}";

        return [
            'fingerprint' => 'inventory_expiry_' . $batch->id . '_' . $stage,
            'type' => 'inventory_expiry',
            'title' => $daysLeft <= 0
                ? 'مخزون منتهي الصلاحية'
                : 'مخزون يقترب من انتهاء الصلاحية',
            'message' => $message,
            'inventory_batch_id' => (int) $batch->id,
            'product_id' => (int) $batch->product_id,
            'product_name' => $productName,
            'sku' => $batch->sku,
            'location_id' => (int) $batch->location_id,
            'location_name' => $batch->location_name,
            'batch_number' => $batch->batch_number,
            'manufacturing_date' => $batch->manufacturing_date,
            'expiry_date' => Carbon::parse($batch->expiry_date)->format('Y-m-d'),
            'available_quantity' => number_format(
                (float) $batch->available_quantity,
                3
            ),
            'days_left' => $daysLeft,
            'stage' => $stage,
            'status_label' => $statusLabel,
            'priority' => $daysLeft <= 7
                ? 'high'
                : ($daysLeft <= 30 ? 'medium' : 'low'),
            'url' => '/inventory/expiry',
        ];
    }

    private function fingerprint(
        int $batchId,
        string $stage,
        int $userId,
        string $channel
    ): string {
        return implode(':', [
            'expiry',
            $batchId,
            $stage,
            $userId,
            $channel,
        ]);
    }
}
