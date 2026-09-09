<?php

namespace App\Services\Restaurant;

use App\Enums\RestaurantTableSessionStatus;
use App\Models\RestaurantTable;
use App\Models\RestaurantTableSession;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Notifications\RestaurantTableSessionNotification;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestaurantTableService
{
    /**
     * Open a new session for a table.
     */
    public function open(
        RestaurantTable $table,
        User $user,
        int $guestCount = 1,
        ?string $notes = null
    ): RestaurantTableSession {
        return DB::transaction(function () use (
            $table,
            $user,
            $guestCount,
            $notes
        ): RestaurantTableSession {
            $lockedTable = RestaurantTable::query()
                ->whereKey($table->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedTable->is_active) {
                throw ValidationException::withMessages([
                    'restaurant_table_id' => 'لا يمكن فتح جلسة على طاولة غير مفعلة.',
                ]);
            }

            $existing = RestaurantTableSession::query()
                ->where('restaurant_table_id', $lockedTable->id)
                ->where('status', RestaurantTableSessionStatus::Open->value)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'restaurant_table_id' => 'هذه الطاولة لديها جلسة مفتوحة بالفعل.',
                ]);
            }

            $session = RestaurantTableSession::query()->create([
                'restaurant_table_id' => $lockedTable->id,
                'location_id' => $lockedTable->location_id,
                'opened_by' => $user->id,
                'closed_by' => null,
                'status' => RestaurantTableSessionStatus::Open,
                'guest_count' => max(1, $guestCount),
                'opened_at' => now(),
                'closed_at' => null,
                'notes' => $notes,
            ]);

            ActivityLogger::log(
                userId: $user->id,
                action: 'restaurant.table_session.opened',
                module: 'restaurant',
                recordType: 'restaurant_table_sessions',
                recordId: $session->id,
                oldValues: null,
                newValues: [
                    'restaurant_table_id' => $session->restaurant_table_id,
                    'location_id' => $session->location_id,
                    'status' => RestaurantTableSessionStatus::Open->value,
                    'guest_count' => $session->guest_count,
                    'opened_by' => $session->opened_by,
                    'opened_at' => $session->opened_at,
                ],
                metadata: [
                    'table_code' => $lockedTable->code,
                    'table_name' => $lockedTable->displayName(),
                ],
            );

            return $session->fresh([
                'table.area',
                'location',
                'openedBy.employee',
            ]);
        });
    }

    /**
     * Return the current open session, or open one automatically for a POS order.
     *
     * @return array{0: RestaurantTableSession, 1: bool}
     *         [session, wasOpened]
     */
    public function resolveForOrder(
        RestaurantTable $table,
        User $user,
        int $guestCount = 1
    ): array {
        return DB::transaction(function () use (
            $table,
            $user,
            $guestCount
        ): array {
            $lockedTable = RestaurantTable::query()
                ->whereKey($table->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedTable->is_active) {
                throw ValidationException::withMessages([
                    'restaurant_table_id' => 'الطاولة المحددة غير مفعلة.',
                ]);
            }

            $session = RestaurantTableSession::query()
                ->where('restaurant_table_id', $lockedTable->id)
                ->where('status', RestaurantTableSessionStatus::Open->value)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($session) {
                return [$session, false];
            }

            $session = RestaurantTableSession::query()->create([
                'restaurant_table_id' => $lockedTable->id,
                'location_id' => $lockedTable->location_id,
                'opened_by' => $user->id,
                'closed_by' => null,
                'status' => RestaurantTableSessionStatus::Open,
                'guest_count' => max(1, $guestCount),
                'opened_at' => now(),
                'closed_at' => null,
                'notes' => null,
            ]);

            ActivityLogger::log(
                userId: $user->id,
                action: 'restaurant.table_session.opened',
                module: 'restaurant',
                recordType: 'restaurant_table_sessions',
                recordId: $session->id,
                oldValues: null,
                newValues: [
                    'restaurant_table_id' => $session->restaurant_table_id,
                    'location_id' => $session->location_id,
                    'status' => RestaurantTableSessionStatus::Open->value,
                    'guest_count' => $session->guest_count,
                    'opened_by' => $session->opened_by,
                    'opened_at' => $session->opened_at,
                ],
                metadata: [
                    'source' => 'restaurant_pos',
                    'table_code' => $lockedTable->code,
                    'table_name' => $lockedTable->displayName(),
                ],
            );

            return [
                $session->fresh([
                    'table.area',
                    'location',
                    'openedBy.employee',
                ]),
                true,
            ];
        });
    }

    /**
     * Close the current table session.
     *
     * A session cannot be closed while it still has a non-completed,
     * non-cancelled order.
     */
    public function close(
        RestaurantTable $table,
        User $user
    ): RestaurantTableSession {
        return DB::transaction(function () use (
            $table,
            $user
        ): RestaurantTableSession {
            $lockedTable = RestaurantTable::query()
                ->whereKey($table->id)
                ->lockForUpdate()
                ->firstOrFail();

            $session = RestaurantTableSession::query()
                ->where('restaurant_table_id', $lockedTable->id)
                ->where('status', RestaurantTableSessionStatus::Open->value)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $session) {
                throw ValidationException::withMessages([
                    'restaurant_table_id' => 'لا توجد جلسة مفتوحة لهذه الطاولة.',
                ]);
            }

            $hasOpenOrders = $session->orders()
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->exists();

            if ($hasOpenOrders) {
                throw ValidationException::withMessages([
                    'restaurant_table_id' => 'لا يمكن إغلاق الجلسة قبل إكمال أو إلغاء الطلبات المفتوحة على الطاولة.',
                ]);
            }

            $oldValues = [
                'status' => RestaurantTableSessionStatus::Open->value,
                'closed_by' => $session->closed_by,
                'closed_at' => $session->closed_at,
            ];

            $session->update([
                'status' => 'closed',
                'closed_by' => $user->id,
                'closed_at' => now(),
            ]);

            ActivityLogger::log(
                userId: $user->id,
                action: 'restaurant.table_session.closed',
                module: 'restaurant',
                recordType: 'restaurant_table_sessions',
                recordId: $session->id,
                oldValues: $oldValues,
                newValues: [
                    'status' => 'closed',
                    'closed_by' => $user->id,
                    'closed_at' => $session->closed_at,
                ],
                metadata: [
                    'location_id' => $session->location_id,
                    'restaurant_table_id' => $lockedTable->id,
                    'table_code' => $lockedTable->code,
                    'table_name' => $lockedTable->displayName(),
                ],
            );

            return $session->fresh([
                'table.area',
                'location',
                'openedBy.employee',
                'closedBy.employee',
                'orders',
            ]);
        });
    }

    /** Notify every authorized operator at the affected restaurant location. */
    public function notifyOpened(RestaurantTableSession $session): void
    {
        NotificationDispatcher::notifyByPermissions(
            new RestaurantTableSessionNotification($session, 'opened'),
            ['restaurant_tables.view', 'restaurant_tables.manage', 'restaurant_pos.use'],
            $session->location_id,
            ['restaurant.view_all_locations'],
            $session->opened_by,
        );
    }

    public function notifyClosed(RestaurantTableSession $session): void
    {
        NotificationDispatcher::notifyByPermissions(
            new RestaurantTableSessionNotification($session, 'closed'),
            ['restaurant_tables.view', 'restaurant_tables.manage', 'restaurant_pos.use'],
            $session->location_id,
            ['restaurant.view_all_locations'],
            $session->closed_by,
        );
    }
}
