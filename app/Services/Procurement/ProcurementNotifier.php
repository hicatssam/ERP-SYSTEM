<?php

namespace App\Services\Procurement;

use App\Models\User;
use App\Notifications\ProcurementNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class ProcurementNotifier
{
    private const GLOBAL_ROLES = [
        'General Manager',
        'Inventory Manager',
        'Accountant',
    ];

    /**
     * @param array<int, string> $permissions
     */
    public function send(
        string $type,
        string $title,
        string $message,
        string $url,
        string $priority,
        ?int $locationId,
        array $permissions,
        array $extra = [],
    ): void {
        $recipients = $this->recipients(
            $locationId,
            $permissions
        );

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new ProcurementNotification(
                array_merge([
                    'fingerprint' =>
                        $type . ':' .
                        ($extra['reference_id'] ?? md5($message)),
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'url' => $url,
                    'priority' => $priority,
                    'location_id' => $locationId,
                ], $extra)
            )
        );
    }

    /**
     * @param array<int, string> $permissions
     */
    private function recipients(
        ?int $locationId,
        array $permissions
    ): Collection {
        return User::query()
            ->where('is_active', true)
            ->with(['roles', 'permissions', 'employee.locations'])
            ->get()
            ->filter(function (User $user) use (
                $locationId,
                $permissions
            ): bool {
                if ($user->isAdmin()) {
                    return true;
                }

                $hasPermission = collect($permissions)
                    ->contains(
                        fn (string $permission): bool =>
                            $user->can($permission)
                    );

                if (! $hasPermission) {
                    return false;
                }

                if ($user->can('financial.global.view')) {
                    return true;
                }

                if ($user->hasAnyRole(self::GLOBAL_ROLES)) {
                    return true;
                }

                if ($locationId === null) {
                    return true;
                }

                if (! $user->employee) {
                    return false;
                }

                return $user->employee->locations
                    ->contains('id', (int) $locationId);
            })
            ->values();
    }
}