@php
    $chatSystemEnabled = app(\App\Services\ModuleService::class)
        ->isEnabled('chat');

    $chatSoundEnabled = (bool) \App\Models\SystemSetting::get(
        'chat_message_sound_enabled',
        true
    );

    $chatSoundVolume = max(
        0,
        min(
            100,
            (int) \App\Models\SystemSetting::get(
                'chat_message_sound_volume',
                65
            )
        )
    );

    $chatAvailable =
        auth()->check()
        && (bool) auth()->user()->is_active;
@endphp

@if(
    $chatSystemEnabled
    && $chatAvailable
    && \Illuminate\Support\Facades\Route::has('chat.index')
    && \Illuminate\Support\Facades\Route::has('chat.unread-count')
)

    <div
        class="chat-topbar-wrapper"
        id="chatTopbarWrapper"
    >
        <a
            href="{{ route('chat.index') }}"
            class="chat-topbar-btn"
            id="chatTopbarBtn"
            aria-label="المحادثات"
            title="محادثات الفروع"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" />
                <path d="M8 9h8" />
                <path d="M8 13h5" />
            </svg>

            <span
                class="chat-topbar-badge"
                id="chatTopbarBadge"
                style="display:none"
            >
                0
            </span>
        </a>
    </div>

    @once
        <style>
            .chat-topbar-wrapper {
                position: relative;
            }

            .chat-topbar-btn {
                position: relative;
                width: 40px;
                height: 40px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: var(--theme-text, var(--text));
                background: transparent;
                border: 0;
                border-radius: 10px;
                text-decoration: none;
                transition: .18s ease;
            }

            .chat-topbar-btn:hover {
                color: var(--theme-accent, var(--gold));
                background: color-mix(
                    in srgb,
                    var(--theme-accent, var(--gold)) 10%,
                    transparent
                );
            }

            .chat-topbar-btn svg {
                width: 21px;
                height: 21px;
            }

            .chat-topbar-badge {
                position: absolute;
                top: 1px;
                left: 1px;
                min-width: 18px;
                height: 18px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0 5px;
                color: #fff;
                background: var(--theme-danger, var(--danger));
                border: 2px solid var(--theme-header-bg, #fff);
                border-radius: 999px;
                font-size: .56rem;
                font-weight: 800;
                line-height: 1;
            }
        </style>

        <script>
            document.addEventListener(
                'DOMContentLoaded',
                () => {
                    const badge =
                        document.getElementById(
                            'chatTopbarBadge'
                        );

                    if (!badge) {
                        return;
                    }

                    const unreadUrl =
                        @json(
                            route(
                                'chat.unread-count'
                            )
                        );

                    const soundEnabled =
                        @json($chatSoundEnabled);

                    const soundVolume =
                        Math.max(
                            0,
                            Math.min(
                                1,
                                @json($chatSoundVolume) / 100
                            )
                        );

                    const soundStateKey =
                        'chat:last-incoming-message:'
                        + @json(auth()->id());

                    let previousIncomingMessageId =
                        Number(
                            sessionStorage.getItem(
                                soundStateKey
                            )
                            || 0
                        );

                    let soundBaselineInitialized =
                        previousIncomingMessageId > 0;

                    let requestRunning = false;

                    /*
                     * AudioContext لا يعمل قبل أول تفاعل من المستخدم
                     * في أغلب المتصفحات.
                     */
                    let audioContext = null;
                    let audioUnlocked = false;
                    let lastPlayedAt = 0;

                    const unlockChatAudio =
                        async () => {
                            if (
                                !soundEnabled
                                || audioUnlocked
                            ) {
                                return;
                            }

                            const AudioContextClass =
                                window.AudioContext
                                || window.webkitAudioContext;

                            if (!AudioContextClass) {
                                return;
                            }

                            audioContext ??=
                                new AudioContextClass();

                            if (
                                audioContext.state
                                === 'suspended'
                            ) {
                                await audioContext.resume();
                            }

                            audioUnlocked =
                                audioContext.state
                                === 'running';
                        };

                    /*
                     * صوت قصير مستقل للمحادثات.
                     * لا يعتمد على ملف صوت خارجي.
                     */
                    const playChatMessageSound =
                        async (
                            messageId = null
                        ) => {
                            if (!soundEnabled) {
                                return false;
                            }

                            const normalizedMessageId =
                                Number(
                                    messageId || 0
                                );

                            if (
                                normalizedMessageId > 0
                                && Number(
                                    window.__chatLastSoundMessageId
                                    || 0
                                ) === normalizedMessageId
                            ) {
                                return true;
                            }

                            /*
                             * يمنع الصوت المكرر إذا اكتشفته صفحة الشات
                             * والـTopbar في نفس اللحظة.
                             */
                            const nowMs = Date.now();

                            if (
                                nowMs - lastPlayedAt
                                < 900
                            ) {
                                return false;
                            }

                            await unlockChatAudio();

                            if (
                                !audioUnlocked
                                || !audioContext
                            ) {
                                return false;
                            }

                            lastPlayedAt =
                                nowMs;

                            if (
                                normalizedMessageId > 0
                            ) {
                                window.__chatLastSoundMessageId =
                                    normalizedMessageId;
                            }

                            const start =
                                audioContext.currentTime;

                            const master =
                                audioContext.createGain();

                            master.connect(
                                audioContext.destination
                            );

                            master.gain.setValueAtTime(
                                0.0001,
                                start
                            );

                            master.gain.exponentialRampToValueAtTime(
                                Math.max(
                                    0.015,
                                    0.22 * soundVolume
                                ),
                                start + 0.012
                            );

                            master.gain.exponentialRampToValueAtTime(
                                0.0001,
                                start + 0.48
                            );

                            const notes = [
                                {
                                    frequency: 740,
                                    offset: 0,
                                    duration: .21,
                                    type: 'sine',
                                },
                                {
                                    frequency: 990,
                                    offset: .105,
                                    duration: .24,
                                    type: 'triangle',
                                },
                            ];

                            notes.forEach(
                                (note) => {
                                    const oscillator =
                                        audioContext
                                            .createOscillator();

                                    const noteGain =
                                        audioContext
                                            .createGain();

                                    oscillator.type =
                                        note.type;

                                    oscillator.frequency
                                        .setValueAtTime(
                                            note.frequency,
                                            start + note.offset
                                        );

                                    noteGain.gain
                                        .setValueAtTime(
                                            0.8,
                                            start + note.offset
                                        );

                                    noteGain.gain
                                        .exponentialRampToValueAtTime(
                                            0.0001,
                                            start
                                            + note.offset
                                            + note.duration
                                        );

                                    oscillator.connect(
                                        noteGain
                                    );

                                    noteGain.connect(
                                        master
                                    );

                                    oscillator.start(
                                        start + note.offset
                                    );

                                    oscillator.stop(
                                        start
                                        + note.offset
                                        + note.duration
                                        + .03
                                    );
                                }
                            );

                            return true;
                        };

                    /*
                     * نجعلها متاحة لصفحة المحادثة نفسها.
                     * إذا كانت القناة مفتوحة يتم تشغيل الصوت
                     * فور وصول الرسالة حتى لو تحولت الرسالة
                     * إلى مقروءة بسرعة.
                     */
                    window.playChatMessageSound =
                        playChatMessageSound;

                    const updateBadge =
                        (count) => {
                            const value =
                                Number(
                                    count || 0
                                );

                            badge.textContent =
                                value > 99
                                    ? '99+'
                                    : String(value);

                            badge.style.display =
                                value > 0
                                    ? 'inline-flex'
                                    : 'none';
                        };

                    const checkUnread =
                        async () => {
                            if (
                                requestRunning
                            ) {
                                return;
                            }

                            requestRunning = true;

                            try {
                                const response =
                                    await fetch(
                                        unreadUrl,
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

                                const count =
                                    Number(
                                        data.unread_count
                                        || 0
                                    );

                                const latestIncomingMessageId =
                                    Number(
                                        data
                                            .latest_incoming_message_id
                                        || 0
                                    );

                                updateBadge(
                                    count
                                );

                                /*
                                 * أول تحميل:
                                 * نسجل آخر رسالة فقط حتى لا نصدر صوتًا
                                 * بسبب رسائل قديمة.
                                 */
                                if (
                                    !soundBaselineInitialized
                                ) {
                                    previousIncomingMessageId =
                                        latestIncomingMessageId;

                                    sessionStorage.setItem(
                                        soundStateKey,
                                        String(
                                            latestIncomingMessageId
                                        )
                                    );

                                    soundBaselineInitialized =
                                        true;
                                } else if (
                                    latestIncomingMessageId > 0
                                    && latestIncomingMessageId
                                        > previousIncomingMessageId
                                ) {
                                    const played =
                                        await playChatMessageSound(
                                            latestIncomingMessageId
                                        );

                                    /*
                                     * إذا المتصفح منع الصوت لأنه لم يحدث
                                     * تفاعل بعد، لا نحدث baseline.
                                     * بعد أول Click/Key سيتم تشغيله
                                     * في الفحص التالي.
                                     */
                                    if (played) {
                                        previousIncomingMessageId =
                                            latestIncomingMessageId;

                                        sessionStorage.setItem(
                                            soundStateKey,
                                            String(
                                                latestIncomingMessageId
                                            )
                                        );
                                    }
                                }

                            } catch (error) {
                                console.debug(
                                    'Chat unread check failed.',
                                    error
                                );
                            } finally {
                                requestRunning =
                                    false;
                            }
                        };

                    const unlockAndRecheck =
                        async () => {
                            await unlockChatAudio();

                            /*
                             * لو وصلت رسالة قبل السماح بالصوت،
                             * افحص مباشرة بعد أول تفاعل.
                             */
                            window.setTimeout(
                                checkUnread,
                                50
                            );
                        };

                    window.addEventListener(
                        'pointerdown',
                        unlockAndRecheck,
                        {
                            once: true,
                        }
                    );

                    window.addEventListener(
                        'keydown',
                        unlockAndRecheck,
                        {
                            once: true,
                        }
                    );

                    checkUnread();

                    window.setInterval(
                        checkUnread,
                        3000
                    );

                    document.addEventListener(
                        'visibilitychange',
                        () => {
                            if (!document.hidden) {
                                checkUnread();
                            }
                        }
                    );
                }
            );
        </script>
    @endonce

@endif