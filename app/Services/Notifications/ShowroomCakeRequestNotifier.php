<?php

namespace App\Services\Notifications;

use App\Models\ShowroomCakeRequest;
use App\Models\User;
use App\Notifications\ShowroomCakeRequestNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ShowroomCakeRequestNotifier
{
    private const DEDUP_TTL_SECONDS = 600;

    public static function requestCreated(
        ShowroomCakeRequest $request,
        ?User $actor = null
    ): void {
        $users = self::recipients(
            locationIds: [
                (int) $request->factory_location_id,
            ],
            permissions: [
                'showroom_cake_requests.update_status',
                'showroom_cake_requests.view',
            ],
        );

        self::send(
            $users,
            new ShowroomCakeRequestNotification(
                $request,
                'created'
            ),
            $actor
        );
    }

    public static function statusChanged(
        ShowroomCakeRequest $request,
        string $fromStatus,
        string $toStatus,
        ?User $actor = null
    ): void {
        $branchId =
            (int) $request->requesting_location_id;

        $factoryId =
            (int) $request->factory_location_id;

        [$locationIds, $permissions] =
            match ($toStatus) {
                'in_progress' => [
                    [$branchId, $factoryId],
                    [
                        'showroom_cake_requests.view',
                        'showroom_cake_requests.update_status',
                    ],
                ],

                'ready' => [
                    [$branchId, $factoryId],
                    [
                        'showroom_cake_requests.view',
                        'showroom_cake_requests.update_status',
                    ],
                ],

                'completed',
                'cancelled' => [
                    [$branchId, $factoryId],
                    [
                        'showroom_cake_requests.view',
                        'showroom_cake_requests.create',
                        'showroom_cake_requests.update_status',
                    ],
                ],

                default => [
                    [$branchId, $factoryId],
                    [
                        'showroom_cake_requests.view',
                    ],
                ],
            };

        self::send(
            self::recipients(
                $locationIds,
                $permissions
            ),
            new ShowroomCakeRequestNotification(
                $request,
                'status_changed',
                $fromStatus,
                $toStatus
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
                            'showroom_cake_requests.view_all'
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
        ShowroomCakeRequestNotification $notification,
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
                    ShowroomCakeRequestNotification::class
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
