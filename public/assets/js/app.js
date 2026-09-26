/* Load the shared date formatter for any secondary layout that only includes app.js. */
(() => {
    if (
        window.DahabDateFormatter
        || document.querySelector(
            'script[data-dahab-date-loader]'
        )
    ) {
        return;
    }

    const appScript = Array.from(
        document.scripts
    ).find(script =>
        script.src.includes(
            '/assets/js/app.js'
        )
    );

    const version = (() => {
        try {
            return appScript
                ? new URL(appScript.src)
                    .searchParams
                    .get('v')
                : null;
        } catch (_) {
            return null;
        }
    })();

    const script =
        document.createElement('script');

    script.dataset.dahabDateLoader = '1';
    script.src =
        '/assets/js/date-format.js'
        + (
            version
                ? '?v='
                    + encodeURIComponent(version)
                : ''
        );

    document.head.appendChild(script);
})();

/* ═══════════════════════════════════════════════
   DAHAB SWEETS — App JavaScript v2
   ═══════════════════════════════════════════════ */

// ── Sidebar + Overlay ───────────────────────────
const sidebar       = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const overlay       = document.getElementById('sidebarOverlay');

function openSidebar() {
    sidebar?.classList.add('open');
    overlay?.classList.add('open');
    document.body.style.overflow = 'hidden'; // prevent scroll behind overlay on mobile
}

function closeSidebar() {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('open');
    document.body.style.overflow = '';
}

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
    });
}

overlay?.addEventListener('click', closeSidebar);

// Close sidebar on resize to desktop
window.addEventListener('resize', () => {
    if (window.innerWidth > 768) closeSidebar();
});

// ── Notification Dropdown ───────────────────────
const notifBtn      = document.getElementById('notifBtn');
const notifDropdown = document.getElementById('notifDropdown');

// ── Notification type → icon SVG map ────────────
const _notifIcons = {
    low_stock_detected: `<svg viewBox="0 0 24 24" fill="none" stroke="#B8892E" stroke-width="2" width="16" height="16"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    order_created: `<svg viewBox="0 0 24 24" fill="none" stroke="#16283A" stroke-width="2" width="16" height="16"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>`,
    order_status_changed: `<svg viewBox="0 0 24 24" fill="none" stroke="#16283A" stroke-width="2" width="16" height="16"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>`,
    payment_received: `<svg viewBox="0 0 24 24" fill="none" stroke="#065F46" stroke-width="2" width="16" height="16"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>`,
    special_cake_order_transitioned: `<svg viewBox="0 0 24 24" fill="none" stroke="#B8892E" stroke-width="2" width="16" height="16"><path d="M20 11.08V8l-6-6H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h6"/><path d="M14 3v5h5M18 21v-6M15 18h6"/></svg>`,
};

function _renderNotifItems(items) {
    const list = document.getElementById('notifList');
    if (!list) return;
    if (!items || items.length === 0) {
        list.innerHTML = '<div class="notif-empty">لا توجد إشعارات جديدة</div>';
        return;
    }
    list.innerHTML = items.map(n => {
        const icon   = _notifIcons[n.type] || _notifIcons['order_created'];
        const href   = n.url ? n.url : '#';
        return `<a class="notif-item" href="${href}" data-id="${n.id}">
            <span class="notif-item-icon">${icon}</span>
            <span class="notif-item-body">
                <span class="notif-item-msg">${n.message}</span>
                <span class="notif-item-time">${n.time}</span>
            </span>
        </a>`;
    }).join('');

    // Mark individual as read on click
    list.querySelectorAll('.notif-item[data-id]').forEach(el => {
        el.addEventListener('click', async () => {
            const id   = el.dataset.id;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            try { await fetch(`/notifications/${id}/read`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } }); } catch {}
        });
    });
}

async function _loadRecentNotifs() {
    try {
        const res  = await fetch('/notifications/recent', { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        _renderNotifItems(data.items || []);
    } catch {
        const list = document.getElementById('notifList');
        if (list) list.innerHTML = '<div class="notif-empty">تعذّر تحميل الإشعارات</div>';
    }
}

let _notifLoaded = false;
if (notifBtn && notifDropdown) {
    notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const wasOpen = notifDropdown.classList.contains('open');
        notifDropdown.classList.toggle('open');
        userDropdown?.classList.remove('open');
        // Load on first open, or every time it opens
        if (!wasOpen) {
            _loadRecentNotifs();
        }
    });
}

// ── User Dropdown ───────────────────────────────
const userMenuBtn  = document.getElementById('userMenuBtn');
const userDropdown = document.getElementById('userDropdown');

if (userMenuBtn && userDropdown) {
    userMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        userDropdown.classList.toggle('open');
        notifDropdown?.classList.remove('open');
    });
}

// Close all dropdowns on outside click
document.addEventListener('click', () => {
    notifDropdown?.classList.remove('open');
    userDropdown?.classList.remove('open');
});

// ── Mark All Notifications Read ─────────────────
const markAllRead = document.getElementById('markAllRead');
if (markAllRead) {
    markAllRead.addEventListener('click', async (e) => {
        e.preventDefault();
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        try {
            await fetch('/notifications/read-all', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            const badge = document.getElementById('notifCount');
            if (badge) { badge.style.display = 'none'; badge.textContent = '0'; }
            const list = document.getElementById('notifList');
            if (list) list.innerHTML = '<div class="notif-empty">لا توجد إشعارات جديدة</div>';
        } catch {}
    });
}

// ── Notification Sound (Web Audio API — no file needed) ─────
let _notifAudioCtx = null;

function playNotificationSound() {
    try {
        if (!_notifAudioCtx) {
            _notifAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        const ctx = _notifAudioCtx;

        // Two-note soft chime: high C then E
        const notes = [
            { freq: 1046.5, startAt: 0.00, duration: 0.18 },   // C6
            { freq: 1318.5, startAt: 0.16, duration: 0.22 },   // E6
        ];

        notes.forEach(({ freq, startAt, duration }) => {
            const osc  = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type      = 'sine';
            osc.frequency.setValueAtTime(freq, ctx.currentTime + startAt);

            gain.gain.setValueAtTime(0,    ctx.currentTime + startAt);
            gain.gain.linearRampToValueAtTime(0.22, ctx.currentTime + startAt + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + startAt + duration);

            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(ctx.currentTime + startAt);
            osc.stop(ctx.currentTime  + startAt + duration + 0.05);
        });
    } catch (_) {}
}

// ── Fetch Unread Notification Count ────────────
let _prevNotifCount = null;

async function refreshNotifCount() {
    try {
        const res = await fetch('/notifications/unread-count', { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        const badge = document.getElementById('notifCount');
        if (badge) {
            badge.textContent = data.count;
            badge.style.display = data.count > 0 ? 'flex' : 'none';
        }
        // Play sound when new notifications arrive (count increased since last poll)
        if (_prevNotifCount !== null && data.count > _prevNotifCount) {
            playNotificationSound();
        }
        _prevNotifCount = data.count;
    } catch {}
}

if (document.getElementById('notifCount')) {
    refreshNotifCount();
    setInterval(refreshNotifCount, 30000);
}

// ── Auto-dismiss Toasts ─────────────────────────
document.querySelectorAll('[data-auto-dismiss]').forEach(toast => {
    setTimeout(() => toast.remove(), 5000);
});

// ── Confirmation Modal ──────────────────────────
function openConfirmModal({ title, body, onConfirm }) {
    const modal = document.getElementById('confirmModal');
    if (!modal) return;
    document.getElementById('confirmTitle').textContent = title || 'تأكيد العملية';
    document.getElementById('confirmBody').textContent  = body  || 'هل أنت متأكد؟';
    const btn = document.getElementById('confirmBtn');
    btn.onclick = () => { closeConfirmModal(); onConfirm(); };
    modal.style.display = 'flex';
}

function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    if (modal) modal.style.display = 'none';
}

document.getElementById('confirmModal')?.addEventListener('click', (e) => {
    if (e.target === e.currentTarget) closeConfirmModal();
});

// ── Toggle Password Visibility ──────────────────
function togglePassword(id, btn) {
    const input = document.getElementById(id);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    // Swap icon (eye / eye-off)
    if (btn) {
        btn.querySelector('svg')?.setAttribute('opacity', isPassword ? '1' : '.5');
    }
}

// ── Confirm Submit ──────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', (e) => {
        e.preventDefault();
        const message = el.dataset.confirm || 'هل أنت متأكد؟';
        const form = el.closest('form');
        openConfirmModal({
            title: 'تأكيد العملية',
            body: message,
            onConfirm: () => form ? form.submit() : (window.location.href = el.href)
        });
    });
});

// ── CSRF helper ─────────────────────────────────
function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

// ── POST via fetch ──────────────────────────────
async function postAction(url, data = {}) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrf(),
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    });
    return res.json();
}

// ── Client-side table search ────────────────────
document.querySelectorAll('[data-search-table]').forEach(input => {
    const tableId = input.dataset.searchTable;
    const table = document.getElementById(tableId);
    if (!table) return;
    input.addEventListener('input', () => {
        const q = input.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
});

// ── Show inline toast ──────────────────────────
function showToast(message, type = 'success') {
    const container = document.getElementById('flashContainer');
    if (!container) return;
    const icons = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
        error:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>'
    };
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `${icons[type] || icons.success}<span>${message}</span><button class="toast-close" onclick="this.parentElement.remove()">×</button>`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}
