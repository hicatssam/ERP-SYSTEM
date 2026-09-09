@extends('layouts.app')

@section('title', 'شاشة المطبخ KDS')

@section('content')
<div class="kds-shell" id="kdsShell">
    <div class="kds-topbar">
        <div>
            <h1>شاشة المطبخ KDS</h1>
            <p>{{ $location->name }} — تحديث تلقائي كل {{ $settings['poll_seconds'] }} ثوانٍ</p>
        </div>

        <div class="kds-top-actions">
            @if($locations->count() > 1 || $stations->count() > 1)
                <form method="GET" class="kds-filter-form">
                    @if($locations->count() > 1)
                        <select name="location_id" class="form-select" onchange="this.form.submit()">
                            @foreach($locations as $branch)
                                <option value="{{ $branch->id }}" @selected((int) $branch->id === (int) $location->id)>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="location_id" value="{{ $location->id }}">
                    @endif

                    <select name="station_id" class="form-select" onchange="this.form.submit()">
                        <option value="">كل المحطات</option>
                        @foreach($stations as $station)
                            <option value="{{ $station->id }}" @selected((int) request('station_id') === (int) $station->id)>
                                {{ $station->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif

            @can('kitchen.view')
                <a href="{{ route('kitchen.tickets.index', ['location_id' => $location->id]) }}" class="btn btn-outline">
                    السجل
                </a>
            @endcan

            @can('kitchen.stations.manage')
                <a href="{{ route('kitchen.stations.index', ['location_id' => $location->id]) }}" class="btn btn-outline">
                    المحطات
                </a>
            @endcan

            <button type="button" class="btn btn-ghost" id="kdsFullscreenBtn">
                ملء الشاشة
            </button>
        </div>
    </div>

    @if($stations->isEmpty())
        <div class="card">
            <div class="empty-state">
                <h3>لا توجد محطة مطبخ فعالة</h3>
                <p>أنشئ محطة أو فعّل محطة موجودة قبل استخدام KDS.</p>
                @can('kitchen.stations.manage')
                    <a href="{{ route('kitchen.stations.index', ['location_id' => $location->id]) }}" class="btn btn-gold">
                        إدارة المحطات
                    </a>
                @endcan
            </div>
        </div>
    @else
        <div class="kds-stats">
            <div class="kds-stat">
                <span>بانتظار التحضير</span>
                <strong id="kdsCountQueued">0</strong>
            </div>
            <div class="kds-stat">
                <span>قيد التحضير</span>
                <strong id="kdsCountPreparing">0</strong>
            </div>
            <div class="kds-stat">
                <span>جاهز للتسليم</span>
                <strong id="kdsCountReady">0</strong>
            </div>
            <div class="kds-stat">
                <span>طلبات عاجلة</span>
                <strong id="kdsCountUrgent">0</strong>
            </div>

            <div class="kds-live-indicator" id="kdsConnectionState">
                <i></i>
                <span>جاري الاتصال...</span>
            </div>
        </div>

        <div class="kds-board" id="kdsBoard">
            <div class="kds-loading">
                جاري تحميل تذاكر المطبخ...
            </div>
        </div>
    @endif
</div>

<style>
.kds-shell{--kds-card-min:320px}
.kds-topbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem}
.kds-topbar h1{margin:0;font-size:1.3rem}.kds-topbar p{margin:.25rem 0 0;color:var(--text-muted);font-size:.75rem}
.kds-top-actions,.kds-filter-form{display:flex;align-items:center;gap:.55rem;flex-wrap:wrap}.kds-filter-form .form-select{min-width:150px}
.kds-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr)) auto;gap:.65rem;align-items:stretch;margin-bottom:1rem}
.kds-stat,.kds-live-indicator{border:1px solid var(--border);background:var(--surface);border-radius:12px;padding:.75rem .9rem}
.kds-stat span,.kds-stat strong{display:block}.kds-stat span{font-size:.68rem;color:var(--text-muted)}.kds-stat strong{font-size:1.35rem;margin-top:.2rem}
.kds-live-indicator{display:flex;align-items:center;gap:.5rem;white-space:nowrap;font-size:.7rem;color:var(--text-muted)}
.kds-live-indicator i{width:9px;height:9px;border-radius:50%;background:var(--warning)}.kds-live-indicator.online i{background:var(--success)}.kds-live-indicator.error i{background:var(--danger)}
.kds-board{display:grid;grid-template-columns:repeat(auto-fill,minmax(var(--kds-card-min),1fr));gap:.8rem;align-items:start}
.kds-loading,.kds-empty{grid-column:1/-1;padding:3rem 1rem;text-align:center;color:var(--text-muted);background:var(--surface);border:1px dashed var(--border);border-radius:14px}
.kds-ticket{position:relative;overflow:hidden;border:1px solid var(--border);background:var(--surface);border-radius:14px;box-shadow:0 5px 16px rgba(15,23,42,.05)}
.kds-ticket::before{content:"";position:absolute;inset-inline-start:0;top:0;bottom:0;width:5px;background:var(--info)}
.kds-ticket.status-preparing::before{background:var(--warning)}.kds-ticket.status-ready::before{background:var(--success)}.kds-ticket.timing-warning{border-color:color-mix(in srgb,var(--warning) 55%,var(--border))}.kds-ticket.timing-critical{border-color:color-mix(in srgb,var(--danger) 65%,var(--border));box-shadow:0 5px 20px color-mix(in srgb,var(--danger) 15%,transparent)}
.kds-ticket.urgent{outline:2px solid color-mix(in srgb,var(--danger) 45%,transparent);outline-offset:1px}
.kds-ticket-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.7rem;padding:.85rem .9rem .65rem;border-bottom:1px solid var(--border)}
.kds-ticket-number{font-weight:900;font-size:.92rem}.kds-ticket-order{display:block;margin-top:.2rem;color:var(--text-muted);font-size:.65rem}
.kds-ticket-time{text-align:left;direction:ltr}.kds-ticket-time strong{display:block;font-size:1rem}.kds-ticket-time span{font-size:.6rem;color:var(--text-muted)}
.kds-ticket-meta{display:flex;align-items:center;gap:.35rem;flex-wrap:wrap;padding:.6rem .9rem;border-bottom:1px solid var(--border)}
.kds-pill{display:inline-flex;align-items:center;padding:.25rem .48rem;border-radius:999px;background:rgba(148,163,184,.11);font-size:.62rem;font-weight:700}
.kds-pill.urgent{background:color-mix(in srgb,var(--danger) 12%,transparent);color:var(--danger)}
.kds-items{display:grid;gap:.5rem;padding:.75rem .9rem}.kds-item{display:grid;grid-template-columns:auto minmax(0,1fr);gap:.55rem;align-items:start}
.kds-item-qty{min-width:36px;padding:.3rem .35rem;border-radius:8px;background:rgba(148,163,184,.12);text-align:center;font-size:.72rem;font-weight:900}
.kds-item strong{display:block;font-size:.78rem}.kds-item-note{display:block;margin-top:.2rem;padding:.35rem .45rem;border-radius:7px;background:color-mix(in srgb,var(--warning) 10%,transparent);font-size:.65rem;line-height:1.55}
.kds-order-note{margin:0 .9rem .75rem;padding:.55rem .65rem;border-radius:8px;background:rgba(148,163,184,.08);font-size:.67rem;line-height:1.6}
.kds-ticket-actions{display:flex;gap:.45rem;padding:.7rem .9rem;border-top:1px solid var(--border)}
.kds-ticket-actions .btn{flex:1;justify-content:center;min-height:38px}.kds-priority-btn{flex:0 0 auto!important}
.kds-ticket-footer{display:flex;justify-content:space-between;gap:.6rem;padding:0 .9rem .75rem;color:var(--text-muted);font-size:.6rem}
.kds-flash{position:fixed;left:20px;bottom:20px;z-index:10000;max-width:360px;padding:.8rem 1rem;border-radius:10px;background:var(--surface);border:1px solid var(--border);box-shadow:0 16px 40px rgba(0,0,0,.18);font-size:.75rem}
.kds-flash.error{border-color:var(--danger);color:var(--danger)}
:fullscreen .kds-shell{padding:18px;background:var(--background);height:100vh;overflow:auto}
:fullscreen .kds-topbar{position:sticky;top:0;z-index:50;padding:.6rem;background:color-mix(in srgb,var(--background) 92%,transparent);backdrop-filter:blur(8px)}
@media(max-width:1150px){.kds-stats{grid-template-columns:repeat(4,minmax(0,1fr))}.kds-live-indicator{grid-column:1/-1}}
@media(max-width:760px){.kds-topbar{align-items:flex-start;flex-direction:column}.kds-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.kds-board{--kds-card-min:280px}}
@media(max-width:480px){.kds-stats{grid-template-columns:1fr 1fr}.kds-filter-form{width:100%}.kds-filter-form .form-select{min-width:0;flex:1}}
</style>

@if($stations->isNotEmpty())
<script>
document.addEventListener('DOMContentLoaded', () => {
    const board = document.getElementById('kdsBoard');
    const state = document.getElementById('kdsConnectionState');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const locationId = @json((int) $location->id);
    const stationId = @json(request('station_id') ? (int) request('station_id') : null);
    const pollMs = @json((int) $settings['poll_seconds'] * 1000);
    const soundEnabled = @json((bool) $settings['sound_enabled']);
    const soundVolume = @json((int) $settings['sound_volume'] / 100);
    const userId = @json((int) auth()->id());

    const permissions = {
        start: @json(auth()->user()->can('kitchen.ticket.start')),
        ready: @json(auth()->user()->can('kitchen.ticket.ready')),
        serve: @json(auth()->user()->can('kitchen.ticket.serve')),
        priority: @json(auth()->user()->can('kitchen.ticket.priority')),
    };

    const feedBase = @json(route('kds.feed'));
    const startTemplate = @json(route('kitchen.tickets.start', ['ticket' => '__TICKET__']));
    const readyTemplate = @json(route('kitchen.tickets.ready', ['ticket' => '__TICKET__']));
    const serveTemplate = @json(route('kitchen.tickets.serve', ['ticket' => '__TICKET__']));
    const priorityTemplate = @json(route('kitchen.tickets.priority', ['ticket' => '__TICKET__']));
    const showTemplate = @json(route('kitchen.tickets.show', ['ticket' => '__TICKET__']));

    const seenKey = `kds:last-ticket:${userId}:${locationId}:${stationId || 'all'}`;
    let baselineInitialized = false;
    let lastSeenTicketId = Number(localStorage.getItem(seenKey) || 0);
    let requestRunning = false;
    let audioContext = null;
    let audioUnlocked = false;
    let pendingSoundTicketId = 0;

    const escapeHtml = value => {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    };

    const formatAge = seconds => {
        const total = Math.max(0, Number(seconds || 0));
        const minutes = Math.floor(total / 60);
        const secs = total % 60;

        if (minutes >= 60) {
            const hours = Math.floor(minutes / 60);
            return `${hours}:${String(minutes % 60).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        return `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    };

    const serviceLabel = value => ({
        dine_in: 'داخل المطعم',
        takeaway: 'سفري',
        delivery: 'توصيل',
        phone: 'هاتف',
        web: 'ويب',
    }[value] || value || '—');

    const statusAction = ticket => {
        if (ticket.status === 'queued' && permissions.start) {
            return `<button class="btn btn-gold" data-kds-action="start" data-ticket="${ticket.id}">بدء التحضير</button>`;
        }

        if (ticket.status === 'preparing' && permissions.ready) {
            return `<button class="btn btn-gold" data-kds-action="ready" data-ticket="${ticket.id}">جاهز</button>`;
        }

        if (ticket.status === 'ready' && permissions.serve) {
            return `<button class="btn btn-gold" data-kds-action="serve" data-ticket="${ticket.id}">تم التسليم</button>`;
        }

        return '';
    };

    const renderTicket = ticket => {
        const tableText = ticket.table
            ? `${ticket.table.area ? escapeHtml(ticket.table.area) + ' — ' : ''}${escapeHtml(ticket.table.name)}`
            : escapeHtml(ticket.order.service_type_label || serviceLabel(ticket.order.service_type));

        const items = (ticket.items || []).map(item => `
            <div class="kds-item">
                <span class="kds-item-qty">${Number(item.quantity).toLocaleString('ar', {maximumFractionDigits: 3})}×</span>
                <div>
                    <strong>${escapeHtml(item.name)}</strong>
                    ${item.kitchen_notes ? `<span class="kds-item-note">${escapeHtml(item.kitchen_notes)}</span>` : ''}
                </div>
            </div>
        `).join('');

        const priorityButton = permissions.priority
            ? `
                <button
                    type="button"
                    class="btn btn-ghost kds-priority-btn"
                    data-kds-action="priority"
                    data-ticket="${ticket.id}"
                    data-urgent="${ticket.urgent ? 0 : 1}"
                    title="${ticket.urgent ? 'إلغاء العاجل' : 'تحديد كعاجل'}"
                >
                    ${ticket.urgent ? '★' : '☆'}
                </button>
            `
            : '';

        return `
            <article
                class="kds-ticket status-${escapeHtml(ticket.status)} timing-${escapeHtml(ticket.timing)} ${ticket.urgent ? 'urgent' : ''}"
                data-ticket-id="${ticket.id}"
                data-age="${ticket.age_seconds}"
            >
                <div class="kds-ticket-head">
                    <div>
                        <span class="kds-ticket-number">${escapeHtml(ticket.order.number || ticket.ticket_number)}</span>
                        <span class="kds-ticket-order">${escapeHtml(ticket.ticket_number)} — ${escapeHtml(ticket.station.name || '')}</span>
                    </div>
                    <div class="kds-ticket-time">
                        <strong data-ticket-age>${formatAge(ticket.age_seconds)}</strong>
                        <span>منذ الوصول</span>
                    </div>
                </div>

                <div class="kds-ticket-meta">
                    <span class="kds-pill">${tableText}</span>
                    ${ticket.order.guest_count ? `<span class="kds-pill">${ticket.order.guest_count} ضيف</span>` : ''}
                    ${ticket.order.waiter ? `<span class="kds-pill">${escapeHtml(ticket.order.waiter)}</span>` : ''}
                    ${ticket.urgent ? '<span class="kds-pill urgent">عاجل</span>' : ''}
                </div>

                <div class="kds-items">${items}</div>

                ${ticket.order.notes ? `<div class="kds-order-note"><strong>ملاحظة:</strong> ${escapeHtml(ticket.order.notes)}</div>` : ''}

                <div class="kds-ticket-actions">
                    ${statusAction(ticket)}
                    ${priorityButton}
                    <a class="btn btn-ghost" href="${showTemplate.replace('__TICKET__', ticket.id)}">تفاصيل</a>
                </div>

                <div class="kds-ticket-footer">
                    <span>${escapeHtml(ticket.status_label || ticket.status)}</span>
                    <span>${ticket.station.target_minutes ? `الهدف ${ticket.station.target_minutes} د` : ''}</span>
                </div>
            </article>
        `;
    };

    const render = data => {
        document.getElementById('kdsCountQueued').textContent = data.counts?.queued ?? 0;
        document.getElementById('kdsCountPreparing').textContent = data.counts?.preparing ?? 0;
        document.getElementById('kdsCountReady').textContent = data.counts?.ready ?? 0;
        document.getElementById('kdsCountUrgent').textContent = data.counts?.urgent ?? 0;

        const tickets = Array.isArray(data.tickets) ? data.tickets : [];

        board.innerHTML = tickets.length
            ? tickets.map(renderTicket).join('')
            : '<div class="kds-empty">لا توجد تذاكر نشطة الآن.</div>';

        const newestTicketId = tickets
            .reduce((max, ticket) => Math.max(max, Number(ticket.id || 0)), 0);

        if (!baselineInitialized) {
            // Initial load is a baseline only: never beep for old tickets.
            lastSeenTicketId = Math.max(lastSeenTicketId, newestTicketId);
            localStorage.setItem(seenKey, String(lastSeenTicketId));
            baselineInitialized = true;
        } else if (newestTicketId > lastSeenTicketId) {
            pendingSoundTicketId = newestTicketId;
            playIncomingSound(newestTicketId);
            lastSeenTicketId = newestTicketId;
            localStorage.setItem(seenKey, String(lastSeenTicketId));
        }

        state.className = 'kds-live-indicator online';
        state.innerHTML = '<i></i><span>متصل — آخر تحديث الآن</span>';
    };

    const unlockAudio = async () => {
        if (!soundEnabled) return;

        try {
            audioContext ||= new (window.AudioContext || window.webkitAudioContext)();
            if (audioContext.state === 'suspended') {
                await audioContext.resume();
            }
            audioUnlocked = audioContext.state === 'running';

            if (audioUnlocked && pendingSoundTicketId > 0) {
                const ticketId = pendingSoundTicketId;
                pendingSoundTicketId = 0;
                await playIncomingSound(ticketId);
            }
        } catch (_) {
            audioUnlocked = false;
        }
    };

    const playIncomingSound = async messageId => {
        if (!soundEnabled) return false;

        if (!audioUnlocked || !audioContext) {
            pendingSoundTicketId = Math.max(
                pendingSoundTicketId,
                Number(messageId || 0)
            );
            return false;
        }

        pendingSoundTicketId = 0;
        const now = audioContext.currentTime;
        const gain = audioContext.createGain();
        gain.gain.value = Math.max(.01, Math.min(1, soundVolume)) * .18;
        gain.connect(audioContext.destination);

        [
            [740, 0, .13],
            [990, .16, .16],
            [1240, .35, .20],
        ].forEach(([frequency, offset, duration]) => {
            const oscillator = audioContext.createOscillator();
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(frequency, now + offset);
            oscillator.connect(gain);
            oscillator.start(now + offset);
            oscillator.stop(now + offset + duration);
        });

        return true;
    };

    const fetchFeed = async () => {
        if (requestRunning) return;
        requestRunning = true;

        try {
            const url = new URL(feedBase, window.location.origin);
            url.searchParams.set('location_id', String(locationId));
            if (stationId) url.searchParams.set('station_id', String(stationId));

            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            render(await response.json());
        } catch (error) {
            state.className = 'kds-live-indicator error';
            state.innerHTML = '<i></i><span>تعذر التحديث — إعادة المحاولة تلقائيًا</span>';
        } finally {
            requestRunning = false;
        }
    };

    const flash = (message, error = false) => {
        document.querySelector('.kds-flash')?.remove();
        const el = document.createElement('div');
        el.className = `kds-flash ${error ? 'error' : ''}`;
        el.textContent = message;
        document.body.appendChild(el);
        window.setTimeout(() => el.remove(), 2600);
    };

    const performAction = async button => {
        const action = button.dataset.kdsAction;
        const ticketId = button.dataset.ticket;
        if (!action || !ticketId) return;

        let template = null;
        let payload = null;

        if (action === 'start') template = startTemplate;
        if (action === 'ready') template = readyTemplate;
        if (action === 'serve') template = serveTemplate;
        if (action === 'priority') {
            template = priorityTemplate;
            payload = new URLSearchParams({urgent: button.dataset.urgent || '0'});
        }

        if (!template) return;

        button.disabled = true;

        try {
            const response = await fetch(template.replace('__TICKET__', ticketId), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                    ...(payload ? {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'} : {}),
                },
                credentials: 'same-origin',
                body: payload,
            });

            const data = await response.json();

            if (!response.ok) {
                const message = data.message
                    || Object.values(data.errors || {}).flat().join(' ')
                    || 'تعذر تنفيذ العملية.';
                throw new Error(message);
            }

            flash(data.message || 'تم تحديث التذكرة.');
            await fetchFeed();
        } catch (error) {
            flash(error.message || 'تعذر تنفيذ العملية.', true);
        } finally {
            button.disabled = false;
        }
    };

    board.addEventListener('click', event => {
        const button = event.target.closest('[data-kds-action]');
        if (button) performAction(button);
    });

    document.getElementById('kdsFullscreenBtn')?.addEventListener('click', async () => {
        try {
            if (!document.fullscreenElement) {
                await document.getElementById('kdsShell').requestFullscreen();
            } else {
                await document.exitFullscreen();
            }
        } catch (_) {}
    });

    ['pointerdown', 'keydown'].forEach(eventName => {
        window.addEventListener(eventName, unlockAudio, {once: true});
    });

    window.setInterval(() => {
        board.querySelectorAll('[data-ticket-age]').forEach(ticketEl => {
            const ageEl = ticketEl.querySelector('[data-ticket-age]');
            if (!ageEl) return;
            const next = Number(ticketEl.dataset.age || 0) + 1;
            ticketEl.dataset.age = String(next);
            ageEl.textContent = formatAge(next);
        });
    }, 1000);

    fetchFeed();
    window.setInterval(fetchFeed, pollMs);
});
</script>
@endif
@endsection
