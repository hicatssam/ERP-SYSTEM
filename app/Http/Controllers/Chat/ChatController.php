<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatAttachment;
use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Models\ChatMessageReceipt;
use App\Models\ChatRead;
use App\Models\User;
use App\Services\Chat\BranchChatService;
use App\Services\ModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function __construct(
        private readonly BranchChatService $chatService
    ) {
    }

    public function index(
        Request $request
    ): View|RedirectResponse {
        $this->ensureChatEnabled();

        /** @var User $user */
        $user = $request->user();

        $this->chatService
            ->ensureBranchChannels($user);

        $channels =
            $this->channelsWithMeta(
                $user
            );

        if ($channels->isEmpty()) {
            return view(
                'chat.index',
                [
                    'channels' =>
                        $channels,

                    'selectedChannel' =>
                        null,

                    'canStartDirect' =>
                        $this->chatService
                            ->canStartAnyDirect(
                                $user
                            ),

                    'canSendSelectedChannel' =>
                        false,

                    'canAttachSelectedChannel' =>
                        false,
                ]
            );
        }

        return redirect()->route(
            'chat.show',
            $channels->first()['id']
        );
    }

    public function show(
        Request $request,
        ChatChannel $chatChannel
    ): View {
        $this->ensureChatEnabled();

        /** @var User $user */
        $user = $request->user();

        $this->authorizeView(
            $user,
            $chatChannel
        );

        $this->chatService
            ->ensureBranchChannels($user);

        $this->chatService
            ->markDeliveredForUser(
                $user
            );

        /*
         * فتح القناة = قراءة الرسائل الموجودة فيها.
         */
        $this->chatService
            ->markRead(
                $user,
                $chatChannel
            );

        $chatChannel->load([
            'location',
            'members.user.employee.locations',
        ]);

        return view(
            'chat.index',
            [
                'channels' =>
                    $this
                        ->channelsWithMeta(
                            $user
                        ),

                'selectedChannel' =>
                    $chatChannel,

                'canStartDirect' =>
                    $this->chatService
                        ->canStartAnyDirect(
                            $user
                        ),

                'canSendSelectedChannel' =>
                    $this->chatService
                        ->canSend(
                            $user,
                            $chatChannel
                        ),

                'canAttachSelectedChannel' =>
                    $this->chatService
                        ->canAttach(
                            $user,
                            $chatChannel
                        ),
            ]
        );
    }

    public function messages(
        Request $request,
        ChatChannel $chatChannel
    ): JsonResponse {
        $this->ensureChatEnabled();

        /** @var User $user */
        $user = $request->user();

        $this->authorizeView(
            $user,
            $chatChannel
        );

        $this->chatService
            ->markDeliveredForUser(
                $user
            );

        $afterId = max(
            0,
            (int) $request
                ->integer('after_id')
        );

        $query = ChatMessage::query()
            ->where(
                'channel_id',
                $chatChannel->id
            )
            ->with([
                'user.employee',
                'attachments',
                'replyTo.user.employee',
                'receipts.user.employee',
            ]);

        if ($afterId > 0) {
            $messages = $query
                ->where(
                    'id',
                    '>',
                    $afterId
                )
                ->orderBy('id')
                ->limit(100)
                ->get();
        } else {
            $messages = $query
                ->latest('id')
                ->limit(80)
                ->get()
                ->sortBy('id')
                ->values();
        }

        if ($messages->isNotEmpty()) {
            $this->chatService
                ->markRead(
                    $user,
                    $chatChannel,
                    (int) $messages
                        ->last()
                        ->id
                );
        }

        return response()->json([
            'messages' =>
                $messages
                    ->map(
                        fn (
                            ChatMessage $message
                        ) =>
                            $this
                                ->serializeMessage(
                                    $message,
                                    $user
                                )
                    )
                    ->values(),

            /*
             * حتى لو لم تصل رسالة جديدة،
             * نرسل حالات آخر رسائل المستخدم لتحديث:
             * مرسلة / وصلت / مقروءة.
             */
            'outgoing_statuses' =>
                $this
                    ->outgoingStatuses(
                        $user,
                        $chatChannel
                    ),

            'unread_total' =>
                $this->chatService
                    ->totalUnread(
                        $user
                    ),
        ]);
    }

    public function store(
        Request $request,
        ChatChannel $chatChannel
    ): JsonResponse {
        $this->ensureChatEnabled();

        /** @var User $user */
        $user = $request->user();

        abort_unless(
            $this->chatService
                ->canSend(
                    $user,
                    $chatChannel
                ),
            403,
            'ليس لديك صلاحية إرسال رسائل في هذه القناة.'
        );

        $hasFiles =
            $request->hasFile(
                'attachments'
            );

        if ($hasFiles) {
            abort_unless(
                $this->chatService
                    ->canAttach(
                        $user,
                        $chatChannel
                    ),
                403,
                'ليس لديك صلاحية إرسال مرفقات في المحادثات.'
            );
        }

        $validated =
            $request->validate(
                [
                    'message' => [
                        'nullable',
                        'string',
                        'max:5000',
                        'required_without:attachments',
                    ],

                    'reply_to_id' => [
                        'nullable',
                        'integer',
                        'exists:chat_messages,id',
                    ],

                    'attachments' => [
                        'nullable',
                        'array',
                        'max:5',
                    ],

                    'attachments.*' => [
                        'file',
                        'max:10240',
                        'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt',
                    ],
                ],
                [
                    'message.required_without' =>
                        'اكتب رسالة أو أرفق ملفًا.',

                    'attachments.max' =>
                        'يمكن إرفاق 5 ملفات كحد أقصى.',

                    'attachments.*.max' =>
                        'الحد الأقصى لحجم الملف الواحد 10MB.',

                    'attachments.*.mimes' =>
                        'نوع الملف غير مدعوم.',
                ]
            );

        if (
            ! empty(
                $validated[
                    'reply_to_id'
                ]
            )
        ) {
            $replyExists =
                ChatMessage::query()
                    ->whereKey(
                        $validated[
                            'reply_to_id'
                        ]
                    )
                    ->where(
                        'channel_id',
                        $chatChannel->id
                    )
                    ->exists();

            abort_unless(
                $replyExists,
                422,
                'الرسالة التي ترد عليها ليست ضمن هذه القناة.'
            );
        }

        $message = DB::transaction(
            function () use (
                $request,
                $validated,
                $user,
                $chatChannel,
                $hasFiles
            ): ChatMessage {
                $message =
                    ChatMessage::query()
                        ->create([
                            'channel_id' =>
                                $chatChannel->id,

                            'user_id' =>
                                $user->id,

                            'reply_to_id' =>
                                $validated[
                                    'reply_to_id'
                                ]
                                ?? null,

                            'message' =>
                                trim(
                                    (string) (
                                        $validated[
                                            'message'
                                        ]
                                        ?? ''
                                    )
                                )
                                ?: null,

                            'message_type' =>
                                $hasFiles
                                    ? 'attachment'
                                    : 'text',
                        ]);

                foreach (
                    $request->file(
                        'attachments',
                        []
                    ) as $file
                ) {
                    $storedPath =
                        $file->store(
                            'chat/'
                            . $chatChannel->id,
                            'public'
                        );

                    ChatAttachment::query()
                        ->create([
                            'message_id' =>
                                $message->id,

                            'disk' =>
                                'public',

                            'path' =>
                                $storedPath,

                            'original_name' =>
                                $file
                                    ->getClientOriginalName(),

                            'mime_type' =>
                                $file
                                    ->getMimeType(),

                            'size' =>
                                $file
                                    ->getSize(),
                        ]);
                }

                return $message;
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Receipts + notifications
        |--------------------------------------------------------------------------
        |
        | بعد نجاح حفظ الرسالة فقط.
        |
        */

        $this->chatService
            ->createReceiptsAndNotify(
                $message,
                $user
            );

        $message->load([
            'user.employee',
            'attachments',
            'replyTo.user.employee',
            'receipts.user.employee',
        ]);

        $this->chatService
            ->markRead(
                $user,
                $chatChannel,
                $message->id
            );

        return response()->json([
            'message' =>
                $this->serializeMessage(
                    $message,
                    $user
                ),

            'success' =>
                true,
        ]);
    }

    public function markRead(
        Request $request,
        ChatChannel $chatChannel
    ): JsonResponse {
        $this->ensureChatEnabled();

        /** @var User $user */
        $user = $request->user();

        $this->authorizeView(
            $user,
            $chatChannel
        );

        $validated = $request->validate([
            'message_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $messageId = isset($validated['message_id'])
            ? (int) $validated['message_id']
            : null;

        if ($messageId !== null) {
            abort_unless(
                ChatMessage::query()
                    ->whereKey($messageId)
                    ->where('channel_id', $chatChannel->id)
                    ->exists(),
                422,
                'الرسالة المحددة ليست ضمن هذه القناة.'
            );
        }

        $this->chatService
            ->markDeliveredForUser(
                $user
            );

        $this->chatService
            ->markRead(
                $user,
                $chatChannel,
                $messageId
            );

        return response()->json([
            'success' =>
                true,

            'unread_total' =>
                $this->chatService
                    ->totalUnread(
                        $user
                    ),
        ]);
    }

    public function directUsers(
        Request $request
    ): JsonResponse {
        $this->ensureChatEnabled();

        /** @var User $user */
        $user = $request->user();

        abort_unless(
            $this->chatService
                ->canStartAnyDirect(
                    $user
                ),
            403,
            'ليس لديك صلاحية بدء محادثات مباشرة.'
        );

        $users =
            $this->chatService
                ->directUsers(
                    $user,
                    $request->string('q')
                        ->trim()
                        ->toString(),
                    60
                );

        return response()->json([
            'users' =>
                $users
                    ->map(
                        function (
                            User $target
                        ) use ($user): array {
                            $location =
                                $target
                                    ->employee
                                    ?->locations
                                    ?->first(
                                        fn ($location) =>
                                            (bool) (
                                                $location
                                                    ->pivot
                                                    ?->is_primary
                                                ?? false
                                            )
                                    );

                            $existing =
                                $this->chatService
                                    ->findDirectChannel(
                                        $user,
                                        $target
                                    );

                            return [
                                'id' =>
                                    $target->id,

                                'name' =>
                                    $target
                                        ->employee
                                        ?->full_name

                                    ?? $target
                                        ->username

                                    ?? 'مستخدم',

                                'username' =>
                                    $target
                                        ->username,

                                'job_title' =>
                                    $target
                                        ->employee
                                        ?->job_title,

                                'location_name' =>
                                    $location
                                        ?->name,

                                'avatar' =>
                                    $this
                                        ->profileImageUrl(
                                            $target
                                                ->employee
                                                ?->profile_image

                                            ?? $target
                                                ->profile_image
                                        ),

                                'channel_id' =>
                                    $existing?->id,
                            ];
                        }
                    )
                    ->values(),
        ]);
    }


    public function startDirect(
        Request $request,
        User $user
    ): JsonResponse|RedirectResponse {
        $this->ensureChatEnabled();

        /** @var User $initiator */
        $initiator =
            $request->user();

        abort_unless(
            $this->chatService
                ->canStartAnyDirect(
                    $initiator
                ),
            403,
            'ليس لديك صلاحية بدء محادثات مباشرة.'
        );

        abort_if(
            (int) $initiator->id === (int) $user->id,
            422,
            'لا يمكنك بدء محادثة مباشرة مع نفسك.'
        );

        $channel =
            $this->chatService
                ->startDirectConversation(
                    $initiator,
                    $user
                );

        $url =
            route(
                'chat.show',
                $channel
            );

        if ($request->expectsJson()) {
            return response()->json([
                'success' =>
                    true,

                'channel_id' =>
                    $channel->id,

                'url' =>
                    $url,
            ]);
        }

        return redirect(
            $url
        );
    }


    public function unreadCount(
        Request $request
    ): JsonResponse {
        $this->ensureChatEnabled();

        /** @var User $user */
        $user = $request->user();

        $this->chatService
            ->ensureBranchChannels(
                $user
            );

        /*
         * وصول طلب الـBadge من متصفح المستخدم
         * يعتبر Delivery acknowledgement.
         */
        $this->chatService
            ->markDeliveredForUser(
                $user
            );

        $channels =
            $this->channelsWithMeta(
                $user
            );

        $channelIds =
            $channels
                ->pluck('id')
                ->map(
                    fn ($id) => (int) $id
                )
                ->filter()
                ->values();

        $latestIncomingMessage =
            $channelIds->isEmpty()
                ? null
                : ChatMessage::query()
                    ->whereIn(
                        'channel_id',
                        $channelIds->all()
                    )
                    ->where(
                        'user_id',
                        '!=',
                        $user->id
                    )
                    ->latest('id')
                    ->first([
                        'id',
                        'channel_id',
                        'created_at',
                    ]);

        return response()->json([
            'unread_count' =>
                (int) $channels
                    ->sum(
                        'unread_count'
                    ),

            /*
             * مهم للصوت:
             * لا نعتمد فقط على unread_count، لأن المستخدم
             * إذا كان فاتح القناة قد تتحول الرسالة إلى مقروءة
             * بسرعة ويظل unread_count = 0.
             */
            'latest_incoming_message_id' =>
                $latestIncomingMessage?->id,

            'latest_incoming_channel_id' =>
                $latestIncomingMessage?->channel_id,

            'channels' =>
                $channels
                    ->map(
                        fn (
                            array $channel
                        ) => [
                            'id' =>
                                $channel['id'],

                            'name' =>
                                $channel['name'],

                            'unread_count' =>
                                $channel[
                                    'unread_count'
                                ],

                            'last_message' =>
                                $channel[
                                    'last_message'
                                ],
                        ]
                    )
                    ->values(),
        ]);
    }

    private function channelsWithMeta(
        User $user
    ) {
        /*
        |--------------------------------------------------------------------------
        | Branch managers indexed by primary branch
        |--------------------------------------------------------------------------
        */

        $branchManagers =
            User::query()
                ->where(
                    'is_active',
                    true
                )
                ->whereHas(
                    'roles',
                    fn ($query) =>
                        $query->where(
                            'name',
                            'Branch Manager'
                        )
                )
                ->whereHas(
                    'employee.employeeLocations',
                    fn ($query) =>
                        $query->where(
                            'employee_locations.is_primary',
                            true
                        )
                )
                ->with([
                    'employee.employeeLocations',
                ])
                ->get()
                ->mapWithKeys(
                    function (
                        User $manager
                    ): array {
                        $locationId =
                            $manager
                                ->employee
                                ?->employeeLocations
                                ?->firstWhere(
                                    'is_primary',
                                    true
                                )
                                ?->location_id;

                        return $locationId
                            ? [
                                (int) $locationId =>
                                    $manager,
                            ]
                            : [];
                    }
                );

        return $this->chatService
            ->accessibleChannels(
                $user
            )
            ->map(
                function (
                    ChatChannel $channel
                ) use (
                    $user,
                    $branchManagers
                ): array {
                    $lastMessage =
                        ChatMessage::query()
                            ->where(
                                'channel_id',
                                $channel->id
                            )
                            ->with(
                                'user.employee'
                            )
                            ->latest('id')
                            ->first();

                    /*
                    |--------------------------------------------------------------------------
                    | Direct conversation
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $channel->type
                        === 'direct'
                    ) {
                        $channel->loadMissing([
                            'members.user.employee.locations',
                        ]);

                        $otherUser =
                            $channel
                                ->members
                                ->first(
                                    fn ($member) =>
                                        (int)
                                        $member->user_id
                                        !==
                                        (int)
                                        $user->id
                                )
                                ?->user;

                        $otherLocation =
                            $otherUser
                                ?->employee
                                ?->locations
                                ?->first(
                                    fn ($location) =>
                                        (bool) (
                                            $location
                                                ->pivot
                                                ?->is_primary
                                            ?? false
                                        )
                                );

                        $displayName =
                            $otherUser
                                ?->employee
                                ?->full_name

                            ?? $otherUser
                                ?->username

                            ?? 'مستخدم';

                        $avatar =
                            $otherUser
                                ?->employee
                                ?->profile_image

                            ?? $otherUser
                                ?->profile_image;

                        return [
                            'id' =>
                                $channel->id,

                            'name' =>
                                $channel->name,

                            'channel_type' =>
                                'direct',

                            'display_name' =>
                                $displayName,

                            /*
                             * kept for current Blade compatibility.
                             */
                            'location_name' =>
                                $displayName,

                            'participant_user_id' =>
                                $otherUser?->id,

                            'participant_name' =>
                                $displayName,

                            'participant_avatar' =>
                                $this
                                    ->profileImageUrl(
                                        $avatar
                                    ),

                            'participant_job_title' =>
                                $otherUser
                                    ?->employee
                                    ?->job_title,

                            'participant_location_name' =>
                                $otherLocation
                                    ?->name,

                            'unread_count' =>
                                $this->chatService
                                    ->unreadCountForChannel(
                                        $user,
                                        $channel
                                    ),

                            'last_message' =>
                                $lastMessage
                                    ? $this
                                        ->messagePreview(
                                            $lastMessage
                                        )
                                    : 'ابدأ المحادثة',

                            'last_message_at' =>
                                $lastMessage
                                    ?->created_at
                                    ?->diffForHumans(),
                        ];
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Branch channel
                    |--------------------------------------------------------------------------
                    */

                    /** @var User|null $branchManager */
                    $branchManager =
                        $branchManagers->get(
                            (int)
                            $channel
                                ->location_id
                        );

                    $managerName =
                        $branchManager
                            ?->employee
                            ?->full_name

                        ?? $branchManager
                            ?->username

                        ?? null;

                    $managerAvatar =
                        $branchManager
                            ?->employee
                            ?->profile_image

                        ?? $branchManager
                            ?->profile_image;

                    $locationName =
                        $channel
                            ->location
                            ?->name
                        ?? '—';

                    return [
                        'id' =>
                            $channel->id,

                        'name' =>
                            $channel->name,

                        'channel_type' =>
                            'branch',

                        'display_name' =>
                            $locationName,

                        'location_name' =>
                            $locationName,

                        'participant_user_id' =>
                            $branchManager?->id,

                        'participant_name' =>
                            $managerName,

                        'participant_avatar' =>
                            $this
                                ->profileImageUrl(
                                    $managerAvatar
                                ),

                        'participant_job_title' =>
                            'مدير الفرع',

                        'participant_location_name' =>
                            $locationName,

                        'unread_count' =>
                            $this->chatService
                                ->unreadCountForChannel(
                                    $user,
                                    $channel
                                ),

                        'last_message' =>
                            $lastMessage
                                ? $this
                                    ->messagePreview(
                                        $lastMessage
                                    )
                                : 'لا توجد رسائل بعد',

                        'last_message_at' =>
                            $lastMessage
                                ?->created_at
                                ?->diffForHumans(),
                    ];
                }
            )
            ->values();
    }


    private function messagePreview(
        ChatMessage $message
    ): string {
        $body = trim(
            (string) $message->message
        );

        if ($body !== '') {
            return mb_strimwidth(
                $body,
                0,
                55,
                '…'
            );
        }

        return '📎 مرفق';
    }

    private function serializeMessage(
        ChatMessage $message,
        User $viewer
    ): array {
        $message->loadMissing([
            'user.employee',
            'attachments',
            'replyTo.user.employee',
            'receipts.user.employee',
        ]);

        $senderName =
            $message->user
                ?->employee
                ?->full_name

            ?? $message->user
                ?->username

            ?? 'مستخدم';

        $senderAvatar =
            $message->user
                ?->employee
                ?->profile_image

            ?? $message->user
                ?->profile_image;

        $isMine =
            (int) $message->user_id
            === (int) $viewer->id;

        return [
            'id' =>
                $message->id,

            'channel_id' =>
                $message->channel_id,

            'mine' =>
                $isMine,

            'sender' => [
                'id' =>
                    $message->user_id,

                'name' =>
                    $senderName,

                'avatar' =>
                    $this->profileImageUrl(
                        $senderAvatar
                    ),
            ],

            'message' =>
                $message->message,

            'message_type' =>
                $message->message_type,

            'created_at' =>
                $message
                    ->created_at
                    ?->format(
                        'Y-m-d H:i'
                    ),

            'time' =>
                $message
                    ->created_at
                    ?->format('H:i'),

            'status' =>
                $isMine
                    ? $this
                        ->messageStatus(
                            $message
                        )
                    : null,

            'reply_to' =>
                $message->replyTo
                    ? [
                        'id' =>
                            $message
                                ->replyTo
                                ->id,

                        'sender' =>
                            $message
                                ->replyTo
                                ->user
                                ?->employee
                                ?->full_name

                            ?? $message
                                ->replyTo
                                ->user
                                ?->username

                            ?? 'مستخدم',

                        'message' =>
                            mb_strimwidth(
                                trim(
                                    (string)
                                    $message
                                        ->replyTo
                                        ->message
                                )
                                ?: '📎 مرفق',
                                0,
                                100,
                                '…'
                            ),
                    ]
                    : null,

            'attachments' =>
                $message
                    ->attachments
                    ->map(
                        fn (
                            ChatAttachment $attachment
                        ) => [
                            'id' =>
                                $attachment->id,

                            'name' =>
                                $attachment
                                    ->original_name,

                            'url' =>
                                $attachment->url,

                            'mime_type' =>
                                $attachment
                                    ->mime_type,

                            'size' =>
                                $attachment->size,

                            'is_image' =>
                                $attachment
                                    ->is_image,
                        ]
                    )
                    ->values(),
        ];
    }

    private function outgoingStatuses(
        User $viewer,
        ChatChannel $channel
    ) {
        return ChatMessage::query()
            ->where(
                'channel_id',
                $channel->id
            )
            ->where(
                'user_id',
                $viewer->id
            )
            ->with([
                'receipts.user.employee',
            ])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(
                fn (
                    ChatMessage $message
                ) => [
                    'message_id' =>
                        $message->id,

                    'status' =>
                        $this
                            ->messageStatus(
                                $message
                            ),
                ]
            )
            ->values();
    }

    private function messageStatus(
        ChatMessage $message
    ): array {
        $receipts =
            $message
                ->receipts;

        $recipientCount =
            $receipts->count();

        /*
        |--------------------------------------------------------------------------
        | Legacy messages
        |--------------------------------------------------------------------------
        |
        | الرسائل القديمة التي أُرسلت قبل إضافة receipts.
        |
        */

        if ($recipientCount === 0) {
            $legacyReadCount =
                ChatRead::query()
                    ->where(
                        'channel_id',
                        $message
                            ->channel_id
                    )
                    ->where(
                        'user_id',
                        '!=',
                        $message
                            ->user_id
                    )
                    ->where(
                        'last_read_message_id',
                        '>=',
                        $message->id
                    )
                    ->count();

            return [
                'key' =>
                    $legacyReadCount > 0
                        ? 'read'
                        : 'sent',

                'label' =>
                    $legacyReadCount > 0
                        ? '✓✓ مقروءة'
                        : '✓ مرسلة',

                'recipient_count' =>
                    0,

                'delivered_count' =>
                    $legacyReadCount,

                'read_count' =>
                    $legacyReadCount,

                'details' =>
                    [],

                'detail_text' =>
                    $legacyReadCount > 0
                        ? 'تمت قراءة هذه الرسالة قبل تفعيل تتبع المستلمين التفصيلي.'
                        : 'رسالة قديمة أو لم يكن هناك مستلم مسجل عند الإرسال.',
            ];
        }

        $deliveredCount =
            $receipts
                ->filter(
                    fn (
                        ChatMessageReceipt $receipt
                    ) =>
                        $receipt->delivered_at
                        || $receipt->read_at
                )
                ->count();

        $readCount =
            $receipts
                ->whereNotNull(
                    'read_at'
                )
                ->count();

        if (
            $readCount
            === $recipientCount
        ) {
            $key =
                'read';

            $label =
                '✓✓ تمت القراءة';
        } elseif (
            $readCount > 0
        ) {
            $key =
                'read';

            $label =
                '✓✓ قرأها '
                . $readCount
                . '/'
                . $recipientCount;
        } elseif (
            $deliveredCount
            === $recipientCount
        ) {
            $key =
                'delivered';

            $label =
                '✓✓ تم التسليم';
        } elseif (
            $deliveredCount > 0
        ) {
            $key =
                'delivered';

            $label =
                '✓✓ وصلت '
                . $deliveredCount
                . '/'
                . $recipientCount;
        } else {
            $key =
                'sent';

            $label =
                '✓ مرسلة';
        }

        $details =
            $receipts
                ->map(
                    function (
                        ChatMessageReceipt $receipt
                    ): array {
                        $name =
                            $receipt
                                ->user
                                ?->employee
                                ?->full_name

                            ?? $receipt
                                ->user
                                ?->username

                            ?? 'مستخدم';

                        if (
                            $receipt->read_at
                        ) {
                            $statusKey =
                                'read';

                            $statusLabel =
                                'تمت القراءة';
                        } elseif (
                            $receipt
                                ->delivered_at
                        ) {
                            $statusKey =
                                'delivered';

                            $statusLabel =
                                'تم التسليم';
                        } else {
                            $statusKey =
                                'pending';

                            $statusLabel =
                                'بانتظار التسليم';
                        }

                        return [
                            'user_id' =>
                                $receipt
                                    ->user_id,

                            'name' =>
                                $name,

                            'status_key' =>
                                $statusKey,

                            'status_label' =>
                                $statusLabel,

                            'delivered_at' =>
                                $receipt
                                    ->delivered_at
                                    ?->format(
                                        'Y-m-d H:i'
                                    ),

                            'read_at' =>
                                $receipt
                                    ->read_at
                                    ?->format(
                                        'Y-m-d H:i'
                                    ),
                        ];
                    }
                )
                ->values();

        $detailText =
            'المستلمون: '
            . $recipientCount
            . ' — تم التسليم: '
            . $deliveredCount
            . ' — تمت القراءة: '
            . $readCount;

        return [
            'key' =>
                $key,

            'label' =>
                $label,

            'recipient_count' =>
                $recipientCount,

            'delivered_count' =>
                $deliveredCount,

            'read_count' =>
                $readCount,

            'details' =>
                $details,

            'detail_text' =>
                $detailText,
        ];
    }

    private function profileImageUrl(
        ?string $path
    ): ?string {
        $path = trim(
            (string) $path
        );

        if ($path === '') {
            return null;
        }

        /*
         * رابط خارجي كامل.
         */
        if (
            str_starts_with(
                $path,
                'http://'
            )
            ||
            str_starts_with(
                $path,
                'https://'
            )
            ||
            str_starts_with(
                $path,
                '//'
            )
        ) {
            return $path;
        }

        $normalized =
            ltrim(
                $path,
                '/'
            );

        /*
         * بعض الصور محفوظة أصلًا كـ storage/...
         */
        if (
            str_starts_with(
                $normalized,
                'storage/'
            )
        ) {
            return asset(
                $normalized
            );
        }

        /*
         * لو المسار مباشر داخل public.
         */
        if (
            file_exists(
                public_path(
                    $normalized
                )
            )
        ) {
            return asset(
                $normalized
            );
        }

        /*
         * الشكل المعتاد لصور الموظفين:
         * profile_image = employees/xxx.jpg
         */
        return asset(
            'storage/'
            . $normalized
        );
    }


    private function ensureChatEnabled(): void
    {
        abort_unless(
            app(ModuleService::class)->isEnabled('chat'),
            404
        );
    }

    private function authorizeView(
        User $user,
        ChatChannel $channel
    ): void {
        abort_unless(
            $this->chatService
                ->canView(
                    $user,
                    $channel
                ),
            403,
            'ليس لديك صلاحية فتح هذه القناة.'
        );
    }
}
