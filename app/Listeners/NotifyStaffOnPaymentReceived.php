<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Listeners\Concerns\HasNotificationDedup;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyStaffOnPaymentReceived implements ShouldQueue
{
    use HasNotificationDedup;

    private const GLOBAL_ROLES = ['General Manager', 'Accountant'];

    private const LOCAL_PERMISSIONS = [
        'payments.record',
        'payments.verify',
        'financial.branch.view',
        'financial.collections.view',
    ];

    private const GLOBAL_PERMISSIONS = [
        'financial.global.view',
    ];

    private const DEDUP_TTL_SECONDS = 86400;

    public function handle(PaymentReceived $event): void
    {
        $payment = $event->payment;
        $locationId = $payment->location_id;
        $fingerprint = 'payment_received_' . $payment->id;

        $users = User::query()
            ->where('is_active', true)
            ->with(['roles', 'permissions', 'employee.locations'])
            ->get()
            ->filter(function (User $user) use ($locationId): bool {
                if ($user->isAdmin()) {
                    return true;
                }

                $hasLocalPermission = collect(self::LOCAL_PERMISSIONS)
                    ->contains(fn (string $permission): bool => $user->can($permission));

                $hasGlobalPermission = collect(self::GLOBAL_PERMISSIONS)
                    ->contains(fn (string $permission): bool => $user->can($permission));

                if (! $hasLocalPermission && ! $hasGlobalPermission) {
                    return false;
                }

                if ($hasGlobalPermission) {
                    return true;
                }

                if ($user->hasAnyRole(self::GLOBAL_ROLES)) {
                    return true;
                }

                if (! $locationId) {
                    return true;
                }

                if (! $user->employee) {
                    return false;
                }

                return $user->employee->locations
                    ->contains('id', (int) $locationId);
            })
            ->values();

        foreach ($users as $user) {
            if ($event->receivedBy && $user->id === $event->receivedBy->id) {
                continue;
            }

            if (! $this->claimDedup(
                $user,
                $fingerprint,
                PaymentReceivedNotification::class,
                self::DEDUP_TTL_SECONDS
            )) {
                continue;
            }

            $user->notify(new PaymentReceivedNotification($payment));
        }
    }
}