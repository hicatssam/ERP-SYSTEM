<?php

namespace App\Services\Chat;

use App\Models\ChatChannel;
use App\Models\ChatChannelMember;
use App\Models\ChatMessage;
use App\Models\ChatMessageReceipt;
use App\Models\ChatRead;
use App\Models\Location;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class BranchChatService
{
    /*
    |--------------------------------------------------------------------------
    | Branch channels
    |--------------------------------------------------------------------------
    */

    public function ensureBranchChannels(
        ?User $creator = null
    ): void {
        Location::query()
            ->branches()
            ->active()
            ->select([
                'id',
                'name',
            ])
            ->get()
            ->each(
                function (
                    Location $location
                ) use ($creator): void {
                    ChatChannel::query()
                        ->firstOrCreate(
                            [
                                'location_id' =>
                                    $location->id,

                                'type' =>
                                    'branch',
                            ],
                            [
                                'name' =>
                                    'الإدارة ↔ '
                                    . $location->name,

                                'is_active' =>
                                    true,

                                'created_by' =>
                                    $creator?->id,
                            ]
                        );
                }
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Accessible channels
    |--------------------------------------------------------------------------
    |
    | Branch:
    | - Admin / manage / view_all_branches => all branch channels.
    | - chat.view => primary location branch only.
    |
    | Direct:
    | - only the two members can see the conversation.
    | - chat.manage does NOT expose private direct chats of other people.
    |
    */

    public function accessibleQuery(
        User $user
    ): Builder {
        $locationId =
            $this->primaryLocationId(
                $user
            );

        return ChatChannel::query()
            ->where(
                'is_active',
                true
            )
            ->with([
                'location',
                'members.user.employee.locations',
            ])
            ->where(
                function (
                    Builder $query
                ) use (
                    $user,
                    $locationId
                ): void {
                    /*
                     * Direct conversations where user is a member.
                     */
                    $query->where(
                        function (
                            Builder $direct
                        ) use ($user): void {
                            $direct
                                ->where(
                                    'type',
                                    'direct'
                                )
                                ->whereHas(
                                    'members',
                                    fn (
                                        Builder $members
                                    ) =>
                                        $members->where(
                                            'user_id',
                                            $user->id
                                        )
                                );
                        }
                    );

                    /*
                     * Branch channels.
                     */
                    $query->orWhere(
                        function (
                            Builder $branch
                        ) use (
                            $user,
                            $locationId
                        ): void {
                            $branch->where(
                                'type',
                                'branch'
                            );

                            if (
                                $user->isAdmin()
                                || $user->can(
                                    'chat.manage'
                                )
                                || $user->can(
                                    'chat.view_all_branches'
                                )
                            ) {
                                return;
                            }

                            if (
                                ! $user->can(
                                    'chat.view'
                                )
                                || ! $locationId
                            ) {
                                $branch->whereRaw(
                                    '1 = 0'
                                );

                                return;
                            }

                            $branch->where(
                                'location_id',
                                $locationId
                            );
                        }
                    );
                }
            );
    }

    public function accessibleChannels(
        User $user
    ): Collection {
        return $this
            ->accessibleQuery($user)
            ->get()
            ->sortBy(
                function (
                    ChatChannel $channel
                ): string {
                    return
                        ($channel->type === 'direct'
                            ? '0'
                            : '1')
                        . '|'
                        . $channel->name;
                }
            )
            ->values();
    }

    public function canView(
        User $user,
        ChatChannel $channel
    ): bool {
        if ($channel->type === 'direct') {
            return $channel
                ->members()
                ->where(
                    'user_id',
                    $user->id
                )
                ->exists();
        }

        if (
            $user->isAdmin()
            || $user->can('chat.manage')
            || $user->can(
                'chat.view_all_branches'
            )
        ) {
            return true;
        }

        if (! $user->can('chat.view')) {
            return false;
        }

        return
            (int) $this
                ->primaryLocationId(
                    $user
                )
            ===
            (int) $channel
                ->location_id;
    }

    /*
    |--------------------------------------------------------------------------
    | Sending
    |--------------------------------------------------------------------------
    |
    | Direct:
    | any active member can reply. This is intentional so Admin can message
    | ANY account and that account can answer even if its operational role
    | does not have branch-chat permissions.
    |
    | Branch:
    | existing chat.send rules remain.
    |
    */

    public function canSend(
        User $user,
        ChatChannel $channel
    ): bool {
        if (
            ! $user->is_active
            || ! $this->canView(
                $user,
                $channel
            )
        ) {
            return false;
        }

        if ($channel->type === 'direct') {
            return true;
        }

        return
            $user->isAdmin()
            || $user->can(
                'chat.manage'
            )
            || $user->can(
                'chat.send'
            );
    }

    public function canAttach(
        User $user,
        ChatChannel $channel
    ): bool {
        if (
            ! $user->is_active
            || ! $this->canView(
                $user,
                $channel
            )
        ) {
            return false;
        }

        /*
         * Direct chat behaves like a normal person-to-person messenger.
         */
        if ($channel->type === 'direct') {
            return true;
        }

        return
            $user->isAdmin()
            || $user->can(
                'chat.manage'
            )
            || $user->can(
                'chat.attachments'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Direct conversation hierarchy
    |--------------------------------------------------------------------------
    */

    public function canStartDirectWith(
        User $initiator,
        User $target
    ): bool {
        if (
            ! $initiator->is_active
            || ! $target->is_active
            || (int) $initiator->id
                ===
                (int) $target->id
        ) {
            return false;
        }

        if (
            $initiator->isAdmin()
            || $initiator->can(
                'chat.manage'
            )
            || $initiator->can(
                'chat.direct.start_all'
            )
        ) {
            return true;
        }

        if (
            ! $initiator->can(
                'chat.direct.start_location'
            )
        ) {
            return false;
        }

        $initiatorLocationId =
            $this->primaryLocationId(
                $initiator
            );

        $targetLocationId =
            $this->primaryLocationId(
                $target
            );

        return
            $initiatorLocationId
            && $targetLocationId
            && (int) $initiatorLocationId
                ===
                (int) $targetLocationId;
    }

    public function canStartAnyDirect(
        User $user
    ): bool {
        return
            $user->isAdmin()
            || $user->can(
                'chat.manage'
            )
            || $user->can(
                'chat.direct.start_all'
            )
            || (
                $user->can(
                    'chat.direct.start_location'
                )
                && $this->primaryLocationId(
                    $user
                )
            );
    }

    public function directUsers(
        User $initiator,
        ?string $search = null,
        int $limit = 50
    ): Collection {
        if (
            ! $this->canStartAnyDirect(
                $initiator
            )
        ) {
            return collect();
        }

        $query = User::query()
            ->where(
                'is_active',
                true
            )
            ->where(
                'id',
                '!=',
                $initiator->id
            )
            ->with([
                'employee.locations',
            ]);

        $hasGlobalScope =
            $initiator->isAdmin()
            || $initiator->can(
                'chat.manage'
            )
            || $initiator->can(
                'chat.direct.start_all'
            );

        if (! $hasGlobalScope) {
            $locationId =
                $this->primaryLocationId(
                    $initiator
                );

            if (! $locationId) {
                return collect();
            }

            $query->whereHas(
                'employee.employeeLocations',
                fn (
                    Builder $employeeLocations
                ) =>
                    $employeeLocations
                        ->where(
                            'employee_locations.location_id',
                            $locationId
                        )
                        ->where(
                            'employee_locations.is_primary',
                            true
                        )
            );
        }

        $search =
            trim(
                (string) $search
            );

        if ($search !== '') {
            $query->where(
                function (
                    Builder $filter
                ) use ($search): void {
                    $filter
                        ->where(
                            'username',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'email',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'employee',
                            function (
                                Builder $employee
                            ) use ($search): void {
                                $employee
                                    ->where(
                                        'full_name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'phone',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'job_title',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        );
                }
            );
        }

        return $query
            ->orderBy('username')
            ->limit(
                max(
                    1,
                    min(
                        100,
                        $limit
                    )
                )
            )
            ->get()
            ->filter(
                fn (User $target) =>
                    $this->canStartDirectWith(
                        $initiator,
                        $target
                    )
            )
            ->values();
    }

    public function startDirectConversation(
        User $initiator,
        User $target
    ): ChatChannel {
        if (
            ! $this->canStartDirectWith(
                $initiator,
                $target
            )
        ) {
            throw ValidationException::withMessages([
                'user' =>
                    'ليس لديك صلاحية بدء محادثة مباشرة مع هذا المستخدم.',
            ]);
        }

        $directKey =
            $this->directKey(
                $initiator->id,
                $target->id
            );

        return DB::transaction(
            function () use (
                $initiator,
                $target,
                $directKey
            ): ChatChannel {
                $channel =
                    ChatChannel::query()
                        ->firstOrCreate(
                            [
                                'direct_key' =>
                                    $directKey,
                            ],
                            [
                                'location_id' =>
                                    null,

                                'name' =>
                                    'محادثة مباشرة',

                                'type' =>
                                    'direct',

                                'is_active' =>
                                    true,

                                'created_by' =>
                                    $initiator->id,
                            ]
                        );

                foreach (
                    [
                        $initiator->id,
                        $target->id,
                    ] as $userId
                ) {
                    ChatChannelMember::query()
                        ->firstOrCreate(
                            [
                                'channel_id' =>
                                    $channel->id,

                                'user_id' =>
                                    $userId,
                            ],
                            [
                                'joined_at' =>
                                    now(),
                            ]
                        );
                }

                return $channel
                    ->fresh([
                        'members.user.employee.locations',
                    ]);
            }
        );
    }

    public function findDirectChannel(
        User $first,
        User $second
    ): ?ChatChannel {
        return ChatChannel::query()
            ->where(
                'type',
                'direct'
            )
            ->where(
                'direct_key',
                $this->directKey(
                    $first->id,
                    $second->id
                )
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Recipients
    |--------------------------------------------------------------------------
    */

    public function recipientUsersForChannel(
        ChatChannel $channel,
        User $sender
    ): Collection {
        if ($channel->type === 'direct') {
            return $channel
                ->members()
                ->where(
                    'user_id',
                    '!=',
                    $sender->id
                )
                ->with([
                    'user.employee.locations',
                ])
                ->get()
                ->pluck('user')
                ->filter(
                    fn (?User $user) =>
                        $user
                        && $user->is_active
                )
                ->unique('id')
                ->values();
        }

        return User::query()
            ->where(
                'is_active',
                true
            )
            ->whereKeyNot(
                $sender->id
            )
            ->with([
                'roles',
                'permissions',
                'employee.locations',
            ])
            ->get()
            ->filter(
                fn (User $user) =>
                    $this->canView(
                        $user,
                        $channel
                    )
            )
            ->unique('id')
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Receipts + notifications
    |--------------------------------------------------------------------------
    */

    public function createReceiptsAndNotify(
        ChatMessage $message,
        User $sender
    ): Collection {
        $message->loadMissing([
            'channel.location',
            'channel.members.user.employee',
            'user.employee',
            'attachments',
        ]);

        $recipients =
            $this->recipientUsersForChannel(
                $message->channel,
                $sender
            );

        $now = now();

        foreach ($recipients as $recipient) {
            ChatMessageReceipt::query()
                ->firstOrCreate(
                    [
                        'message_id' =>
                            $message->id,

                        'user_id' =>
                            $recipient->id,
                    ],
                    [
                        'delivered_at' =>
                            null,

                        'read_at' =>
                            null,

                        'created_at' =>
                            $now,

                        'updated_at' =>
                            $now,
                    ]
                );

            /*
             * إشعار DatabaseNotification لكل مستلم.
             * فشل الإشعار لا يلغي الرسالة نفسها.
             */
            try {
                $recipient->notify(
                    new ChatMessageReceivedNotification(
                        $message
                    )
                );
            } catch (Throwable $exception) {
                report(
                    $exception
                );
            }
        }

        return $recipients;
    }

    /*
    |--------------------------------------------------------------------------
    | Delivery
    |--------------------------------------------------------------------------
    */

    public function markDeliveredForUser(
        User $user
    ): void {
        $channelIds =
            $this->accessibleChannels(
                $user
            )
            ->pluck('id')
            ->map(
                fn ($id) =>
                    (int) $id
            )
            ->all();

        if (empty($channelIds)) {
            return;
        }

        ChatMessageReceipt::query()
            ->where(
                'user_id',
                $user->id
            )
            ->whereNull(
                'delivered_at'
            )
            ->whereHas(
                'message',
                fn (
                    Builder $query
                ) =>
                    $query->whereIn(
                        'channel_id',
                        $channelIds
                    )
            )
            ->update([
                'delivered_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Read
    |--------------------------------------------------------------------------
    */

    public function markRead(
        User $user,
        ChatChannel $channel,
        ?int $messageId = null
    ): void {
        if (
            ! $this->canView(
                $user,
                $channel
            )
        ) {
            return;
        }

        $lastMessageId =
            $messageId
            ?: (int) (
                ChatMessage::query()
                    ->where(
                        'channel_id',
                        $channel->id
                    )
                    ->max('id')
                ?: 0
            );

        if ($lastMessageId <= 0) {
            return;
        }

        $read =
            ChatRead::query()
                ->firstOrNew([
                    'channel_id' =>
                        $channel->id,

                    'user_id' =>
                        $user->id,
                ]);

        $current =
            (int) (
                $read
                    ->last_read_message_id
                ?: 0
            );

        if (
            $lastMessageId
            >= $current
        ) {
            $read
                ->last_read_message_id =
                    $lastMessageId;

            $read->read_at =
                now();

            $read->save();
        }

        ChatMessageReceipt::query()
            ->where(
                'user_id',
                $user->id
            )
            ->whereHas(
                'message',
                fn (
                    Builder $query
                ) =>
                    $query
                        ->where(
                            'channel_id',
                            $channel->id
                        )
                        ->where(
                            'id',
                            '<=',
                            $lastMessageId
                        )
            )
            ->whereNull(
                'read_at'
            )
            ->get()
            ->each(
                function (
                    ChatMessageReceipt $receipt
                ): void {
                    $receipt->delivered_at
                        ??= now();

                    $receipt->read_at =
                        now();

                    $receipt->save();
                }
            );

        /*
         * فتح القناة = إشعارات رسائل القناة تصبح مقروءة.
         */
        $user
            ->unreadNotifications()
            ->where(
                'type',
                ChatMessageReceivedNotification::class
            )
            ->get()
            ->each(
                function (
                    $notification
                ) use (
                    $channel,
                    $lastMessageId
                ): void {
                    $data =
                        (array)
                        $notification->data;

                    if (
                        (int) (
                            $data[
                                'channel_id'
                            ]
                            ?? 0
                        )
                        !==
                        (int) $channel->id
                    ) {
                        return;
                    }

                    if (
                        (int) (
                            $data[
                                'chat_message_id'
                            ]
                            ?? 0
                        )
                        <=
                        $lastMessageId
                    ) {
                        $notification
                            ->markAsRead();
                    }
                }
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Unread
    |--------------------------------------------------------------------------
    */

    public function unreadCountForChannel(
        User $user,
        ChatChannel $channel
    ): int {
        if (
            ! $this->canView(
                $user,
                $channel
            )
        ) {
            return 0;
        }

        $lastReadId =
            (int) (
                ChatRead::query()
                    ->where(
                        'channel_id',
                        $channel->id
                    )
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->value(
                        'last_read_message_id'
                    )
                ?: 0
            );

        return ChatMessage::query()
            ->where(
                'channel_id',
                $channel->id
            )
            ->where(
                'user_id',
                '!=',
                $user->id
            )
            ->when(
                $lastReadId > 0,
                fn (
                    Builder $query
                ) =>
                    $query->where(
                        'id',
                        '>',
                        $lastReadId
                    )
            )
            ->count();
    }

    public function totalUnread(
        User $user
    ): int {
        return $this
            ->accessibleChannels(
                $user
            )
            ->sum(
                fn (
                    ChatChannel $channel
                ) =>
                    $this
                        ->unreadCountForChannel(
                            $user,
                            $channel
                        )
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function primaryLocationId(
        User $user
    ): ?int {
        if (
            $user->relationLoaded(
                'employee'
            )
            && $user->employee
            && $user->employee
                ->relationLoaded(
                    'locations'
                )
        ) {
            $location =
                $user->employee
                    ->locations
                    ->first(
                        fn ($location) =>
                            (bool) (
                                $location
                                    ->pivot
                                    ?->is_primary
                                ?? false
                            )
                    );

            return $location
                ? (int) $location->id
                : null;
        }

        return $user
            ->primaryLocation()
            ?->id;
    }

    private function directKey(
        int $firstUserId,
        int $secondUserId
    ): string {
        $ids = [
            $firstUserId,
            $secondUserId,
        ];

        sort(
            $ids,
            SORT_NUMERIC
        );

        return
            'direct:user:'
            . $ids[0]
            . ':user:'
            . $ids[1];
    }
}