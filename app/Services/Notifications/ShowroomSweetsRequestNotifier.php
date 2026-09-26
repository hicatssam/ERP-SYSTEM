<?php

namespace App\Services\Notifications;

use App\Models\ShowroomSweetsRequest;
use App\Models\User;
use App\Notifications\ShowroomSweetsRequestNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ShowroomSweetsRequestNotifier
{
    private const DEDUP_TTL_SECONDS = 600;

    public static function requestCreated(
        ShowroomSweetsRequest $request,
        ?User $actor = null
    ): void {
        $request->loadMissing([
            'requestingLocation',
            'factoryLocation',
            'items.product',
        ]);

        $users = self::recipients(
            locationIds: [
                (int) $request->factory_location_id,
            ],
            permissions: [
                'showroom_sweets_requests.start',
                'showroom_sweets_requests.update_status',
                'showroom_sweets_requests.view',
            ],
        );

        self::send(
            $users,
            new ShowroomSweetsRequestNotification(
                showroomSweetsRequest: $request,
                event: 'created',
            ),
            $actor
        );
    }

    public static function statusChanged(
        ShowroomSweetsRequest $request,
        string $fromStatus,
        string $toStatus,
        ?User $actor = null
    ): void {
        $request->loadMissing([
            'requestingLocation',
            'factoryLocation',
            'items.product',
        ]);

        $branchId =
            (int) $request->requesting_location_id;

        $factoryId =
            (int) $request->factory_location_id;

        [$locationIds, $permissions] =
            match ($toStatus) {
                'in_progress' => [
                    [$branchId, $factoryId],
                    [
                        'showroom_sweets_requests.view',
                        'showroom_sweets_requests.start',
                        'showroom_sweets_requests.ready',
                        'showroom_sweets_requests.update_status',
                    ],
                ],

                'ready' => [
                    [$branchId, $factoryId],
                    [
                        'showroom_sweets_requests.view',
                        'showroom_sweets_requests.ready',
                        'showroom_sweets_requests.receive',
                        'showroom_sweets_requests.update_status',
                    ],
                ],

                'completed' => [
                    [$branchId, $factoryId],
                    [
                        'showroom_sweets_requests.view',
                        'showroom_sweets_requests.create',
                        'showroom_sweets_requests.receive',
                        'showroom_sweets_requests.update_status',
                    ],
                ],

                'cancelled' => [
                    [$branchId, $factoryId],
                    [
                        'showroom_sweets_requests.view',
                        'showroom_sweets_requests.create',
                        'showroom_sweets_requests.cancel',
                        'showroom_sweets_requests.update_status',
                    ],
                ],

                default => [
                    [$branchId, $factoryId],
                    [
                        'showroom_sweets_requests.view',
                    ],
                ],
            };

        self::send(
            self::recipients(
                $locationIds,
                $permissions
            ),
            new ShowroomSweetsRequestNotification(
                showroomSweetsRequest: $request,
                event: 'status_changed',
                fromStatus: $fromStatus,
                toStatus: $toStatus,
            ),
            $actor
        );
    }

    private static function recipients(
        array $locationIds,
        array $permissions
    ): Collection {
        $locationIds = array_values(
            array_unique(
                array_filter(
                    array_map(
                        'intval',
                        $locationIds
                    )
                )
            )
        );

        return User::query()
            ->where('is_active', true)
            ->with([
                'roles',
                'permissions',
                'employee.locations',
            ])
            ->get()
            ->filter(
                function (User $user) use (
                    $locationIds,
                    $permissions
                ): bool {
                    if ($user->isAdmin()) {
                        return true;
                    }

                    if (
                        $user->can(
                            'showroom_sweets_requests.view_all'
                        )
                    ) {
                        return true;
                    }

                    if (
                        ! collect($permissions)
                            ->contains(
                                fn (string $permission) =>
                                    $user->can(
                                        $permission
                                    )
                            )
                    ) {
                        return false;
                    }

                    if (! $user->employee) {
                        return false;
                    }

                    return $user
                        ->employee
                        ->locations
                        ->contains(
                            fn ($location) =>
                                in_array(
                                    (int) $location->id,
                                    $locationIds,
                                    true
                                )
                        );
                }
            )
            ->unique('id')
            ->values();
    }

    private static function send(
        Collection $users,
        ShowroomSweetsRequestNotification $notification,
        ?User $actor
    ): void {
        foreach ($users as $user) {
            if (
                $actor
                && (int) $actor->id === (int) $user->id
                && ! $user->isAdmin()
            ) {
                continue;
            }

            $data =
                $notification->toDatabase(
                    $user
                );

            $fingerprint =
                (string) (
                    $data['fingerprint']
                    ?? ''
                );

            if (
                $fingerprint === ''
                || ! self::claimDedup(
                    $user,
                    $fingerprint
                )
            ) {
                continue;
            }

            $user->notify(
                clone $notification
            );
        }
    }

    private static function claimDedup(
        User $user,
        string $fingerprint
    ): bool {
        if (
            $user->notifications()
                ->where(
                    'type',
                    ShowroomSweetsRequestNotification::class
                )
                ->where(
                    'data->fingerprint',
                    $fingerprint
                )
                ->where(
                    'created_at',
                    '>=',
                    now()->subSeconds(
                        self::DEDUP_TTL_SECONDS
                    )
                )
                ->exists()
        ) {
            return false;
        }

        return Cache::add(
            "notif_dedup_{$user->id}_{$fingerprint}",
            1,
            self::DEDUP_TTL_SECONDS
        );
    }
}
