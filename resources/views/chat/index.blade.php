@extends('layouts.app')

@section('title', 'المحادثات')
@section('page-title', 'المحادثات')

@php
    $chatTheme = [
        'backgroundColor' => \App\Models\SystemSetting::get(
            'chat_background_color',
            '#F5F6F8'
        ),

        'backgroundOverlay' => max(
            0,
            min(
                100,
                (int) \App\Models\SystemSetting::get(
                    'chat_background_overlay',
                    20
                )
            )
        ),

        'channelsBg' => \App\Models\SystemSetting::get(
            'chat_channels_bg',
            '#FFFFFF'
        ),

        'channelsHeaderBg' => \App\Models\SystemSetting::get(
            'chat_channels_header_bg',
            '#FFFFFF'
        ),

        'channelActiveBg' => \App\Models\SystemSetting::get(
            'chat_channel_active_bg',
            '#FFF4E8'
        ),

        'channelText' => \App\Models\SystemSetting::get(
            'chat_channel_text',
            '#172435'
        ),

        'channelMuted' => \App\Models\SystemSetting::get(
            'chat_channel_muted',
            '#687482'
        ),

        'conversationHeaderBg' => \App\Models\SystemSetting::get(
            'chat_conversation_header_bg',
            '#FFFFFF'
        ),

        'mineBg' => \App\Models\SystemSetting::get(
            'chat_message_mine_bg',
            '#C98516'
        ),

        'mineText' => \App\Models\SystemSetting::get(
            'chat_message_mine_text',
            '#FFFFFF'
        ),

        'otherBg' => \App\Models\SystemSetting::get(
            'chat_message_other_bg',
            '#FFFFFF'
        ),

        'otherText' => \App\Models\SystemSetting::get(
            'chat_message_other_text',
            '#172435'
        ),

        'composerBg' => \App\Models\SystemSetting::get(
            'chat_composer_bg',
            '#FFFFFF'
        ),

        'inputBg' => \App\Models\SystemSetting::get(
            'chat_input_bg',
            '#FFFFFF'
        ),

        'inputText' => \App\Models\SystemSetting::get(
            'chat_input_text',
            '#172435'
        ),

        'border' => \App\Models\SystemSetting::get(
            'chat_border',
            '#DDE2E7'
        ),

        'accent' => \App\Models\SystemSetting::get(
            'chat_accent',
            '#C98516'
        ),

        'sendBg' => \App\Models\SystemSetting::get(
            'chat_send_button_bg',
            '#C98516'
        ),

        'sendText' => \App\Models\SystemSetting::get(
            'chat_send_button_text',
            '#FFFFFF'
        ),

        'unreadBg' => \App\Models\SystemSetting::get(
            'chat_unread_badge_bg',
            '#E22929'
        ),

        'unreadText' => \App\Models\SystemSetting::get(
            'chat_unread_badge_text',
            '#FFFFFF'
        ),

        'bubbleRadius' => max(
            0,
            min(
                30,
                (int) \App\Models\SystemSetting::get(
                    'chat_bubble_radius',
                    14
                )
            )
        ),

        'preset' => \App\Models\SystemSetting::get(
            'chat_theme_preset',
            'whatsapp-soft'
        ),
    ];

    $chatBackgroundImagePath =
        \App\Models\SystemSetting::get(
            'chat_background_image'
        );

    $chatBackgroundImageUrl =
        $chatBackgroundImagePath
            ? asset($chatBackgroundImagePath)
            : null;

    $hexToRgb = static function (
        string $hex
    ): string {
        $hex = ltrim(
            trim($hex),
            '#'
        );

        if (strlen($hex) === 3) {
            $hex =
                $hex[0] . $hex[0]
                . $hex[1] . $hex[1]
                . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return '245,246,248';
        }

        return
            hexdec(substr($hex, 0, 2))
            . ','
            . hexdec(substr($hex, 2, 2))
            . ','
            . hexdec(substr($hex, 4, 2));
    };

    $chatBackgroundRgb =
        $hexToRgb(
            $chatTheme['backgroundColor']
        );

    $chatBackgroundOverlayAlpha =
        $chatTheme['backgroundOverlay']
        / 100;

    $selectedChannelMeta =
        $selectedChannel
            ? collect($channels)->first(
                fn ($channel) =>
                    (int) $channel['id']
                    === (int) $selectedChannel->id
            )
            : null;


    $canStartDirect =
        (bool) (
            $canStartDirect
            ?? false
        );

    $canSendChat =
        (bool) (
            $canSendSelectedChannel
            ?? false
        );

    $canAttachChat =
        (bool) (
            $canAttachSelectedChannel
            ?? false
        );

    $chatPresetClass = 'theme-' . preg_replace(
        '/[^a-z0-9\-]/i',
        '',
        (string) $chatTheme['preset']
    );
@endphp

@section('content')

<div class="chat-page {{ $chatPresetClass }}">

    @if(!$selectedChannel)

        <div class="chat-empty-state">
            <div class="chat-empty-icon">💬</div>
            <h2>لا توجد محادثات بعد</h2>
            <p>
                ستظهر هنا المحادثات المباشرة أو قنوات الفروع المسموح بها.
            </p>

            @if($canStartDirect)
                <button
                    type="button"
                    class="btn btn-gold"
                    id="chatStartDirectEmpty"
                    style="margin-top:1rem"
                >
                    بدء محادثة جديدة
                </button>
            @endif
        </div>

    @else

        <div class="chat-shell">

            <aside class="chat-channels-panel">

                <div class="chat-panel-header">
                    <div>
                        <h2>المحادثات</h2>
                        <p>
                            الفروع والمحادثات المباشرة
                        </p>
                    </div>

                    @if($canStartDirect)
                        <button
                            type="button"
                            class="chat-new-direct-btn"
                            id="chatStartDirectBtn"
                            title="بدء محادثة مباشرة"
                        >
                            <span>＋</span>
                            <span>محادثة</span>
                        </button>
                    @endif
                </div>

                <div class="chat-channel-list" id="chatChannelList">

                    @foreach($channels as $channel)

                        <a
                            href="{{ route('chat.show', $channel['id']) }}"
                            class="chat-channel-item {{ (int) $selectedChannel->id === (int) $channel['id'] ? 'active' : '' }}"
                            data-channel-id="{{ $channel['id'] }}"
                        >
                            <div
                                class="chat-channel-avatar"
                                title="{{ $channel['participant_name'] ?? $channel['location_name'] }}"
                            >
                                <span class="chat-avatar-fallback">
                                    {{
                                        mb_strtoupper(
                                            mb_substr(
                                                $channel['participant_name']
                                                    ?? $channel['location_name']
                                                    ?? 'م',
                                                0,
                                                1
                                            )
                                        )
                                    }}
                                </span>

                                @if(!empty($channel['participant_avatar']))
                                    <img
                                        src="{{ $channel['participant_avatar'] }}"
                                        alt="{{ $channel['participant_name'] ?? $channel['location_name'] }}"
                                        loading="lazy"
                                        onerror="this.remove()"
                                    >
                                @endif
                            </div>

                            <div class="chat-channel-copy">
                                <div class="chat-channel-title-row">
                                    <strong>
                                        {{ $channel['display_name'] ?? $channel['location_name'] }}
                                    </strong>

                                    @if($channel['unread_count'] > 0)
                                        <span class="chat-channel-badge">
                                            {{ $channel['unread_count'] > 99 ? '99+' : $channel['unread_count'] }}
                                        </span>
                                    @endif
                                </div>

                                @if(($channel['channel_type'] ?? 'branch') === 'direct')
                                    <div class="chat-channel-person">
                                        {{
                                            collect([
                                                $channel['participant_job_title'] ?? null,
                                                $channel['participant_location_name'] ?? null,
                                            ])->filter()->implode(' — ')
                                            ?: 'محادثة مباشرة'
                                        }}
                                    </div>
                                @elseif(!empty($channel['participant_name']))
                                    <div class="chat-channel-person">
                                        مدير الفرع:
                                        {{ $channel['participant_name'] }}
                                    </div>
                                @endif

                                <div class="chat-channel-preview">
                                    {{ $channel['last_message'] }}
                                </div>

                                @if($channel['last_message_at'])
                                    <small>
                                        {{ $channel['last_message_at'] }}
                                    </small>
                                @endif
                            </div>
                        </a>

                    @endforeach

                </div>

            </aside>


            <section class="chat-conversation">

                <header class="chat-conversation-header">

                    <div
                        class="chat-conversation-avatar"
                        title="{{ $selectedChannelMeta['participant_name'] ?? ($selectedChannel->location?->name ?? 'الفرع') }}"
                    >
                        <span class="chat-avatar-fallback">
                            {{
                                mb_strtoupper(
                                    mb_substr(
                                        $selectedChannelMeta['participant_name']
                                            ?? $selectedChannel->location?->name
                                            ?? 'م',
                                        0,
                                        1
                                    )
                                )
                            }}
                        </span>

                        @if(!empty($selectedChannelMeta['participant_avatar']))
                            <img
                                src="{{ $selectedChannelMeta['participant_avatar'] }}"
                                alt="{{ $selectedChannelMeta['participant_name'] ?? ($selectedChannel->location?->name ?? 'الفرع') }}"
                                loading="eager"
                                onerror="this.remove()"
                            >
                        @endif
                    </div>

                    <div>
                        <h2>
                            @if(($selectedChannelMeta['channel_type'] ?? 'branch') === 'direct')
                                {{ $selectedChannelMeta['participant_name'] ?? 'محادثة مباشرة' }}
                            @else
                                {{ $selectedChannel->location?->name ?? $selectedChannel->name }}
                            @endif
                        </h2>

                        <p>
                            @if(($selectedChannelMeta['channel_type'] ?? 'branch') === 'direct')
                                {{
                                    collect([
                                        $selectedChannelMeta['participant_job_title'] ?? null,
                                        $selectedChannelMeta['participant_location_name'] ?? null,
                                    ])->filter()->implode(' — ')
                                    ?: 'محادثة مباشرة'
                                }}
                            @elseif(!empty($selectedChannelMeta['participant_name']))
                                مدير الفرع:
                                <strong>
                                    {{ $selectedChannelMeta['participant_name'] }}
                                </strong>
                            @else
                                قناة مباشرة بين الإدارة ومدير الفرع
                            @endif
                        </p>

                        <div class="chat-header-badges">
                            <span class="chat-header-badge is-online">محادثة داخلية آمنة</span>
                            <span class="chat-header-badge">{{ $channels ? count($channels) : 0 }} قناة</span>
                        </div>
                    </div>

                </header>


                <div
                    class="chat-messages"
                    id="chatMessages"
                >
                    <div
                        class="chat-loading"
                        id="chatLoading"
                    >
                        جاري تحميل الرسائل...
                    </div>
                </div>


                <div class="chat-bottom-area" id="chatBottomArea">
                <div
                    class="chat-reply-box"
                    id="chatReplyBox"
                    hidden
                >
                    <div>
                        <strong id="chatReplySender"></strong>
                        <span id="chatReplyText"></span>
                    </div>

                    <button
                        type="button"
                        id="cancelReply"
                        aria-label="إلغاء الرد"
                    >
                        ×
                    </button>
                </div>


                <div
                    class="chat-file-preview"
                    id="chatFilePreview"
                    hidden
                ></div>

                <div
                    class="chat-emoji-picker"
                    id="chatEmojiPicker"
                    hidden
                >
                    <div class="chat-emoji-picker-head">
                        <strong>الرموز التعبيرية</strong>
                        <button
                            type="button"
                            id="chatEmojiClose"
                            aria-label="إغلاق الرموز التعبيرية"
                        >×</button>
                    </div>

                    <div
                        class="chat-emoji-tabs"
                        id="chatEmojiTabs"
                    ></div>

                    <div
                        class="chat-emoji-grid"
                        id="chatEmojiGrid"
                    ></div>
                </div>


                <form
                    class="chat-composer"
                    id="chatComposer"
                    enctype="multipart/form-data"
                >

                    @csrf

                    <input
                        type="hidden"
                        name="reply_to_id"
                        id="replyToId"
                    >

                    @if($canAttachChat)
                    <label
                        class="chat-attach-btn"
                        title="إرفاق ملف"
                    >
                        <input
                            type="file"
                            name="attachments[]"
                            id="chatAttachments"
                            accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt"
                            multiple
                            hidden
                        >

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"
                            />
                        </svg>
                    </label>
                    @endif

                    <button
                        type="button"
                        class="chat-emoji-btn"
                        id="chatEmojiBtn"
                        title="إضافة رمز تعبيري"
                        aria-label="إضافة رمز تعبيري"
                        aria-expanded="false"
                    >
                        <span aria-hidden="true">😊</span>
                    </button>

                    <textarea
                        name="message"
                        id="chatMessageInput"
                        rows="1"
                        maxlength="5000"
                        placeholder="{{ $canSendChat ? 'اكتب رسالتك...' : 'لديك صلاحية عرض المحادثة فقط' }}"
                        @disabled(!$canSendChat)
                    ></textarea>

                    <button
                        type="submit"
                        class="chat-send-btn"
                        id="chatSendBtn"
                        title="{{ $canSendChat ? 'إرسال' : 'ليس لديك صلاحية الإرسال' }}"
                        @disabled(!$canSendChat)
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <line
                                x1="22"
                                y1="2"
                                x2="11"
                                y2="13"
                            />
                            <polygon
                                points="22 2 15 22 11 13 2 9 22 2"
                            />
                        </svg>
                    </button>

                </form>

                <div
                    class="chat-error"
                    id="chatError"
                    hidden
                ></div>
                </div>

            </section>

        </div>

    @endif

</div>

@if($canStartDirect)
    <div
        class="chat-user-modal"
        id="chatUserModal"
        hidden
    >
        <div class="chat-user-modal-card">
            <div class="chat-user-modal-head">
                <div>
                    <h3>محادثة مباشرة جديدة</h3>
                    <p>اختر المستخدم المسموح لك بمراسلته حسب نطاقك الإداري.</p>
                </div>

                <button
                    type="button"
                    id="chatUserModalClose"
                    aria-label="إغلاق"
                >
                    ×
                </button>
            </div>

            <div class="chat-user-search-wrap">
                <input
                    type="search"
                    id="chatUserSearch"
                    class="form-input"
                    placeholder="ابحث بالاسم، اسم المستخدم، الوظيفة أو الهاتف..."
                    autocomplete="off"
                >
            </div>

            <div
                class="chat-user-results"
                id="chatUserResults"
            >
                <div class="chat-user-loading">
                    جاري تحميل المستخدمين...
                </div>
            </div>
        </div>
    </div>
@endif

@endsection

@if(!$selectedChannel && $canStartDirect)
@push('scripts')
<script>
document.addEventListener(
    'DOMContentLoaded',
    () => {
        const modal =
            document.getElementById(
                'chatUserModal'
            );

        const openButton =
            document.getElementById(
                'chatStartDirectEmpty'
            );

        const closeButton =
            document.getElementById(
                'chatUserModalClose'
            );

        const searchInput =
            document.getElementById(
                'chatUserSearch'
            );

        const results =
            document.getElementById(
                'chatUserResults'
            );

        const csrfToken =
            document.querySelector(
                'meta[name="csrf-token"]'
            )?.content || '';

        const usersUrl =
            @json(route('chat.users'));

        const startUrlTemplate =
            @json(
                route(
                    'chat.direct.start',
                    ['user' => '__USER__']
                )
            );

        let searchTimer =
            null;


        const escapeHtml =
            (value) => {
                const div =
                    document.createElement(
                        'div'
                    );

                div.textContent =
                    value ?? '';

                return div.innerHTML;
            };


        const renderUsers =
            (users) => {
                if (!results) {
                    return;
                }

                if (
                    !Array.isArray(users)
                    || users.length === 0
                ) {
                    results.innerHTML =
                        `
                            <div class="chat-user-empty">
                                لا يوجد مستخدمون ضمن نطاقك الإداري.
                            </div>
                        `;

                    return;
                }

                results.innerHTML =
                    users
                        .map(
                            (user) => {
                                const letter =
                                    escapeHtml(
                                        (
                                            user.name
                                            || 'م'
                                        )
                                        .trim()
                                        .charAt(0)
                                        .toUpperCase()
                                    );

                                const avatar =
                                    user.avatar
                                        ? `
                                            <img
                                                src="${escapeHtml(user.avatar)}"
                                                alt="${escapeHtml(user.name)}"
                                                onerror="this.remove()"
                                            >
                                        `
                                        : '';

                                const subtitle =
                                    [
                                        user.job_title,
                                        user.location_name,
                                        user.username
                                            ? `@${user.username}`
                                            : null,
                                    ]
                                    .filter(Boolean)
                                    .join(' — ');

                                return `
                                    <button
                                        type="button"
                                        class="chat-user-row"
                                        data-direct-user-id="${user.id}"
                                        data-direct-channel-id="${user.channel_id || ''}"
                                    >
                                        <span class="chat-user-avatar">
                                            <span>${letter}</span>
                                            ${avatar}
                                        </span>

                                        <span class="chat-user-info">
                                            <strong>${escapeHtml(user.name)}</strong>
                                            <span>${escapeHtml(subtitle)}</span>
                                        </span>

                                        ${
                                            user.channel_id
                                                ? '<span class="chat-user-existing">موجودة</span>'
                                                : ''
                                        }
                                    </button>
                                `;
                            }
                        )
                        .join('');
            };


        const loadUsers =
            async (
                search = ''
            ) => {
                results.innerHTML =
                    `
                        <div class="chat-user-loading">
                            جاري تحميل المستخدمين...
                        </div>
                    `;

                try {
                    const url =
                        new URL(
                            usersUrl,
                            window.location.origin
                        );

                    if (search.trim()) {
                        url.searchParams.set(
                            'q',
                            search.trim()
                        );
                    }

                    const response =
                        await fetch(
                            url,
                            {
                                headers: {
                                    'Accept':
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },

                                credentials:
                                    'same-origin',

                                cache:
                                    'no-store',
                            }
                        );

                    const data =
                        await response.json();

                    if (!response.ok) {
                        throw new Error(
                            data.message
                            || 'تعذر تحميل المستخدمين.'
                        );
                    }

                    renderUsers(
                        data.users || []
                    );
                } catch (error) {
                    results.innerHTML =
                        `
                            <div class="chat-user-empty">
                                ${escapeHtml(
                                    error.message
                                    || 'تعذر تحميل المستخدمين.'
                                )}
                            </div>
                        `;
                }
            };


        openButton?.addEventListener(
            'click',
            () => {
                modal.hidden =
                    false;

                searchInput?.focus();

                loadUsers(
                    searchInput?.value
                    || ''
                );
            }
        );


        closeButton?.addEventListener(
            'click',
            () => {
                modal.hidden =
                    true;
            }
        );


        modal?.addEventListener(
            'click',
            (event) => {
                if (
                    event.target
                    === modal
                ) {
                    modal.hidden =
                        true;
                }
            }
        );


        searchInput?.addEventListener(
            'input',
            () => {
                window.clearTimeout(
                    searchTimer
                );

                searchTimer =
                    window.setTimeout(
                        () =>
                            loadUsers(
                                searchInput.value
                            ),
                        250
                    );
            }
        );


        results?.addEventListener(
            'click',
            async (event) => {
                const row =
                    event.target.closest(
                        '[data-direct-user-id]'
                    );

                if (!row) {
                    return;
                }

                const existingId =
                    Number(
                        row.dataset
                            .directChannelId
                        || 0
                    );

                if (existingId > 0) {
                    window.location.href =
                        `/chat/${existingId}`;

                    return;
                }

                const userId =
                    Number(
                        row.dataset
                            .directUserId
                    );

                if (!userId) {
                    return;
                }

                row.disabled =
                    true;

                try {
                    const response =
                        await fetch(
                            startUrlTemplate
                                .replace(
                                    '__USER__',
                                    String(userId)
                                ),
                            {
                                method:
                                    'POST',

                                headers: {
                                    'Accept':
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',

                                    'X-CSRF-TOKEN':
                                        csrfToken,
                                },

                                credentials:
                                    'same-origin',
                            }
                        );

                    const data =
                        await response.json();

                    if (!response.ok) {
                        throw new Error(
                            data.message
                            || 'تعذر بدء المحادثة.'
                        );
                    }

                    window.location.href =
                        data.url;
                } catch (error) {
                    row.disabled =
                        false;

                    results.insertAdjacentHTML(
                        'afterbegin',
                        `
                            <div class="chat-user-empty">
                                ${escapeHtml(
                                    error.message
                                    || 'تعذر بدء المحادثة.'
                                )}
                            </div>
                        `
                    );
                }
            }
        );
    }
);
</script>
@endpush
@endif


@push('styles')

<style>

    .chat-page {
        --chat-background: {{ $chatTheme['backgroundColor'] }};
        --chat-channels-bg: {{ $chatTheme['channelsBg'] }};
        --chat-channels-header-bg: {{ $chatTheme['channelsHeaderBg'] }};
        --chat-channel-active-bg: {{ $chatTheme['channelActiveBg'] }};
        --chat-channel-text: {{ $chatTheme['channelText'] }};
        --chat-channel-muted: {{ $chatTheme['channelMuted'] }};
        --chat-conversation-header-bg: {{ $chatTheme['conversationHeaderBg'] }};
        --chat-mine-bg: {{ $chatTheme['mineBg'] }};
        --chat-mine-text: {{ $chatTheme['mineText'] }};
        --chat-other-bg: {{ $chatTheme['otherBg'] }};
        --chat-other-text: {{ $chatTheme['otherText'] }};
        --chat-composer-bg: {{ $chatTheme['composerBg'] }};
        --chat-input-bg: {{ $chatTheme['inputBg'] }};
        --chat-input-text: {{ $chatTheme['inputText'] }};
        --chat-border: {{ $chatTheme['border'] }};
        --chat-accent: {{ $chatTheme['accent'] }};
        --chat-send-bg: {{ $chatTheme['sendBg'] }};
        --chat-send-text: {{ $chatTheme['sendText'] }};
        --chat-unread-bg: {{ $chatTheme['unreadBg'] }};
        --chat-unread-text: {{ $chatTheme['unreadText'] }};
        --chat-bubble-radius: {{ $chatTheme['bubbleRadius'] }}px;

        min-height:
            calc(100vh - 130px);
    }

    .chat-shell {
        display: grid;

        grid-template-columns:
            330px minmax(0, 1fr);

        /*
         * يتم حساب هذا المتغير بالجافاسكربت من مكان البطاقة الفعلي
         * داخل الصفحة، حتى لا تنزل منطقة الكتابة تحت الشاشة.
         */
        height:
            var(
                --chat-shell-height,
                calc(100dvh - 180px)
            );

        min-height:
            0;

        overflow:
            hidden;

        background:
            var(--chat-channels-bg);

        border:
            1px solid
            var(--chat-border);

        border-radius:
            var(--theme-radius, 14px);

        box-shadow:
            0 10px 34px
            rgba(0, 0, 0, .06);
    }


    /*
    |--------------------------------------------------------------------------
    | Channels
    |--------------------------------------------------------------------------
    */

    .chat-channels-panel {
        min-width: 0;

        border-left:
            1px solid
            var(--chat-border);

        background:
            var(--chat-channels-bg);
    }

    .chat-panel-header {
        min-height: 82px;

        display: flex;

        align-items: center;

        padding:
            15px 18px;

        border-bottom:
            1px solid
            var(--chat-border);
    }

    .chat-panel-header h2,
    .chat-conversation-header h2 {
        margin: 0;

        color:
            var(--chat-channel-text);

        font-size:
            1rem;

        font-weight:
            800;
    }

    .chat-panel-header p,
    .chat-conversation-header p {
        margin:
            3px 0 0;

        color:
            var(--chat-channel-muted);

        font-size:
            .73rem;
    }

    .chat-channel-list {
        height:
            calc(100% - 82px);

        overflow-y:
            auto;

        padding:
            8px;
    }

    .chat-channel-item {
        display: flex;

        gap:
            10px;

        align-items:
            center;

        padding:
            11px;

        margin-bottom:
            5px;

        color:
            var(--chat-channel-text);

        text-decoration:
            none;

        border:
            1px solid transparent;

        border-radius:
            12px;

        transition:
            .18s ease;
    }

    .chat-channel-item:hover {
        background:
            color-mix(
                in srgb,
                var(--theme-accent, var(--gold)) 7%,
                transparent
            );
    }

    .chat-channel-item.active {
        background:
            var(--chat-channel-active-bg);

        border-color:
            color-mix(
                in srgb,
                var(--chat-accent) 34%,
                var(--chat-border)
            );
    }

    .chat-channel-avatar,
    .chat-conversation-avatar {
        width: 42px;
        height: 42px;

        flex:
            0 0 42px;

        display: flex;

        align-items: center;

        justify-content: center;

        color:
            #fff;

        background:
            var(--theme-primary, var(--navy));

        border-radius:
            50%;

        font-weight:
            800;
    }

    .chat-channel-avatar,
    .chat-conversation-avatar {
        position: relative;
        overflow: hidden;
        isolation: isolate;
    }

    .chat-channel-avatar img,
    .chat-conversation-avatar img {
        position: absolute;
        inset: 0;
        z-index: 2;

        width: 100%;
        height: 100%;

        object-fit: cover;
        object-position: center;

        border-radius: inherit;
    }

    .chat-avatar-fallback {
        position: absolute;
        inset: 0;
        z-index: 1;

        display: flex;
        align-items: center;
        justify-content: center;

        color: inherit;
        background: inherit;

        font-weight: 800;
    }

    .chat-channel-copy {
        min-width: 0;

        flex: 1;
    }

    .chat-channel-person {
        margin-top: 3px;

        overflow: hidden;

        color:
            var(--chat-accent);

        white-space: nowrap;

        text-overflow: ellipsis;

        font-size: .66rem;

        font-weight: 700;
    }

    .chat-channel-title-row {
        display: flex;

        align-items: center;

        gap: 8px;

        justify-content:
            space-between;
    }

    .chat-channel-title-row strong {
        min-width: 0;

        overflow: hidden;

        white-space: nowrap;

        text-overflow:
            ellipsis;

        font-size:
            .82rem;
    }

    .chat-channel-preview {
        margin-top:
            4px;

        overflow: hidden;

        color:
            var(--chat-channel-muted);

        white-space:
            nowrap;

        text-overflow:
            ellipsis;

        font-size:
            .7rem;
    }

    .chat-channel-copy small {
        display: block;

        margin-top:
            2px;

        color:
            var(--chat-channel-muted);

        font-size:
            .62rem;
    }

    .chat-channel-badge {
        min-width: 20px;
        height: 20px;

        display: inline-flex;

        align-items: center;

        justify-content: center;

        padding:
            0 6px;

        color:
            #fff;

        background:
            var(--chat-unread-bg);

        border-radius:
            999px;

        font-size:
            .62rem;

        font-weight:
            800;
    }


    /*
    |--------------------------------------------------------------------------
    | Conversation
    |--------------------------------------------------------------------------
    */

    .chat-conversation {
        min-width: 0;

        display: grid;

        grid-template-rows:
            auto minmax(0, 1fr) auto auto auto;

        background:
            var(--chat-background);
    }

    .chat-conversation-header {
        min-height:
            82px;

        display:
            flex;

        align-items:
            center;

        gap:
            11px;

        padding:
            13px 18px;

        background:
            var(--theme-surface, var(--surface));

        border-bottom:
            1px solid
            var(--chat-border);
    }

    .chat-messages {
        overflow-y:
            auto;

        padding:
            20px;

        scroll-behavior:
            smooth;
    }

    .chat-loading {
        padding:
            30px;

        text-align:
            center;

        color:
            var(--chat-channel-muted);

        font-size:
            .8rem;
    }

    .chat-message-row {
        display:
            flex;

        margin-bottom:
            12px;
    }

    .chat-message-row.mine {
        justify-content:
            flex-start;
    }

    .chat-message-row.other {
        justify-content:
            flex-end;
    }

    .chat-message {
        position:
            relative;

        width:
            fit-content;

        max-width:
            min(74%, 620px);

        padding:
            9px 11px 7px;

        color:
            var(--chat-channel-text);

        background:
            var(--chat-channels-bg);

        border:
            1px solid
            var(--chat-border);

        border-radius:
            14px;

        box-shadow:
            0 4px 16px
            rgba(0, 0, 0, .04);
    }

    .chat-message-row.mine .chat-message {
        color:
            var(--chat-mine-text);

        background:
            var(--chat-mine-bg);

        border-color:
            var(--chat-mine-bg);
    }

    .chat-message-sender {
        margin-bottom:
            4px;

        color:
            var(--chat-accent);

        font-size:
            .66rem;

        font-weight:
            800;
    }

    .chat-message-row.mine
    .chat-message-sender {
        color:
            rgba(255, 255, 255, .75);
    }

    .chat-message-body {
        white-space:
            pre-wrap;

        overflow-wrap:
            anywhere;

        font-size:
            .82rem;

        line-height:
            1.7;
    }

    .chat-message-meta {
        display:
            flex;

        align-items:
            center;

        justify-content:
            flex-end;

        gap:
            5px;

        margin-top:
            5px;

        color:
            var(--chat-channel-muted);

        font-size:
            .58rem;
    }

    .chat-message-row.mine
    .chat-message-meta {
        color:
            rgba(255, 255, 255, .64);
    }

    .chat-seen {
        font-weight:
            700;
    }

    .chat-reply-snippet {
        margin-bottom:
            7px;

        padding:
            6px 8px;

        background:
            rgba(0, 0, 0, .06);

        border-right:
            3px solid
            var(--theme-accent, var(--gold));

        border-radius:
            7px;

        font-size:
            .66rem;
    }

    .chat-message-row.mine
    .chat-reply-snippet {
        background:
            rgba(255, 255, 255, .10);
    }

    .chat-reply-snippet strong,
    .chat-reply-snippet span {
        display:
            block;
    }

    .chat-reply-snippet span {
        margin-top:
            2px;

        opacity:
            .8;
    }

    .chat-attachments {
        display:
            grid;

        gap:
            7px;

        margin-top:
            7px;
    }

    .chat-image-link img {
        display:
            block;

        max-width:
            320px;

        max-height:
            240px;

        object-fit:
            cover;

        border-radius:
            10px;
    }

    .chat-file-link {
        display:
            flex;

        align-items:
            center;

        gap:
            8px;

        padding:
            7px 9px;

        color:
            inherit;

        background:
            rgba(0, 0, 0, .05);

        border-radius:
            9px;

        text-decoration:
            none;

        font-size:
            .72rem;
    }

    .chat-message-row.mine
    .chat-file-link {
        background:
            rgba(255, 255, 255, .10);
    }

    .chat-reply-action {
        position:
            absolute;

        top:
            5px;

        left:
            5px;

        display:
            none;

        padding:
            3px 5px;

        color:
            inherit;

        background:
            rgba(0, 0, 0, .08);

        border:
            0;

        border-radius:
            6px;

        cursor:
            pointer;

        font-size:
            .6rem;
    }

    .chat-message:hover
    .chat-reply-action {
        display:
            block;
    }


    /*
    |--------------------------------------------------------------------------
    | Reply / files
    |--------------------------------------------------------------------------
    */

    .chat-reply-box,
    .chat-file-preview {
        margin:
            0 14px 7px;

        padding:
            8px 10px;

        background:
            var(--chat-channels-bg);

        border:
            1px solid
            var(--chat-border);

        border-radius:
            10px;
    }

    .chat-reply-box {
        display:
            flex;

        align-items:
            center;

        justify-content:
            space-between;

        gap:
            10px;
    }

    .chat-reply-box[hidden],
    .chat-file-preview[hidden] {
        display:
            none;
    }

    .chat-reply-box strong,
    .chat-reply-box span {
        display:
            block;

        font-size:
            .68rem;
    }

    .chat-reply-box strong {
        color:
            var(--chat-accent);
    }

    .chat-reply-box span {
        margin-top:
            2px;

        color:
            var(--chat-channel-muted);
    }

    .chat-reply-box button {
        width: 27px;
        height: 27px;

        color:
            var(--theme-danger, var(--danger));

        background:
            transparent;

        border:
            0;

        cursor:
            pointer;

        font-size:
            1.15rem;
    }

    .chat-file-preview {
        color:
            var(--chat-channel-muted);

        font-size:
            .7rem;
    }


    /*
    |--------------------------------------------------------------------------
    | Composer
    |--------------------------------------------------------------------------
    */

    .chat-composer {
        display:
            flex;

        align-items:
            flex-end;

        gap:
            8px;

        padding:
            12px 14px;

        background:
            var(--chat-composer-bg);

        border-top:
            1px solid
            var(--chat-border);
    }

    .chat-composer textarea {
        min-height:
            44px;

        max-height:
            140px;

        flex:
            1;

        resize:
            none;

        padding:
            10px 12px;

        color:
            var(--chat-channel-text);

        background:
            var(--theme-bg, var(--bg));

        border:
            1px solid
            var(--theme-border, var(--border));

        border-radius:
            11px;

        outline:
            none;

        font-family:
            inherit;

        font-size:
            .8rem;

        line-height:
            1.6;
    }

    .chat-composer textarea:focus {
        border-color:
            var(--chat-accent);

        box-shadow:
            0 0 0 3px
            color-mix(
                in srgb,
                var(--theme-accent, var(--gold)) 13%,
                transparent
            );
    }

    .chat-attach-btn,
    .chat-send-btn {
        width: 44px;
        height: 44px;

        flex:
            0 0 44px;

        display:
            inline-flex;

        align-items:
            center;

        justify-content:
            center;

        border-radius:
            11px;

        cursor:
            pointer;
    }

    .chat-attach-btn {
        color:
            var(--chat-accent);

        background:
            color-mix(
                in srgb,
                var(--theme-accent, var(--gold)) 8%,
                transparent
            );

        border:
            1px solid
            color-mix(
                in srgb,
                var(--theme-accent, var(--gold)) 25%,
                transparent
            );
    }

    .chat-send-btn {
        color:
            var(--chat-send-text);

        background:
            var(--chat-send-bg);

        border:
            1px solid
            var(--chat-send-bg);
    }

    .chat-attach-btn svg,
    .chat-send-btn svg {
        width: 19px;
        height: 19px;
    }

    .chat-send-btn:disabled {
        opacity:
            .55;

        cursor:
            wait;
    }

    .chat-error {
        margin:
            0 14px 12px;

        padding:
            8px 10px;

        color:
            var(--theme-danger, var(--danger));

        background:
            color-mix(
                in srgb,
                var(--theme-danger, var(--danger)) 8%,
                transparent
            );

        border-radius:
            8px;

        font-size:
            .7rem;
    }


    /*
    |--------------------------------------------------------------------------
    | Empty
    |--------------------------------------------------------------------------
    */

    .chat-empty-state {
        min-height:
            460px;

        display:
            flex;

        flex-direction:
            column;

        align-items:
            center;

        justify-content:
            center;

        text-align:
            center;

        color:
            var(--chat-channel-muted);

        background:
            var(--chat-channels-bg);

        border:
            1px solid
            var(--chat-border);

        border-radius:
            var(--theme-radius, 14px);
    }

    .chat-empty-icon {
        margin-bottom:
            10px;

        font-size:
            2.5rem;
    }

    .chat-empty-state h2 {
        margin:
            0 0 6px;

        color:
            var(--chat-channel-text);
    }

    .chat-empty-state p {
        margin: 0;
    }


    /*
    |--------------------------------------------------------------------------
    | Responsive
    |--------------------------------------------------------------------------
    */

    @media (
        max-width: 900px
    ) {

        .chat-shell {
            grid-template-columns:
                1fr;

            height:
                auto;

            min-height:
                0;
        }

        .chat-channels-panel {
            border-left:
                0;

            border-bottom:
                1px solid
                var(--theme-border, var(--border));
        }

        .chat-channel-list {
            max-height:
                260px;
        }

        .chat-conversation {
            min-height:
                650px;
        }

        .chat-message {
            max-width:
                88%;
        }
    }


    /* =========================================
       Final Chat Theme Overrides
    ========================================= */

    .chat-shell {
        background:
            var(--chat-channels-bg) !important;

        border-color:
            var(--chat-border) !important;
    }

    .chat-channels-panel {
        background:
            var(--chat-channels-bg) !important;

        border-color:
            var(--chat-border) !important;
    }

    .chat-panel-header {
        background:
            var(--chat-channels-header-bg) !important;

        border-color:
            var(--chat-border) !important;
    }

    .chat-panel-header h2,
    .chat-channel-title-row strong {
        color:
            var(--chat-channel-text) !important;
    }

    .chat-panel-header p,
    .chat-channel-preview,
    .chat-channel-copy small {
        color:
            var(--chat-channel-muted) !important;
    }

    .chat-channel-item {
        color:
            var(--chat-channel-text) !important;
    }

    .chat-channel-item.active {
        background:
            var(--chat-channel-active-bg) !important;

        border-color:
            color-mix(
                in srgb,
                var(--chat-accent) 35%,
                var(--chat-border)
            ) !important;
    }

    .chat-channel-avatar,
    .chat-conversation-avatar {
        background:
            var(--chat-accent) !important;

        color:
            #FFFFFF !important;
    }

    .chat-channel-badge {
        background:
            var(--chat-unread-bg) !important;

        color:
            var(--chat-unread-text) !important;
    }

    .chat-conversation {
        background:
            var(--chat-background) !important;
    }

    .chat-conversation-header {
        background:
            var(--chat-conversation-header-bg) !important;

        border-color:
            var(--chat-border) !important;
    }

    .chat-conversation-header h2 {
        color:
            var(--chat-channel-text) !important;
    }

    .chat-conversation-header p {
        color:
            var(--chat-channel-muted) !important;
    }

    .chat-messages {
        background-color:
            var(--chat-background);

        @if($chatBackgroundImageUrl)
            background-image:
                linear-gradient(
                    rgba(
                        {{ $chatBackgroundRgb }},
                        {{ $chatBackgroundOverlayAlpha }}
                    ),
                    rgba(
                        {{ $chatBackgroundRgb }},
                        {{ $chatBackgroundOverlayAlpha }}
                    )
                ),
                url('{{ $chatBackgroundImageUrl }}');

            background-position:
                center;

            background-size:
                cover;

            background-repeat:
                no-repeat;

            background-attachment:
                local;
        @else
            background-image:
                none;
        @endif
    }

    .chat-message {
        background:
            var(--chat-other-bg) !important;

        color:
            var(--chat-other-text) !important;

        border-color:
            var(--chat-border) !important;

        border-radius:
            var(--chat-bubble-radius) !important;
    }

    .chat-message-row.mine
    .chat-message {
        background:
            var(--chat-mine-bg) !important;

        color:
            var(--chat-mine-text) !important;

        border-color:
            var(--chat-mine-bg) !important;
    }

    .chat-message-sender,
    .chat-reply-snippet strong {
        color:
            var(--chat-accent) !important;
    }

    .chat-message-row.mine
    .chat-message-sender,
    .chat-message-row.mine
    .chat-message-meta {
        color:
            color-mix(
                in srgb,
                var(--chat-mine-text) 72%,
                transparent
            ) !important;
    }

    .chat-message-meta {
        color:
            var(--chat-channel-muted) !important;
    }

    .chat-reply-snippet {
        border-right-color:
            var(--chat-accent) !important;
    }

    .chat-reply-box,
    .chat-file-preview {
        background:
            var(--chat-composer-bg) !important;

        color:
            var(--chat-channel-text) !important;

        border-color:
            var(--chat-border) !important;
    }

    .chat-reply-box span,
    .chat-file-preview {
        color:
            var(--chat-channel-muted) !important;
    }

    .chat-composer {
        background:
            var(--chat-composer-bg) !important;

        border-color:
            var(--chat-border) !important;
    }

    .chat-composer textarea {
        background:
            var(--chat-input-bg) !important;

        color:
            var(--chat-input-text) !important;

        border-color:
            var(--chat-border) !important;
    }

    .chat-composer textarea::placeholder {
        color:
            color-mix(
                in srgb,
                var(--chat-input-text) 45%,
                transparent
            ) !important;
    }

    .chat-composer textarea:focus {
        border-color:
            var(--chat-accent) !important;

        box-shadow:
            0 0 0 3px
            color-mix(
                in srgb,
                var(--chat-accent) 15%,
                transparent
            ) !important;
    }

    .chat-attach-btn {
        color:
            var(--chat-accent) !important;

        background:
            color-mix(
                in srgb,
                var(--chat-accent) 8%,
                transparent
            ) !important;

        border-color:
            color-mix(
                in srgb,
                var(--chat-accent) 28%,
                var(--chat-border)
            ) !important;
    }

    .chat-send-btn {
        background:
            var(--chat-send-bg) !important;

        color:
            var(--chat-send-text) !important;

        border-color:
            var(--chat-send-bg) !important;
    }



    /* =========================================
       Professional WhatsApp-Like Upgrade
    ========================================= */

    .chat-page {
        --chat-shell-shadow: 0 18px 45px rgba(15, 23, 42, .08);
        --chat-soft-shadow: 0 6px 20px rgba(15, 23, 42, .05);
        --chat-pattern-opacity: .06;
        --chat-pattern-color: rgba(255,255,255,.7);
    }

    .chat-page.theme-whatsapp-soft {
        --chat-pattern-opacity: .10;
        --chat-pattern-color: rgba(255,255,255,.7);
    }

    .chat-page.theme-emerald {
        --chat-pattern-opacity: .07;
        --chat-pattern-color: rgba(255,255,255,.65);
    }

    .chat-page.theme-midnight {
        --chat-pattern-opacity: .05;
        --chat-pattern-color: rgba(255,255,255,.20);
    }

    .chat-page.theme-rose {
        --chat-pattern-opacity: .08;
        --chat-pattern-color: rgba(255,255,255,.55);
    }

    .chat-shell {
        border-radius: 22px !important;
        box-shadow: var(--chat-shell-shadow) !important;
        backdrop-filter: blur(4px);
    }

    .chat-channels-panel {
        position: relative;
    }

    .chat-panel-header,
    .chat-conversation-header {
        min-height: 88px;
    }

    .chat-panel-header {
        background: linear-gradient(180deg, color-mix(in srgb, var(--chat-channels-header-bg) 94%, #ffffff 6%), var(--chat-channels-header-bg)) !important;
    }

    .chat-conversation-header {
        justify-content: flex-start;
        background: linear-gradient(180deg, color-mix(in srgb, var(--chat-conversation-header-bg) 92%, #ffffff 8%), var(--chat-conversation-header-bg)) !important;
        box-shadow: inset 0 -1px 0 var(--chat-border);
    }

    .chat-conversation-header > div:nth-child(2) {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }

    .chat-header-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 2px;
    }

    .chat-header-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 10px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--chat-accent) 8%, #ffffff 92%);
        color: var(--chat-channel-text);
        border: 1px solid color-mix(in srgb, var(--chat-accent) 22%, var(--chat-border));
        font-size: .68rem;
        font-weight: 700;
    }

    .chat-header-badge.is-online::before {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #22c55e;
        box-shadow: 0 0 0 3px rgba(34, 197, 94, .16);
    }

    .chat-channel-list {
        padding: 12px;
    }

    .chat-channel-item {
        padding: 13px 12px;
        margin-bottom: 8px;
        border-radius: 16px;
    }

    .chat-channel-item.active {
        box-shadow: var(--chat-soft-shadow);
    }

    .chat-channel-avatar,
    .chat-conversation-avatar {
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        font-size: .95rem;
        box-shadow: inset 0 -6px 14px rgba(0,0,0,.12);
    }

    .chat-messages {
        position: relative;
        padding: 24px 24px 18px;
        background-color: var(--chat-background);
        background-image:
            radial-gradient(circle at 25px 25px, color-mix(in srgb, var(--chat-accent) 8%, transparent) 2px, transparent 0),
            radial-gradient(circle at 75px 75px, color-mix(in srgb, var(--chat-accent) 6%, transparent) 2px, transparent 0),
            linear-gradient(rgba(255,255,255,var(--chat-pattern-opacity)), rgba(255,255,255,var(--chat-pattern-opacity)));
        background-size: 100px 100px, 100px 100px, cover;
        background-position: 0 0, 10px 10px, center;
    }

    .chat-message-row {
        margin-bottom: 14px;
    }

    .chat-message {
        max-width: min(72%, 640px);
        padding: 10px 12px 8px;
        border-radius: 18px !important;
        box-shadow: var(--chat-soft-shadow);
    }

    .chat-message::after {
        content: '';
        position: absolute;
        top: 12px;
        width: 12px;
        height: 12px;
        transform: rotate(45deg);
        background: inherit;
        border: inherit;
        border-top: 0;
        border-left: 0;
        z-index: -1;
    }

    .chat-message-row.mine .chat-message::after {
        left: -5px;
        right: auto;
    }

    .chat-message-row.other .chat-message::after {
        right: -5px;
        left: auto;
    }

    .chat-message-row.mine .chat-message {
        border-bottom-right-radius: 18px !important;
        border-top-left-radius: 8px !important;
    }

    .chat-message-row.other .chat-message {
        border-bottom-left-radius: 18px !important;
        border-top-right-radius: 8px !important;
    }

    .chat-image-link img {
        border-radius: 14px;
        box-shadow: 0 8px 18px rgba(0,0,0,.08);
    }

    .chat-composer {
        gap: 10px;
        padding: 14px 16px 16px;
        background: linear-gradient(180deg, color-mix(in srgb, var(--chat-composer-bg) 94%, #ffffff 6%), var(--chat-composer-bg)) !important;
    }

    .chat-composer textarea {
        min-height: 48px;
        border-radius: 18px;
        padding: 12px 16px;
        box-shadow: inset 0 1px 2px rgba(15, 23, 42, .03);
    }

    .chat-attach-btn,
    .chat-send-btn {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        box-shadow: var(--chat-soft-shadow);
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .chat-attach-btn:hover,
    .chat-send-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 24px rgba(15,23,42,.10);
    }

    .chat-send-btn {
        background: linear-gradient(180deg, color-mix(in srgb, var(--chat-send-bg) 90%, #ffffff 10%), var(--chat-send-bg)) !important;
    }

    .chat-reply-box,
    .chat-file-preview {
        margin: 0 16px 10px;
        border-radius: 16px;
    }

    .chat-empty-state {
        border-radius: 22px;
        box-shadow: var(--chat-shell-shadow);
    }

    @media (max-width: 900px) {
        .chat-shell {
            border-radius: 18px !important;
        }

        .chat-messages {
            padding: 18px 14px 12px;
        }

        .chat-message {
            max-width: 90%;
        }
    }



    /* =========================================
       Chat viewport stability fix
       Keeps composer visible while messages scroll
    ========================================= */

    .chat-shell {
        min-height: 0 !important;
    }

    .chat-conversation {
        min-height: 0 !important;
        height: 100% !important;
        overflow: hidden !important;
        grid-template-rows:
            auto
            minmax(0, 1fr)
            auto
            auto
            auto !important;
    }

    .chat-messages {
        /*
         * مهم:
         * لا نستخدم height:100% داخل Grid.
         * لأنها تجعل صف الرسائل يأخذ ارتفاع المحادثة بالكامل
         * وتدفع حقل الكتابة إلى خارج البطاقة.
         */
        min-height: 0 !important;
        height: auto !important;
        max-height: none !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
    }

    .chat-composer,
    .chat-reply-box,
    .chat-file-preview,
    .chat-error {
        position: relative;
        z-index: 6;
        flex-shrink: 0;
    }

    .chat-composer {
        min-height: 72px;
    }

    @media (max-width: 900px) {
        .chat-conversation {
            min-height: 0 !important;
        }
    }



    /* =========================================
       Sender avatar + compact bubbles + receipts + emoji picker
    ========================================= */

    .chat-page {
        --chat-read-receipt: #53BDEB;
        --chat-sent-receipt: color-mix(in srgb, var(--chat-channel-muted) 78%, transparent);
    }

    .chat-message-row {
        direction: ltr;
        align-items: flex-end;
        gap: 8px;
    }

    .chat-message-row.mine {
        justify-content: flex-end !important;
    }

    .chat-message-row.other {
        justify-content: flex-start !important;
    }

    .chat-message-avatar {
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-radius: 50%;
        background: color-mix(in srgb, var(--chat-accent) 14%, var(--chat-other-bg));
        color: var(--chat-accent);
        border: 1px solid color-mix(in srgb, var(--chat-accent) 18%, var(--chat-border));
        box-shadow: 0 3px 10px rgba(15, 23, 42, .06);
        font-size: .7rem;
        font-weight: 800;
        user-select: none;
    }

    .chat-message-avatar img {
        width: 100%;
        height: 100%;
        display: block;
        object-fit: cover;
    }

    .chat-message-row.mine .chat-message-avatar {
        order: 2;
    }

    .chat-message-row.mine .chat-message-stack {
        order: 1;
        align-items: flex-end;
    }

    .chat-message-row.other .chat-message-avatar {
        order: 1;
    }

    .chat-message-row.other .chat-message-stack {
        order: 2;
        align-items: flex-start;
    }

    .chat-message-stack {
        min-width: 0;
        max-width: min(72%, 660px);
        display: flex;
        flex-direction: column;
        gap: 4px;
        direction: rtl;
    }

    .chat-message-sender {
        margin: 0 6px 1px;
        padding: 0;
        background: transparent !important;
        font-size: .68rem;
        line-height: 1.35;
        max-width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .chat-message {
        width: auto !important;
        max-width: 100% !important;
        min-width: 58px;
        display: inline-flex;
        flex-direction: column;
        align-self: flex-start;
        padding: 8px 10px 6px !important;
        direction: rtl;
    }

    .chat-message-row.mine .chat-message {
        align-self: flex-end;
    }

    .chat-message-body {
        display: block;
        width: auto;
        max-width: 100%;
        white-space: pre-wrap;
        word-break: break-word;
        overflow-wrap: anywhere;
        line-height: 1.75;
    }

    .chat-message-meta {
        width: max-content;
        max-width: 100%;
        margin-top: 4px;
        margin-inline-start: auto;
        gap: 6px;
        white-space: nowrap;
    }

    .chat-message-receipt {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        padding: 0;
        border: 0;
        background: transparent;
        color: var(--chat-sent-receipt);
        font-family: Arial, Tahoma, sans-serif;
        font-size: .72rem;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -2px;
        cursor: default;
    }

    .chat-message-receipt.status-read {
        color: var(--chat-read-receipt) !important;
    }

    .chat-message-receipt.status-delivered,
    .chat-message-receipt.status-sent {
        color: var(--chat-sent-receipt) !important;
    }

    .chat-emoji-btn {
        width: 44px;
        height: 44px;
        flex: 0 0 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        color: var(--chat-channel-muted);
        background: color-mix(in srgb, var(--chat-input-bg) 90%, var(--chat-background));
        border: 1px solid var(--chat-border);
        border-radius: 12px;
        cursor: pointer;
        font-size: 1.15rem;
        transition: .18s ease;
    }

    .chat-emoji-btn:hover,
    .chat-emoji-btn[aria-expanded="true"] {
        color: var(--chat-accent);
        border-color: color-mix(in srgb, var(--chat-accent) 40%, var(--chat-border));
        background: color-mix(in srgb, var(--chat-accent) 8%, var(--chat-input-bg));
        transform: translateY(-1px);
    }

    .chat-emoji-picker {
        position: absolute;
        z-index: 80;
        right: 16px;
        bottom: 78px;
        width: min(390px, calc(100% - 32px));
        max-height: 380px;
        overflow: hidden;
        background: var(--chat-composer-bg);
        color: var(--chat-channel-text);
        border: 1px solid var(--chat-border);
        border-radius: 18px;
        box-shadow: 0 22px 60px rgba(15, 23, 42, .22);
        backdrop-filter: blur(16px);
    }

    .chat-emoji-picker[hidden] {
        display: none;
    }

    .chat-emoji-picker-head {
        min-height: 48px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 9px 12px;
        border-bottom: 1px solid var(--chat-border);
    }

    .chat-emoji-picker-head strong {
        font-size: .8rem;
        font-weight: 800;
    }

    .chat-emoji-picker-head button {
        width: 30px;
        height: 30px;
        border: 0;
        border-radius: 9px;
        background: transparent;
        color: var(--chat-channel-muted);
        cursor: pointer;
        font-size: 1.2rem;
    }

    .chat-emoji-picker-head button:hover {
        background: color-mix(in srgb, var(--chat-accent) 8%, transparent);
        color: var(--chat-accent);
    }

    .chat-emoji-tabs {
        display: flex;
        gap: 4px;
        padding: 8px;
        overflow-x: auto;
        border-bottom: 1px solid var(--chat-border);
    }

    .chat-emoji-tab {
        min-width: 38px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 9px;
        background: transparent;
        cursor: pointer;
        font-size: 1rem;
    }

    .chat-emoji-tab:hover,
    .chat-emoji-tab.active {
        background: color-mix(in srgb, var(--chat-accent) 10%, transparent);
    }

    .chat-emoji-grid {
        display: grid;
        grid-template-columns: repeat(8, 1fr);
        gap: 3px;
        max-height: 260px;
        overflow-y: auto;
        padding: 10px;
    }

    .chat-emoji-item {
        aspect-ratio: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: 0;
        border-radius: 9px;
        background: transparent;
        cursor: pointer;
        font-size: 1.35rem;
        line-height: 1;
        transition: transform .12s ease, background .12s ease;
    }

    .chat-emoji-item:hover {
        background: color-mix(in srgb, var(--chat-accent) 8%, transparent);
        transform: scale(1.12);
    }

    @media (max-width: 700px) {
        .chat-message-stack {
            max-width: 82%;
        }

        .chat-message-avatar {
            width: 30px;
            height: 30px;
            flex-basis: 30px;
        }

        .chat-emoji-picker {
            right: 10px;
            bottom: 74px;
            width: calc(100% - 20px);
        }

        .chat-emoji-grid {
            grid-template-columns: repeat(7, 1fr);
        }
    }



    /* =========================================
       FINAL COMPOSER VISIBILITY FIX
       الرسائل فقط هي التي تعمل Scroll
       وحقل الكتابة يبقى ظاهرًا دائمًا
    ========================================= */

    .chat-shell {
        overflow: hidden !important;
    }

    .chat-conversation {
        position: relative !important;
        display: grid !important;

        grid-template-rows:
            auto
            minmax(0, 1fr)
            auto
            auto
            auto
            auto !important;

        min-height: 0 !important;
        height: 100% !important;
        overflow: hidden !important;
    }

    .chat-conversation-header {
        grid-row: 1;
        min-width: 0;
    }

    .chat-messages {
        grid-row: 2;
        min-width: 0 !important;
        min-height: 0 !important;

        height: auto !important;
        max-height: none !important;

        overflow-y: auto !important;
        overflow-x: hidden !important;
    }

    .chat-reply-box:not([hidden]) {
        grid-row: 3;
    }

    .chat-file-preview:not([hidden]) {
        grid-row: 4;
    }

    .chat-composer {
        grid-row: 5;

        position: relative !important;
        z-index: 20 !important;

        width: 100%;
        min-width: 0;
        min-height: 72px;

        flex-shrink: 0;

        visibility: visible !important;
        opacity: 1 !important;

        border-top:
            1px solid
            var(--chat-border) !important;
    }

    .chat-composer textarea {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;

        min-width: 0;
        width: 100%;
    }

    .chat-error:not([hidden]) {
        grid-row: 6;
    }

    .chat-emoji-picker {
        /*
         * نخلي الـEmoji Picker مرتبط بالمحادثة نفسها
         * بدون ما يأخذ صفًا من الـGrid.
         */
        position: absolute !important;
        right: 16px;
        bottom: 82px;
        z-index: 90;
    }

    @media (max-width: 900px) {
        .chat-conversation {
            min-height: 0 !important;
        }

        .chat-composer {
            min-height: 68px;
        }
    }



    /* =========================================
       FINAL VIEWPORT FIT FIX
       يمنع قص نصف حقل الرسالة أسفل الشاشة
    ========================================= */

    .chat-page {
        min-height: 0 !important;
    }

    .chat-shell {
        max-height:
            var(
                --chat-shell-height,
                calc(100dvh - 180px)
            ) !important;

        min-height: 0 !important;
        overflow: hidden !important;
    }

    .chat-conversation,
    .chat-channels-panel {
        min-height: 0 !important;
        height: 100% !important;
        overflow: hidden !important;
    }

    .chat-channel-list {
        min-height: 0 !important;
        overflow-y: auto !important;
        overscroll-behavior: contain;
    }

    .chat-composer {
        box-sizing: border-box !important;

        min-height: 70px !important;
        max-height: 170px;

        padding:
            12px 14px !important;

        overflow: visible !important;
    }

    .chat-composer textarea {
        min-height: 46px !important;
        max-height: 120px !important;
    }

    @media (max-width: 900px) {
        .chat-shell {
            height:
                var(
                    --chat-shell-height,
                    calc(100dvh - 155px)
                ) !important;

            max-height:
                var(
                    --chat-shell-height,
                    calc(100dvh - 155px)
                ) !important;
        }
    }



    /* =========================================
       HARD FIX: composer always fully visible
       Header / Messages / Bottom area only
    ========================================= */

    .chat-shell {
        min-height: 0 !important;
        overflow: hidden !important;
    }

    .chat-conversation {
        position: relative !important;
        display: grid !important;
        grid-template-rows:
            auto
            minmax(0, 1fr)
            auto !important;

        min-height: 0 !important;
        height: 100% !important;
        overflow: hidden !important;
    }

    .chat-conversation-header {
        grid-row: 1 !important;
        min-height: 0;
    }

    .chat-messages {
        grid-row: 2 !important;

        min-width: 0 !important;
        min-height: 0 !important;
        height: auto !important;
        max-height: none !important;

        overflow-y: auto !important;
        overflow-x: hidden !important;

        /*
         * مساحة بسيطة أسفل آخر رسالة حتى لا تلتصق بالـcomposer.
         */
        padding-bottom: 18px !important;
    }

    .chat-bottom-area {
        grid-row: 3 !important;

        position: relative !important;
        z-index: 50;

        display: block !important;

        width: 100%;
        min-width: 0;
        min-height: 0;

        flex: none !important;

        background:
            var(--chat-composer-bg);

        border-top:
            1px solid
            var(--chat-border);

        overflow: visible !important;
    }

    .chat-bottom-area .chat-reply-box,
    .chat-bottom-area .chat-file-preview {
        position: relative !important;
        inset: auto !important;

        margin:
            8px 14px 0 !important;
    }

    .chat-bottom-area .chat-composer {
        position: relative !important;
        inset: auto !important;

        display: flex !important;

        width: 100% !important;
        min-width: 0 !important;
        min-height: 72px !important;
        height: auto !important;

        box-sizing: border-box !important;

        margin: 0 !important;

        padding:
            11px 14px 13px !important;

        overflow: visible !important;

        visibility: visible !important;
        opacity: 1 !important;

        border-top: 0 !important;
    }

    .chat-bottom-area .chat-composer textarea {
        display: block !important;

        min-width: 0 !important;
        width: auto !important;
        min-height: 46px !important;
        max-height: 120px !important;

        flex: 1 1 auto !important;

        box-sizing: border-box !important;

        visibility: visible !important;
        opacity: 1 !important;
    }

    .chat-bottom-area .chat-attach-btn,
    .chat-bottom-area .chat-emoji-btn,
    .chat-bottom-area .chat-send-btn {
        flex:
            0 0 48px !important;

        width: 48px !important;
        height: 48px !important;

        align-self:
            flex-end;
    }

    .chat-bottom-area .chat-error:not([hidden]) {
        position: relative !important;
        inset: auto !important;

        margin:
            0 14px 10px !important;
    }

    .chat-bottom-area .chat-emoji-picker {
        position: absolute !important;

        right: 14px !important;
        bottom: 74px !important;

        z-index: 100 !important;
    }

    /*
     * يلغي أي grid-row قديم تم وضعه على عناصر الـfooter.
     */
    .chat-reply-box,
    .chat-file-preview,
    .chat-composer,
    .chat-error {
        grid-row:
            auto !important;
    }

    @media (max-width: 900px) {
        .chat-bottom-area .chat-composer {
            min-height: 68px !important;

            padding:
                9px 10px 11px !important;
        }

        .chat-bottom-area .chat-attach-btn,
        .chat-bottom-area .chat-emoji-btn,
        .chat-bottom-area .chat-send-btn {
            flex-basis:
                44px !important;

            width:
                44px !important;

            height:
                44px !important;
        }

        .chat-bottom-area .chat-emoji-picker {
            right:
                10px !important;

            bottom:
                68px !important;

            width:
                calc(100% - 20px) !important;
        }
    }



    /* =========================================
       Direct user conversations
    ========================================= */

    .chat-panel-header {
        justify-content: space-between;
        gap: 10px;
    }

    .chat-new-direct-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        flex: 0 0 auto;
        padding: 7px 10px;
        color: var(--chat-send-text);
        background: var(--chat-send-bg);
        border: 0;
        border-radius: 10px;
        font-family: inherit;
        font-size: .68rem;
        font-weight: 800;
        cursor: pointer;
        transition: .18s ease;
    }

    .chat-new-direct-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 7px 18px rgba(15, 23, 42, .10);
    }

    .chat-user-modal {
        position: fixed;
        inset: 0;
        z-index: 10050;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(15, 23, 42, .48);
        backdrop-filter: blur(4px);
    }

    .chat-user-modal[hidden] {
        display: none;
    }

    .chat-user-modal-card {
        width: min(100%, 570px);
        max-height: min(720px, 88vh);
        display: grid;
        grid-template-rows: auto auto minmax(0, 1fr);
        overflow: hidden;
        background: var(--chat-channels-bg);
        color: var(--chat-channel-text);
        border: 1px solid var(--chat-border);
        border-radius: 20px;
        box-shadow: 0 28px 90px rgba(0, 0, 0, .24);
    }

    .chat-user-modal-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 15px;
        padding: 17px 18px 14px;
        border-bottom: 1px solid var(--chat-border);
    }

    .chat-user-modal-head h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
    }

    .chat-user-modal-head p {
        margin: 5px 0 0;
        color: var(--chat-channel-muted);
        font-size: .72rem;
    }

    .chat-user-modal-head button {
        width: 32px;
        height: 32px;
        border: 0;
        border-radius: 9px;
        color: var(--chat-channel-muted);
        background: transparent;
        cursor: pointer;
        font-size: 1.25rem;
    }

    .chat-user-modal-head button:hover {
        background: color-mix(
            in srgb,
            var(--chat-accent) 9%,
            transparent
        );
    }

    .chat-user-search-wrap {
        padding: 12px 16px;
        border-bottom: 1px solid var(--chat-border);
    }

    .chat-user-results {
        min-height: 180px;
        overflow-y: auto;
        padding: 8px;
    }

    .chat-user-loading,
    .chat-user-empty {
        padding: 28px 15px;
        color: var(--chat-channel-muted);
        text-align: center;
        font-size: .76rem;
    }

    .chat-user-row {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 10px 11px;
        color: var(--chat-channel-text);
        background: transparent;
        border: 1px solid transparent;
        border-radius: 14px;
        font-family: inherit;
        text-align: right;
        cursor: pointer;
    }

    .chat-user-row:hover {
        background: color-mix(
            in srgb,
            var(--chat-accent) 7%,
            transparent
        );
        border-color: color-mix(
            in srgb,
            var(--chat-accent) 20%,
            var(--chat-border)
        );
    }

    .chat-user-avatar {
        position: relative;
        width: 46px;
        height: 46px;
        flex: 0 0 46px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: var(--chat-accent);
        border-radius: 50%;
        font-size: .84rem;
        font-weight: 800;
    }

    .chat-user-avatar img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .chat-user-info {
        min-width: 0;
        flex: 1;
    }

    .chat-user-info strong,
    .chat-user-info span {
        display: block;
    }

    .chat-user-info strong {
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        font-size: .8rem;
    }

    .chat-user-info span {
        margin-top: 3px;
        overflow: hidden;
        color: var(--chat-channel-muted);
        white-space: nowrap;
        text-overflow: ellipsis;
        font-size: .67rem;
    }

    .chat-user-existing {
        flex: 0 0 auto;
        padding: 4px 7px;
        color: var(--chat-accent);
        background: color-mix(
            in srgb,
            var(--chat-accent) 9%,
            transparent
        );
        border-radius: 999px;
        font-size: .58rem;
        font-weight: 800;
    }

</style>

@endpush


@if($selectedChannel)

@push('scripts')

<script>

document.addEventListener(
    'DOMContentLoaded',
    () => {

        /*
        |--------------------------------------------------------------------------
        | Fit chat card exactly inside the visible browser viewport
        |--------------------------------------------------------------------------
        |
        | نعتمد على موقع البطاقة الفعلي بدل calc ثابت، لأن ارتفاع الـTopbar
        | والهيدر يختلف حسب حجم الشاشة والثيم.
        |
        */

        const chatShell =
            document.querySelector(
                '.chat-shell'
            );

        const fitChatShellToViewport =
            () => {
                if (!chatShell) {
                    return;
                }

                const rect =
                    chatShell
                        .getBoundingClientRect();

                const bottomGap =
                    window.innerWidth <= 900
                        ? 18
                        : 22;

                const availableHeight =
                    Math.max(
                        280,
                        Math.floor(
                            document.documentElement.clientHeight
                            - rect.top
                            - bottomGap
                        )
                    );

                chatShell.style.setProperty(
                    '--chat-shell-height',
                    `${availableHeight}px`
                );
            };

        fitChatShellToViewport();

        window.addEventListener(
            'resize',
            fitChatShellToViewport
        );

        window.addEventListener(
            'orientationchange',
            fitChatShellToViewport
        );

        /*
         * بعد تحميل الخطوط/الصور قد يتغير مكان البطاقة قليلًا.
         */
        window.setTimeout(
            fitChatShellToViewport,
            120
        );

        window.setTimeout(
            fitChatShellToViewport,
            600
        );

        const channelId =
            @json($selectedChannel->id);

        const messagesUrl =
            @json(
                route(
                    'chat.messages',
                    $selectedChannel
                )
            );

        const sendUrl =
            @json(
                route(
                    'chat.messages.store',
                    $selectedChannel
                )
            );

        const readUrl =
            @json(
                route(
                    'chat.read',
                    $selectedChannel
                )
            );

        const csrfToken =
            document.querySelector(
                'meta[name="csrf-token"]'
            )?.content || '';

        const directUsersUrl =
            @json(
                route(
                    'chat.users'
                )
            );

        const directStartUrlTemplate =
            @json(
                route(
                    'chat.direct.start',
                    ['user' => '__USER__']
                )
            );

        const canStartDirect =
            @json($canStartDirect);

        const messagesBox =
            document.getElementById(
                'chatMessages'
            );

        const composer =
            document.getElementById(
                'chatComposer'
            );

        const input =
            document.getElementById(
                'chatMessageInput'
            );

        const attachmentsInput =
            document.getElementById(
                'chatAttachments'
            );

        const sendBtn =
            document.getElementById(
                'chatSendBtn'
            );

        const errorBox =
            document.getElementById(
                'chatError'
            );

        const filePreview =
            document.getElementById(
                'chatFilePreview'
            );

        const replyBox =
            document.getElementById(
                'chatReplyBox'
            );

        const replyToId =
            document.getElementById(
                'replyToId'
            );

        const replySender =
            document.getElementById(
                'chatReplySender'
            );

        const replyText =
            document.getElementById(
                'chatReplyText'
            );

        const cancelReply =
            document.getElementById(
                'cancelReply'
            );

        const emojiBtn =
            document.getElementById(
                'chatEmojiBtn'
            );

        const emojiPicker =
            document.getElementById(
                'chatEmojiPicker'
            );

        const emojiClose =
            document.getElementById(
                'chatEmojiClose'
            );

        const emojiTabs =
            document.getElementById(
                'chatEmojiTabs'
            );

        const emojiGrid =
            document.getElementById(
                'chatEmojiGrid'
            );

        let lastMessageId = 0;
        let loading = false;
        let initialLoaded = false;


        const escapeHtml = (value) => {
            const div =
                document.createElement('div');

            div.textContent =
                value ?? '';

            return div.innerHTML;
        };


        const senderInitials = (name) => {
            const parts = String(name || 'م')
                .trim()
                .split(/\s+/)
                .filter(Boolean);

            return (
                (parts[0]?.[0] || '')
                + (parts[1]?.[0] || '')
            ).toUpperCase() || 'م';
        };


        const senderAvatarHtml = (message) => {
            const name =
                message?.sender?.name
                || 'مستخدم';

            const avatar =
                message?.sender?.avatar;

            if (avatar) {
                return `
                    <div
                        class="chat-message-avatar"
                        title="${escapeHtml(name)}"
                    >
                        <img
                            src="${escapeHtml(avatar)}"
                            alt="${escapeHtml(name)}"
                            loading="lazy"
                            onerror="this.parentElement.innerHTML='${escapeHtml(senderInitials(name))}'"
                        >
                    </div>
                `;
            }

            return `
                <div
                    class="chat-message-avatar"
                    title="${escapeHtml(name)}"
                >
                    ${escapeHtml(
                        senderInitials(name)
                    )}
                </div>
            `;
        };


        const normalizeMessageStatus = (message) => {
            const status =
                message?.status;

            if (status?.key === 'read') {
                return {
                    key: 'read',
                    icon: '✓✓',
                    label:
                        status.detail_text
                        || status.label
                        || 'تمت القراءة',
                };
            }

            if (status?.key === 'delivered') {
                return {
                    key: 'delivered',
                    icon: '✓',
                    label:
                        status.detail_text
                        || status.label
                        || 'تم التسليم ولم تتم القراءة بعد',
                };
            }

            if (message?.seen === true) {
                return {
                    key: 'read',
                    icon: '✓✓',
                    label: 'تمت القراءة',
                };
            }

            return {
                key: 'sent',
                icon: '✓',
                label:
                    status?.detail_text
                    || status?.label
                    || 'تم الإرسال ولم تتم القراءة بعد',
            };
        };


        const messageReceiptHtml = (message) => {
            if (!message.mine) {
                return '';
            }

            const status =
                normalizeMessageStatus(
                    message
                );

            return `
                <span
                    class="chat-message-receipt status-${status.key}"
                    data-message-status-id="${message.id}"
                    title="${escapeHtml(status.label)}"
                    aria-label="${escapeHtml(status.label)}"
                >${status.icon}</span>
            `;
        };


        const updateOutgoingStatuses = (statuses) => {
            if (!Array.isArray(statuses)) {
                return;
            }

            statuses.forEach((item) => {
                const element =
                    document.querySelector(
                        `[data-message-status-id="${Number(item.message_id)}"]`
                    );

                if (!element || !item.status) {
                    return;
                }

                const pseudoMessage = {
                    id: item.message_id,
                    mine: true,
                    status: item.status,
                };

                const normalized =
                    normalizeMessageStatus(
                        pseudoMessage
                    );

                element.className =
                    `chat-message-receipt status-${normalized.key}`;

                element.textContent =
                    normalized.icon;

                element.title =
                    normalized.label;

                element.setAttribute(
                    'aria-label',
                    normalized.label
                );
            });
        };


        const formatBytes = (bytes) => {
            const value =
                Number(bytes || 0);

            if (value < 1024) {
                return `${value} B`;
            }

            if (value < 1024 * 1024) {
                return `${(
                    value / 1024
                ).toFixed(1)} KB`;
            }

            return `${(
                value / 1024 / 1024
            ).toFixed(1)} MB`;
        };


        const isNearBottom = () => {
            return (
                messagesBox.scrollHeight
                - messagesBox.scrollTop
                - messagesBox.clientHeight
            ) < 160;
        };


        const scrollBottom = (
            smooth = false
        ) => {
            messagesBox.scrollTo({
                top:
                    messagesBox.scrollHeight,

                behavior:
                    smooth
                        ? 'smooth'
                        : 'auto',
            });
        };


        const attachmentHtml = (
            attachment
        ) => {

            if (
                attachment.is_image
            ) {
                return `
                    <a
                        class="chat-image-link"
                        href="${escapeHtml(
                            attachment.url
                        )}"
                        target="_blank"
                        rel="noopener"
                    >
                        <img
                            src="${escapeHtml(
                                attachment.url
                            )}"
                            alt="${escapeHtml(
                                attachment.name
                            )}"
                        >
                    </a>
                `;
            }

            return `
                <a
                    class="chat-file-link"
                    href="${escapeHtml(
                        attachment.url
                    )}"
                    target="_blank"
                    rel="noopener"
                >
                    <span>📎</span>

                    <span>
                        ${escapeHtml(
                            attachment.name
                        )}
                        ·
                        ${formatBytes(
                            attachment.size
                        )}
                    </span>
                </a>
            `;
        };


        const messageHtml = (
            message
        ) => {

            const reply =
                message.reply_to
                    ? `
                        <div
                            class="chat-reply-snippet"
                        >
                            <strong>
                                ${escapeHtml(
                                    message
                                        .reply_to
                                        .sender
                                )}
                            </strong>

                            <span>
                                ${escapeHtml(
                                    message
                                        .reply_to
                                        .message
                                )}
                            </span>
                        </div>
                    `
                    : '';

            const body =
                message.message
                    ? `
                        <div
                            class="chat-message-body"
                        >${escapeHtml(
                            message.message
                        )}</div>
                    `
                    : '';

            const attachments =
                Array.isArray(
                    message.attachments
                )
                && message
                    .attachments
                    .length

                    ? `
                        <div
                            class="chat-attachments"
                        >
                            ${
                                message
                                    .attachments
                                    .map(
                                        attachmentHtml
                                    )
                                    .join('')
                            }
                        </div>
                    `
                    : '';

            const senderName =
                escapeHtml(
                    message?.sender?.name
                    || 'مستخدم'
                );

            return `
                <div
                    class="chat-message-row ${
                        message.mine
                            ? 'mine'
                            : 'other'
                    }"
                    data-message-id="${message.id}"
                >

                    ${senderAvatarHtml(message)}

                    <div
                        class="chat-message-stack"
                    >

                        ${
                            !message.mine
                                ? `
                                    <div
                                        class="chat-message-sender"
                                        title="${senderName}"
                                    >${senderName}</div>
                                `
                                : ''
                        }

                        <div
                            class="chat-message"
                        >

                            <button
                                type="button"
                                class="chat-reply-action"
                                data-reply-id="${message.id}"
                                data-reply-sender="${senderName}"
                                data-reply-text="${escapeHtml(
                                    message.message
                                    || '📎 مرفق'
                                )}"
                            >
                                رد
                            </button>

                            ${reply}
                            ${body}
                            ${attachments}

                            <div
                                class="chat-message-meta"
                            >
                                <span>
                                    ${escapeHtml(
                                        message.time
                                    )}
                                </span>

                                ${messageReceiptHtml(message)}
                            </div>

                        </div>

                    </div>

                </div>
            `;
        };


        const appendMessages = (
            messages
        ) => {

            if (
                !Array.isArray(messages)
                || messages.length === 0
            ) {
                return;
            }

            const shouldScroll =
                !initialLoaded
                || isNearBottom();

            let latestNewIncomingMessageId =
                0;

            messages.forEach(
                (message) => {

                    if (
                        document.querySelector(
                            `[data-message-id="${message.id}"]`
                        )
                    ) {
                        return;
                    }

                    messagesBox.insertAdjacentHTML(
                        'beforeend',
                        messageHtml(
                            message
                        )
                    );

                    /*
                     * أثناء أول تحميل لا نصدر صوتًا للرسائل القديمة.
                     * بعد ذلك أي رسالة جديدة ليست من المستخدم الحالي
                     * تشغّل صوت المحادثة الخاص.
                     */
                    if (
                        initialLoaded
                        && !message.mine
                    ) {
                        latestNewIncomingMessageId =
                            Math.max(
                                latestNewIncomingMessageId,
                                Number(
                                    message.id
                                    || 0
                                )
                            );
                    }

                    lastMessageId =
                        Math.max(
                            lastMessageId,
                            Number(
                                message.id
                            )
                        );
                }
            );

            if (
                latestNewIncomingMessageId > 0
                && typeof window
                    .playChatMessageSound
                    === 'function'
            ) {
                window.playChatMessageSound(
                    latestNewIncomingMessageId
                );
            }

            if (shouldScroll) {
                scrollBottom(
                    initialLoaded
                );
            }
        };


        const loadMessages = async (
            initial = false
        ) => {

            if (
                loading
                || document.hidden
            ) {
                return;
            }

            loading = true;

            try {

                const url =
                    new URL(
                        messagesUrl,
                        window.location.origin
                    );

                if (
                    !initial
                    && lastMessageId > 0
                ) {
                    url.searchParams.set(
                        'after_id',
                        String(
                            lastMessageId
                        )
                    );
                }

                const response =
                    await fetch(
                        url,
                        {
                            headers: {
                                'Accept':
                                    'application/json',

                                'X-Requested-With':
                                    'XMLHttpRequest',
                            },

                            credentials:
                                'same-origin',

                            cache:
                                'no-store',
                        }
                    );

                if (!response.ok) {
                    throw new Error(
                        `HTTP ${response.status}`
                    );
                }

                const data =
                    await response.json();

                if (initial) {
                    messagesBox.innerHTML =
                        '';

                    lastMessageId = 0;
                }

                appendMessages(
                    data.messages || []
                );

                updateOutgoingStatuses(
                    data.outgoing_statuses
                    || []
                );

                if (
                    initial
                    && (
                        !data.messages
                        || !data
                            .messages
                            .length
                    )
                ) {
                    messagesBox.innerHTML =
                        `
                            <div
                                class="chat-loading"
                            >
                                ابدأ المحادثة مع هذا الفرع
                            </div>
                        `;
                }

                initialLoaded =
                    true;

                if (
                    initial
                    && data.messages?.length
                ) {
                    scrollBottom();
                }

            } catch (error) {

                console.debug(
                    'Chat polling failed.',
                    error
                );

            } finally {

                loading =
                    false;
            }
        };


        const showError = (
            message
        ) => {
            errorBox.textContent =
                message;

            errorBox.hidden =
                false;
        };


        const clearError = () => {
            errorBox.hidden =
                true;

            errorBox.textContent =
                '';
        };


        const clearReply = () => {
            replyToId.value =
                '';

            replySender.textContent =
                '';

            replyText.textContent =
                '';

            replyBox.hidden =
                true;
        };


        messagesBox.addEventListener(
            'click',
            (event) => {

                const button =
                    event.target.closest(
                        '.chat-reply-action'
                    );

                if (!button) {
                    return;
                }

                replyToId.value =
                    button.dataset.replyId
                    || '';

                replySender.textContent =
                    button.dataset
                        .replySender
                    || '';

                replyText.textContent =
                    button.dataset
                        .replyText
                    || '';

                replyBox.hidden =
                    false;

                input.focus();
            }
        );


        cancelReply.addEventListener(
            'click',
            clearReply
        );


        const emojiCategories = {
            recent: {
                icon: '🕘',
                label: 'الأخيرة',
                emojis: ['😀','😂','🥹','😍','🥰','😘','😊','😉','😎','😭','😅','❤️','🔥','👍','🙏','👏','🎉','💯','✅','✨'],
            },
            faces: {
                icon: '😀',
                label: 'الوجوه',
                emojis: ['😀','😃','😄','😁','😆','😅','😂','🤣','🥲','🥹','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😝','😜','🤪','🤨','🧐','🤓','😎','🥸','🤩','🥳','🙂‍↕️','😏','😒','🙂‍↔️','😞','😔','😟','😕','🙁','☹️','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤯','😳','🥵','🥶','😱','😨','😰','😥','😓','🤗','🤔','🫣','🤭','🫢','🫡','🤫','🫠','🤥','😶','😶‍🌫️','😐','😑','😬','🫨','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','😵‍💫','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕','🤑','🤠','😈','👿','👹','👺','🤡','💩','👻','💀','☠️','👽','👾','🤖'],
            },
            gestures: {
                icon: '👍',
                label: 'الأيدي',
                emojis: ['👋','🤚','🖐️','✋','🖖','🫱','🫲','🫳','🫴','👌','🤌','🤏','✌️','🤞','🫰','🤟','🤘','🤙','👈','👉','👆','🖕','👇','☝️','🫵','👍','👎','✊','👊','🤛','🤜','👏','🙌','🫶','👐','🤲','🤝','🙏','✍️','💅','🤳','💪','🦾','🦿','🦵','🦶','👂','👃','🧠','🫀','🫁','🦷','👀','👁️','👄','🫦'],
            },
            hearts: {
                icon: '❤️',
                label: 'القلوب',
                emojis: ['❤️','🩷','🧡','💛','💚','💙','🩵','💜','🤎','🖤','🩶','🤍','💔','❤️‍🔥','❤️‍🩹','❣️','💕','💞','💓','💗','💖','💘','💝','💟','♥️','💋','💌','💐','🌹','🥀','🌷','🌸','💮','🪻','🌺','🌻','🌼','🪷','✨','⭐','🌟','💫','⚡','🔥','💥','💯'],
            },
            animals: {
                icon: '🐶',
                label: 'الحيوانات',
                emojis: ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐻‍❄️','🐨','🐯','🦁','🐮','🐷','🐽','🐸','🐵','🙈','🙉','🙊','🐒','🐔','🐧','🐦','🐤','🐣','🦆','🦅','🦉','🦇','🐺','🐗','🐴','🦄','🐝','🪱','🐛','🦋','🐌','🐞','🐜','🪰','🪲','🪳','🦟','🦗','🕷️','🦂','🐢','🐍','🦎','🐙','🦑','🪼','🦐','🦞','🦀','🐡','🐠','🐟','🐬','🐳','🐋','🦈','🦭','🐊','🐅','🐆','🦓','🫏','🦍','🦧','🦣','🐘','🦛','🦏','🐪','🐫','🦒','🦘','🦬','🐃','🐂','🐄','🐎','🐖','🐏','🐑','🦙','🐐','🦌','🫎','🐕','🐩','🦮','🐕‍🦺','🐈','🐈‍⬛','🪽','🪶','🐓','🦃','🦤','🦚','🦜','🦢','🪿','🦩','🕊️','🐇','🦝','🦨','🦡','🦫','🦦','🦥','🐁','🐀','🐿️','🦔'],
            },
            food: {
                icon: '🍕',
                label: 'الطعام',
                emojis: ['🍏','🍎','🍐','🍊','🍋','🍋‍🟩','🍌','🍉','🍇','🍓','🫐','🍈','🍒','🍑','🥭','🍍','🥥','🥝','🍅','🍆','🥑','🥦','🫛','🥬','🥒','🌶️','🫑','🌽','🥕','🫒','🧄','🧅','🥔','🍠','🫚','🥐','🥯','🍞','🥖','🥨','🧀','🥚','🍳','🧈','🥞','🧇','🥓','🥩','🍗','🍖','🌭','🍔','🍟','🍕','🫓','🥪','🥙','🧆','🌮','🌯','🫔','🥗','🥘','🫕','🥫','🍝','🍜','🍲','🍛','🍣','🍱','🥟','🦪','🍤','🍙','🍚','🍘','🍥','🥠','🥮','🍢','🍡','🍧','🍨','🍦','🥧','🧁','🍰','🎂','🍮','🍭','🍬','🍫','🍿','🍩','🍪','🌰','🥜','🍯','☕','🫖','🍵','🧃','🥤','🧋','🫙','🍶','🍺','🍻','🥂','🍷','🥃','🍸','🍹','🧉'],
            },
            activity: {
                icon: '⚽',
                label: 'النشاط',
                emojis: ['⚽','🏀','🏈','⚾','🥎','🎾','🏐','🏉','🥏','🎱','🪀','🏓','🏸','🏒','🏑','🥍','🏏','🪃','🥅','⛳','🪁','🏹','🎣','🤿','🥊','🥋','🎽','🛹','🛼','🛷','⛸️','🥌','🎿','⛷️','🏂','🪂','🏋️','🤼','🤸','⛹️','🤺','🤾','🏌️','🏇','🧘','🏄','🏊','🤽','🚣','🧗','🚵','🚴','🏆','🥇','🥈','🥉','🏅','🎖️','🏵️','🎗️','🎫','🎟️','🎪','🤹','🎭','🩰','🎨','🎬','🎤','🎧','🎼','🎹','🥁','🪘','🎷','🎺','🪗','🎸','🪕','🎻','🪈'],
            },
            travel: {
                icon: '🚗',
                label: 'السفر',
                emojis: ['🚗','🚕','🚙','🚌','🚎','🏎️','🚓','🚑','🚒','🚐','🛻','🚚','🚛','🚜','🦯','🦽','🦼','🛴','🚲','🛵','🏍️','🛺','🚨','🚔','🚍','🚘','🚖','🚡','🚠','🚟','🚃','🚋','🚞','🚝','🚄','🚅','🚈','🚂','🚆','🚇','🚊','🚉','✈️','🛫','🛬','🛩️','💺','🛰️','🚀','🛸','🚁','🛶','⛵','🚤','🛥️','🛳️','⛴️','🚢','⚓','🛟','⛽','🚧','🚦','🚥','🗺️','🗿','🗽','🗼','🏰','🏯','🏟️','🎡','🎢','🎠','⛲','⛱️','🏖️','🏝️','🏜️','🌋','⛰️','🏕️','⛺','🏠','🏡','🏢','🏥','🏦','🏨','🏪','🏫','🏬','🏭','🏛️','⛪','🕌','🕍','🛕','🕋'],
            },
            symbols: {
                icon: '✅',
                label: 'الرموز',
                emojis: ['✅','☑️','✔️','❌','❎','➕','➖','➗','✖️','♾️','‼️','⁉️','❓','❔','❕','❗','〰️','💱','💲','⚕️','♻️','⚜️','🔱','📛','🔰','⭕','🛑','⛔','🚫','🚳','🚭','🚯','🚱','🚷','📵','🔞','☢️','☣️','⬆️','↗️','➡️','↘️','⬇️','↙️','⬅️','↖️','↕️','↔️','↩️','↪️','⤴️','⤵️','🔃','🔄','🔙','🔚','🔛','🔜','🔝','🛐','⚛️','🕉️','✡️','☸️','☯️','✝️','☦️','☪️','☮️','🕎','🔯','🪯','♈','♉','♊','♋','♌','♍','♎','♏','♐','♑','♒','♓','⛎','🔀','🔁','🔂','▶️','⏩','⏭️','⏯️','◀️','⏪','⏮️','🔼','⏫','🔽','⏬','⏸️','⏹️','⏺️','⏏️','🎦','🔅','🔆','📶','🛜','📳','📴','♀️','♂️','⚧️','✚','➰','➿','〽️','✳️','✴️','❇️','©️','®️','™️','#️⃣','*️⃣','0️⃣','1️⃣','2️⃣','3️⃣','4️⃣','5️⃣','6️⃣','7️⃣','8️⃣','9️⃣','🔟'],
            },
        };


        const recentEmojiKey =
            'chat_recent_emojis';


        const getRecentEmojis = () => {
            try {
                const saved =
                    JSON.parse(
                        localStorage.getItem(
                            recentEmojiKey
                        ) || '[]'
                    );

                return Array.isArray(saved)
                    ? saved
                    : [];
            } catch (_) {
                return [];
            }
        };


        const saveRecentEmoji = (emoji) => {
            const updated = [
                emoji,
                ...getRecentEmojis()
                    .filter(
                        (item) =>
                            item !== emoji
                    ),
            ].slice(0, 32);

            localStorage.setItem(
                recentEmojiKey,
                JSON.stringify(updated)
            );
        };


        let activeEmojiCategory =
            'recent';


        const renderEmojiTabs = () => {
            emojiTabs.innerHTML =
                Object.entries(
                    emojiCategories
                )
                .map(
                    ([key, category]) => `
                        <button
                            type="button"
                            class="chat-emoji-tab ${
                                key === activeEmojiCategory
                                    ? 'active'
                                    : ''
                            }"
                            data-emoji-category="${key}"
                            title="${escapeHtml(category.label)}"
                            aria-label="${escapeHtml(category.label)}"
                        >${category.icon}</button>
                    `
                )
                .join('');
        };


        const renderEmojiGrid = () => {
            const category =
                emojiCategories[
                    activeEmojiCategory
                ];

            const recent =
                getRecentEmojis();

            const list =
                activeEmojiCategory === 'recent'
                    ? (
                        recent.length
                            ? recent
                            : category.emojis
                    )
                    : category.emojis;

            emojiGrid.innerHTML =
                list
                    .map(
                        (emoji) => `
                            <button
                                type="button"
                                class="chat-emoji-item"
                                data-emoji="${emoji}"
                                title="${emoji}"
                            >${emoji}</button>
                        `
                    )
                    .join('');
        };


        const setEmojiPickerOpen = (open) => {
            emojiPicker.hidden =
                !open;

            emojiBtn.setAttribute(
                'aria-expanded',
                open ? 'true' : 'false'
            );

            if (open) {
                renderEmojiTabs();
                renderEmojiGrid();
            }
        };


        const insertEmoji = (emoji) => {
            const start =
                input.selectionStart
                ?? input.value.length;

            const end =
                input.selectionEnd
                ?? input.value.length;

            input.setRangeText(
                emoji,
                start,
                end,
                'end'
            );

            saveRecentEmoji(emoji);

            input.dispatchEvent(
                new Event(
                    'input',
                    { bubbles: true }
                )
            );

            input.focus();
        };


        emojiBtn.addEventListener(
            'click',
            () => {
                setEmojiPickerOpen(
                    emojiPicker.hidden
                );
            }
        );


        emojiClose.addEventListener(
            'click',
            () => {
                setEmojiPickerOpen(false);
                input.focus();
            }
        );


        emojiTabs.addEventListener(
            'click',
            (event) => {
                const button =
                    event.target.closest(
                        '[data-emoji-category]'
                    );

                if (!button) {
                    return;
                }

                /*
                 * مهم:
                 * عند إعادة رسم Tabs يتم حذف الزر الذي تم الضغط عليه
                 * من DOM، لذلك document click كان يعتبر الضغط خارج
                 * نافذة الإيموجي ويغلقها.
                 */
                event.preventDefault();
                event.stopPropagation();

                activeEmojiCategory =
                    button.dataset
                        .emojiCategory;

                renderEmojiTabs();
                renderEmojiGrid();

                /*
                 * نبقي نافذة الإيموجي مفتوحة أثناء التنقل
                 * بين الأقسام.
                 */
                emojiPicker.hidden =
                    false;

                emojiBtn.setAttribute(
                    'aria-expanded',
                    'true'
                );
            }
        );


        emojiGrid.addEventListener(
            'click',
            (event) => {
                const button =
                    event.target.closest(
                        '[data-emoji]'
                    );

                if (!button) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                insertEmoji(
                    button.dataset.emoji
                );

                /*
                 * لا نغلق الـPicker بعد اختيار إيموجي،
                 * حتى يستطيع المستخدم اختيار أكثر من إيموجي
                 * أو الانتقال إلى قسم آخر مباشرة.
                 */
                emojiPicker.hidden =
                    false;

                emojiBtn.setAttribute(
                    'aria-expanded',
                    'true'
                );
            }
        );


        document.addEventListener(
            'click',
            (event) => {
                if (
                    emojiPicker.hidden
                    || emojiPicker.contains(
                        event.target
                    )
                    || emojiBtn.contains(
                        event.target
                    )
                ) {
                    return;
                }

                setEmojiPickerOpen(false);
            }
        );


        document.addEventListener(
            'keydown',
            (event) => {
                if (
                    event.key === 'Escape'
                    && !emojiPicker.hidden
                ) {
                    setEmojiPickerOpen(false);
                }
            }
        );


        attachmentsInput.addEventListener(
            'change',
            () => {

                const files =
                    Array.from(
                        attachmentsInput.files
                        || []
                    );

                if (!files.length) {
                    filePreview.hidden =
                        true;

                    filePreview.innerHTML =
                        '';

                    return;
                }

                filePreview.innerHTML =
                    files
                        .map(
                            (file) =>
                                `
                                    <div>
                                        📎
                                        ${escapeHtml(
                                            file.name
                                        )}
                                        ·
                                        ${formatBytes(
                                            file.size
                                        )}
                                    </div>
                                `
                        )
                        .join('');

                filePreview.hidden =
                    false;
            }
        );


        input.addEventListener(
            'input',
            () => {

                input.style.height =
                    'auto';

                input.style.height =
                    `${Math.min(
                        input.scrollHeight,
                        140
                    )}px`;
            }
        );


        input.addEventListener(
            'keydown',
            (event) => {

                if (
                    event.key === 'Enter'
                    && !event.shiftKey
                ) {
                    event.preventDefault();

                    composer.requestSubmit();
                }
            }
        );


        composer.addEventListener(
            'submit',
            async (event) => {

                event.preventDefault();

                clearError();

                const text =
                    input.value.trim();

                const hasFiles =
                    attachmentsInput.files
                    && attachmentsInput
                        .files
                        .length > 0;

                if (
                    !text
                    && !hasFiles
                ) {
                    return;
                }

                sendBtn.disabled =
                    true;

                try {

                    const formData =
                        new FormData(
                            composer
                        );

                    const response =
                        await fetch(
                            sendUrl,
                            {
                                method:
                                    'POST',

                                headers: {
                                    'Accept':
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',

                                    'X-CSRF-TOKEN':
                                        csrfToken,
                                },

                                credentials:
                                    'same-origin',

                                body:
                                    formData,
                            }
                        );

                    const data =
                        await response.json();

                    if (!response.ok) {

                        const firstError =
                            data.errors
                                ? Object
                                    .values(
                                        data.errors
                                    )
                                    .flat()
                                    [0]
                                : data.message;

                        throw new Error(
                            firstError
                            || 'تعذر إرسال الرسالة.'
                        );
                    }

                    const emptyState =
                        messagesBox.querySelector(
                            '.chat-loading'
                        );

                    if (emptyState) {
                        messagesBox.innerHTML =
                            '';
                    }

                    appendMessages([
                        data.message
                    ]);

                    input.value =
                        '';

                    input.style.height =
                        'auto';

                    attachmentsInput.value =
                        '';

                    filePreview.hidden =
                        true;

                    filePreview.innerHTML =
                        '';

                    clearReply();

                    scrollBottom(
                        true
                    );

                } catch (error) {

                    showError(
                        error.message
                        || 'تعذر إرسال الرسالة.'
                    );

                } finally {

                    sendBtn.disabled =
                        false;

                    input.focus();
                }
            }
        );


        const markVisibleRead =
            async () => {

                if (
                    !lastMessageId
                ) {
                    return;
                }

                try {

                    await fetch(
                        readUrl,
                        {
                            method:
                                'POST',

                            headers: {
                                'Accept':
                                    'application/json',

                                'X-Requested-With':
                                    'XMLHttpRequest',

                                'X-CSRF-TOKEN':
                                    csrfToken,

                                'Content-Type':
                                    'application/json',
                            },

                            credentials:
                                'same-origin',

                            body:
                                JSON.stringify({
                                    message_id:
                                        lastMessageId,
                                }),
                        }
                    );

                } catch (error) {

                    console.debug(
                        'Mark read failed.',
                        error
                    );
                }
            };



        /*
        |--------------------------------------------------------------------------
        | Direct user picker
        |--------------------------------------------------------------------------
        */

        const userModal =
            document.getElementById(
                'chatUserModal'
            );

        const userModalClose =
            document.getElementById(
                'chatUserModalClose'
            );

        const userSearch =
            document.getElementById(
                'chatUserSearch'
            );

        const userResults =
            document.getElementById(
                'chatUserResults'
            );

        const directButtons = [
            document.getElementById(
                'chatStartDirectBtn'
            ),
            document.getElementById(
                'chatStartDirectEmpty'
            ),
        ].filter(Boolean);

        let userSearchTimer =
            null;

        const openUserModal =
            () => {
                if (
                    !canStartDirect
                    || !userModal
                ) {
                    return;
                }

                userModal.hidden =
                    false;

                userSearch?.focus();

                loadDirectUsers(
                    userSearch?.value
                    || ''
                );
            };


        const closeUserModal =
            () => {
                if (userModal) {
                    userModal.hidden =
                        true;
                }
            };


        const renderDirectUsers =
            (users) => {
                if (!userResults) {
                    return;
                }

                if (
                    !Array.isArray(users)
                    || users.length === 0
                ) {
                    userResults.innerHTML =
                        `
                            <div class="chat-user-empty">
                                لا يوجد مستخدمون ضمن نطاقك الإداري مطابقون للبحث.
                            </div>
                        `;

                    return;
                }

                userResults.innerHTML =
                    users
                        .map(
                            (user) => {
                                const firstLetter =
                                    escapeHtml(
                                        (
                                            user.name
                                            || 'م'
                                        )
                                        .trim()
                                        .charAt(0)
                                        .toUpperCase()
                                    );

                                const avatar =
                                    user.avatar
                                        ? `
                                            <img
                                                src="${escapeHtml(user.avatar)}"
                                                alt="${escapeHtml(user.name)}"
                                                loading="lazy"
                                                onerror="this.remove()"
                                            >
                                        `
                                        : '';

                                const subtitle =
                                    [
                                        user.job_title,
                                        user.location_name,
                                        user.username
                                            ? `@${user.username}`
                                            : null,
                                    ]
                                    .filter(Boolean)
                                    .join(' — ');

                                return `
                                    <button
                                        type="button"
                                        class="chat-user-row"
                                        data-direct-user-id="${user.id}"
                                        data-direct-channel-id="${user.channel_id || ''}"
                                    >
                                        <span class="chat-user-avatar">
                                            <span>${firstLetter}</span>
                                            ${avatar}
                                        </span>

                                        <span class="chat-user-info">
                                            <strong>
                                                ${escapeHtml(user.name)}
                                            </strong>

                                            <span>
                                                ${escapeHtml(subtitle)}
                                            </span>
                                        </span>

                                        ${
                                            user.channel_id
                                                ? `
                                                    <span class="chat-user-existing">
                                                        موجودة
                                                    </span>
                                                `
                                                : ''
                                        }
                                    </button>
                                `;
                            }
                        )
                        .join('');
            };


        const loadDirectUsers =
            async (
                search = ''
            ) => {
                if (
                    !canStartDirect
                    || !userResults
                ) {
                    return;
                }

                userResults.innerHTML =
                    `
                        <div class="chat-user-loading">
                            جاري تحميل المستخدمين...
                        </div>
                    `;

                try {
                    const url =
                        new URL(
                            directUsersUrl,
                            window.location.origin
                        );

                    if (search.trim()) {
                        url.searchParams.set(
                            'q',
                            search.trim()
                        );
                    }

                    const response =
                        await fetch(
                            url,
                            {
                                headers: {
                                    'Accept':
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },

                                credentials:
                                    'same-origin',

                                cache:
                                    'no-store',
                            }
                        );

                    const data =
                        await response.json();

                    if (!response.ok) {
                        throw new Error(
                            data.message
                            || 'تعذر تحميل المستخدمين.'
                        );
                    }

                    renderDirectUsers(
                        data.users
                        || []
                    );
                } catch (error) {
                    userResults.innerHTML =
                        `
                            <div class="chat-user-empty">
                                ${escapeHtml(
                                    error.message
                                    || 'تعذر تحميل المستخدمين.'
                                )}
                            </div>
                        `;
                }
            };


        directButtons.forEach(
            (button) =>
                button.addEventListener(
                    'click',
                    openUserModal
                )
        );


        userModalClose?.addEventListener(
            'click',
            closeUserModal
        );


        userModal?.addEventListener(
            'click',
            (event) => {
                if (
                    event.target
                    === userModal
                ) {
                    closeUserModal();
                }
            }
        );


        userSearch?.addEventListener(
            'input',
            () => {
                window.clearTimeout(
                    userSearchTimer
                );

                userSearchTimer =
                    window.setTimeout(
                        () =>
                            loadDirectUsers(
                                userSearch.value
                            ),
                        250
                    );
            }
        );


        userResults?.addEventListener(
            'click',
            async (event) => {
                const button =
                    event.target.closest(
                        '[data-direct-user-id]'
                    );

                if (!button) {
                    return;
                }

                const existingChannelId =
                    Number(
                        button.dataset
                            .directChannelId
                        || 0
                    );

                if (existingChannelId > 0) {
                    window.location.href =
                        `/chat/${existingChannelId}`;

                    return;
                }

                const userId =
                    Number(
                        button.dataset
                            .directUserId
                    );

                if (!userId) {
                    return;
                }

                button.disabled =
                    true;

                try {
                    const url =
                        directStartUrlTemplate
                            .replace(
                                '__USER__',
                                String(userId)
                            );

                    const response =
                        await fetch(
                            url,
                            {
                                method:
                                    'POST',

                                headers: {
                                    'Accept':
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',

                                    'X-CSRF-TOKEN':
                                        csrfToken,
                                },

                                credentials:
                                    'same-origin',
                            }
                        );

                    const data =
                        await response.json();

                    if (!response.ok) {
                        throw new Error(
                            data.message
                            || 'تعذر بدء المحادثة.'
                        );
                    }

                    window.location.href =
                        data.url;
                } catch (error) {
                    showError(
                        error.message
                        || 'تعذر بدء المحادثة.'
                    );

                    button.disabled =
                        false;
                }
            }
        );


        loadMessages(true);

        window.setInterval(
            () => {
                loadMessages(false);
            },
            2500
        );

        window.setInterval(
            markVisibleRead,
            6000
        );

        document.addEventListener(
            'visibilitychange',
            () => {

                if (!document.hidden) {
                    loadMessages(false);
                }
            }
        );

    }
);

</script>

@endpush

@endif
