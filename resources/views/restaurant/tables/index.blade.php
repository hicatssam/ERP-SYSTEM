@extends('layouts.app')

@section('title', 'طاولات المطعم')

@section('content')
<div class="page-actions">
    <div>
        <div class="page-actions-title">طاولات المطعم</div>
        <div style="margin-top:.25rem;color:var(--text-muted);font-size:.78rem">
            {{ $location->name }} — المناطق والطاولات والجلسات المفتوحة
        </div>
    </div>

    <div class="action-btns">
        @can('restaurant_pos.use')
            <a href="{{ route('restaurant.pos.index', ['location_id' => $location->id]) }}" class="btn btn-gold">
                نقطة البيع
            </a>
        @endcan
        <a href="{{ route('restaurant.dashboard', ['location_id' => $location->id]) }}" class="btn btn-ghost">اللوحة</a>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        <strong>تعذر تنفيذ العملية:</strong>
        <ul style="margin:.5rem 0 0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($locations->count() > 1)
<div class="filter-row" style="margin-bottom:1rem">
    <form method="GET" class="filter-grid">
        <div class="filter-group">
            <label class="filter-label">الفرع</label>
            <select name="location_id" class="form-select" onchange="this.form.submit()">
                @foreach($locations as $branch)
                    <option value="{{ $branch->id }}" @selected((int) $branch->id === (int) $location->id)>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>
</div>
@endif

@php
    $sections = $areas->map(fn($area) => [
        'title' => $area->name,
        'tables' => $area->tables,
    ]);

    if($unassignedTables->isNotEmpty()) {
        $sections->push([
            'title' => 'بدون منطقة',
            'tables' => $unassignedTables,
        ]);
    }
@endphp

@php
    $allTables = $sections->flatMap(fn($section) => $section['tables']);
    $occupiedCount = $allTables->filter(fn($table) => $table->activeSession !== null)->count();
    $disabledCount = $allTables->where('is_active', false)->count();
    $freeCount = $allTables->count() - $occupiedCount - $disabledCount;
@endphp

<div class="table-live-summary" aria-label="ملخص حالة الطاولات">
    <div class="table-live-stat total"><span>إجمالي الطاولات</span><strong>{{ $allTables->count() }}</strong><small>طاولة</small></div>
    <div class="table-live-stat occupied"><span>مشغولة الآن</span><strong id="occupied-count">{{ $occupiedCount }}</strong><small>قيد الخدمة</small></div>
    <div class="table-live-stat free"><span>متاحة الآن</span><strong id="free-count">{{ $freeCount }}</strong><small>جاهزة للاستقبال</small></div>
    <div class="table-live-stat disabled"><span>معطلة</span><strong id="disabled-count">{{ $disabledCount }}</strong><small>خارج الخدمة</small></div>
    <div class="table-live-connection"><i></i><span>تحديث مباشر كل 10 ثوانٍ</span></div>
</div>

@can('restaurant_tables.manage')
<details class="table-management" @if($errors->any()) open @endif>
    <summary>
        <span>
            <strong>إدارة المناطق والطاولات</strong>
            <small>إضافة منطقة أو تجهيز طاولة جديدة</small>
        </span>
        <span class="management-toggle">فتح الإعدادات</span>
    </summary>

    <div class="restaurant-config-grid">
        <form method="POST" action="{{ route('restaurant.tables.areas.store') }}" class="management-form">
            @csrf
            <input type="hidden" name="location_id" value="{{ $location->id }}">
            <div class="management-form-title">إضافة منطقة</div>
            <div class="compact-form-grid area-form-grid">
                <div class="form-group">
                    <label class="form-label">اسم المنطقة *</label>
                    <input type="text" name="name" class="form-input" placeholder="مثال: الصالة الرئيسية" required>
                </div>
                <div class="form-group">
                    <label class="form-label">الكود</label>
                    <input type="text" name="code" class="form-input" placeholder="مثال: رئيسية">
                </div>
                <button class="btn btn-gold" type="submit">حفظ المنطقة</button>
            </div>
        </form>

        <form method="POST" action="{{ route('restaurant.tables.store') }}" class="management-form">
            @csrf
            <input type="hidden" name="location_id" value="{{ $location->id }}">
            <div class="management-form-title">إضافة طاولة</div>
            <div class="compact-form-grid table-create-grid">
                <div class="form-group">
                    <label class="form-label">كود الطاولة *</label>
                    <input type="text" name="code" class="form-input" placeholder="مثال: ط-01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">اسم الطاولة</label>
                    <input type="text" name="name" class="form-input" placeholder="مثال: طاولة العائلة">
                </div>
                <div class="form-group">
                    <label class="form-label">المنطقة</label>
                    <select name="area_id" class="form-select">
                        <option value="">بدون منطقة</option>
                        @foreach($areas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">عدد المقاعد *</label>
                    <input type="number" min="1" max="100" name="capacity" class="form-input" value="4" required>
                </div>
                <button class="btn btn-gold" type="submit">حفظ الطاولة</button>
            </div>
        </form>
    </div>
</details>
@endcan

@forelse($sections as $section)
    <div class="card table-area-card">
        <div class="card-header table-area-header">
            <span class="card-title"><span class="area-icon">▦</span>{{ $section['title'] }}</span>
            <span class="area-count">
                {{ $section['tables']->count() }} طاولة
            </span>
        </div>

        <div class="card-body">
            <div class="restaurant-tables-grid">
                @foreach($section['tables'] as $table)
                    @php
                        $session = $table->activeSession;
                        $occupied = (bool) $session;
                        $openOrders = $session
                            ? $session->orders->filter(
                                fn($order) => !in_array(
                                    $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
                                    ['completed','cancelled'],
                                    true
                                )
                            )->count()
                            : 0;
                    @endphp

                    <div
                        class="restaurant-table-card {{ $occupied ? 'is-occupied' : 'is-free' }} {{ !$table->is_active ? 'is-disabled' : '' }}"
                        data-table-id="{{ $table->id }}"
                    >
                        <div class="table-card-head">
                            <div>
                                <strong><span class="table-state-dot"></span>{{ $table->displayName() }}</strong>
                                <small>الكود: {{ $table->code }} · {{ $table->capacity }} مقاعد</small>
                            </div>
                            <span class="table-status">
                                {{ !$table->is_active ? 'معطلة' : ($occupied ? 'مشغولة' : 'متاحة') }}
                            </span>
                        </div>

                        @if($occupied)
                            <div class="table-session-info">
                                <span class="session-chip guest-count">{{ $session->guest_count }} ضيوف</span>
                                <span class="session-chip open-orders">{{ $openOrders }} طلب مفتوح</span>
                                <span class="session-duration" data-opened-at="{{ $session->opened_at?->toIso8601String() }}">منذ {{ $session->opened_at?->format('H:i') }}</span>
                            </div>
                        @else
                            <div class="table-session-info muted">
                                الطاولة جاهزة لاستقبال الضيوف
                            </div>
                        @endif

                        <div class="table-card-actions">
                            @if($table->is_active && !$occupied)
                                @can('restaurant_tables.open_session')
                                    <form method="POST" action="{{ route('restaurant.tables.open', $table) }}" class="inline-session-form">
                                        @csrf
                                        <input type="hidden" name="location_id" value="{{ $location->id }}">
                                        <input type="number" name="guest_count" min="1" max="100" value="2" class="mini-input" title="عدد الضيوف">
                                        <button class="btn btn-gold btn-sm" type="submit">فتح الطاولة</button>
                                    </form>
                                @endcan
                            @elseif($occupied)
                                @can('restaurant_pos.use')
                                    <a href="{{ route('restaurant.pos.index', [
                                        'location_id' => $location->id,
                                        'table_id' => $table->id,
                                    ]) }}" class="btn btn-outline btn-sm">
                                        فتح الطلبات
                                    </a>
                                @endcan

                                @can('restaurant_tables.close_session')
                                    <form method="POST" action="{{ route('restaurant.tables.close', $table) }}" onsubmit="return confirm('هل تم إخلاء الطاولة وإنهاء جميع طلباتها؟')">
                                        @csrf
                                        <input type="hidden" name="location_id" value="{{ $location->id }}">
                                        <button class="btn btn-ghost btn-sm" type="submit">إخلاء الطاولة</button>
                                    </form>
                                @endcan
                            @endif

                            @can('restaurant_tables.manage')
                                <form method="POST" action="{{ route('restaurant.tables.toggle', $table) }}">
                                    @csrf
                                    <input type="hidden" name="location_id" value="{{ $location->id }}">
                                    <button class="btn btn-ghost btn-sm" type="submit">
                                        {{ $table->is_active ? 'تعطيل' : 'تفعيل' }}
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@empty
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <h3>لا توجد مناطق أو طاولات</h3>
                <p>أضف أول منطقة وطاولة لبدء تشغيل الجلسات.</p>
            </div>
        </div>
    </div>
@endforelse

<style>
.table-live-summary{display:grid;grid-template-columns:repeat(4,minmax(145px,1fr)) minmax(180px,.8fr);gap:.75rem;margin:1rem 0}
.table-live-stat,.table-live-connection{position:relative;border:1px solid var(--border);border-radius:16px;background:var(--surface);padding:.9rem 1rem;min-height:82px;display:grid;grid-template-columns:1fr auto;align-items:center;gap:.15rem .75rem;overflow:hidden}
.table-live-stat::before{content:"";position:absolute;inset-block:0;inset-inline-start:0;width:4px;background:var(--border)}
.table-live-stat span{font-size:.72rem;color:var(--text-muted);font-weight:700}.table-live-stat strong{font-size:1.65rem;grid-row:1/3;grid-column:2}.table-live-stat small{font-size:.62rem;color:var(--text-muted)}
.table-live-stat.total::before{background:#98143c}.table-live-stat.occupied::before{background:#d4af37}.table-live-stat.free::before{background:#16845b}.table-live-stat.disabled::before{background:#7b8794}
.table-live-stat.occupied strong{color:#b7791f}.table-live-stat.free strong{color:#16845b}.table-live-stat.disabled strong{color:#7b8794}
.table-live-connection{display:flex;justify-content:center;font-size:.67rem;color:var(--text-muted)}.table-live-connection i{width:8px;height:8px;border-radius:50%;background:#20a66a;box-shadow:0 0 0 5px rgba(32,166,106,.12);animation:livePulse 1.8s infinite}
.table-management{margin:0 0 1rem;border:1px solid var(--border);border-radius:16px;background:var(--surface);overflow:hidden}
.table-management>summary{list-style:none;cursor:pointer;padding:.9rem 1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem}.table-management>summary::-webkit-details-marker{display:none}
.table-management>summary strong,.table-management>summary small{display:block}.table-management>summary small{margin-top:.2rem;color:var(--text-muted);font-size:.67rem}.management-toggle{padding:.4rem .75rem;border-radius:10px;background:var(--off-white);color:#98143c;font-size:.68rem;font-weight:800}.table-management[open] .management-toggle{font-size:0}.table-management[open] .management-toggle::after{content:"إغلاق الإعدادات";font-size:.68rem}
.restaurant-config-grid{display:grid;grid-template-columns:.75fr 1.55fr;border-top:1px solid var(--border)}.management-form{padding:1rem}.management-form+.management-form{border-inline-start:1px solid var(--border)}.management-form-title{font-weight:900;margin-bottom:.75rem}.compact-form-grid{display:grid;gap:.65rem;align-items:end}.area-form-grid{grid-template-columns:1.4fr 1fr auto}.table-create-grid{grid-template-columns:repeat(4,minmax(120px,1fr)) auto}.compact-form-grid .form-group{margin:0}
.table-area-card{margin-top:1rem;overflow:hidden}.table-area-header{background:linear-gradient(90deg,rgba(152,20,60,.045),transparent)}.table-area-header .card-title{display:flex;align-items:center;gap:.45rem}.area-icon{display:grid;place-items:center;width:28px;height:28px;border-radius:9px;background:rgba(152,20,60,.09);color:#98143c}.area-count{padding:.3rem .65rem;border-radius:999px;background:var(--off-white);color:var(--text-muted);font-size:.66rem;font-weight:700}
.restaurant-tables-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(285px,1fr));gap:1rem}
.restaurant-table-card{position:relative;overflow:hidden;min-height:190px;padding:1.05rem;border:1px solid var(--border);border-radius:16px;background:var(--surface);display:flex;flex-direction:column;transition:transform .2s,border-color .2s,background .2s,box-shadow .2s}
.restaurant-table-card:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(50,30,38,.08)}
.restaurant-table-card.is-occupied{border:2px solid #dc355d;background:linear-gradient(145deg,rgba(220,53,93,.14),rgba(220,53,93,.035));box-shadow:0 10px 28px rgba(220,53,93,.13);animation:occupiedGlow 2.5s ease-in-out infinite}
.restaurant-table-card.is-free{border:2px solid rgba(22,132,91,.55);background:linear-gradient(145deg,rgba(22,132,91,.12),rgba(22,132,91,.025));box-shadow:0 8px 24px rgba(22,132,91,.07)}
.restaurant-table-card.is-disabled{border-color:#a8afb7;background:#f1f2f3;opacity:.65;filter:grayscale(.35);animation:none}
.table-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.6rem}
.table-card-head strong{font-size:.95rem}.table-card-head strong,.table-card-head small{display:block}.table-card-head small{margin-top:.25rem;color:var(--text-muted);font-size:.65rem}
.table-status{padding:.3rem .65rem;border-radius:999px;background:var(--off-white);font-size:.63rem;font-weight:900}
.table-state-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-inline-end:.4rem;vertical-align:middle;background:#20a66a;box-shadow:0 0 0 4px rgba(32,166,106,.1)}
.is-occupied .table-state-dot{background:#dc355d;box-shadow:0 0 0 5px rgba(220,53,93,.14);animation:livePulse 1.4s infinite}.is-free .table-state-dot{background:#16845b;box-shadow:0 0 0 5px rgba(22,132,91,.13)}.is-disabled .table-state-dot{background:#8b929a;box-shadow:none;animation:none}
.is-occupied .table-status{color:#a30f35;background:rgba(220,53,93,.13)}
.is-free .table-status{color:#096943;background:rgba(22,132,91,.13)}
.table-session-info{display:flex;gap:.45rem;flex-wrap:wrap;align-items:center;margin:.95rem 0;color:var(--text-muted);font-size:.68rem}.table-session-info.muted{padding:.55rem .7rem;border-radius:10px;background:rgba(22,132,91,.06);color:#16845b}.session-chip{padding:.32rem .55rem;border:1px solid rgba(212,175,55,.24);border-radius:9px;background:rgba(255,255,255,.55)}.session-duration{width:100%;margin-top:.15rem}
.table-card-actions{display:flex;gap:.45rem;flex-wrap:wrap;align-items:center;margin-top:auto;padding-top:.75rem;border-top:1px solid rgba(130,100,110,.1)}
.inline-session-form{display:flex;gap:.4rem;flex:1}.inline-session-form .btn{flex:1}.mini-input{width:64px;padding:.38rem;border:1px solid var(--border);border-radius:9px;background:var(--surface);color:var(--text);text-align:center}
@keyframes occupiedGlow{0%,100%{box-shadow:0 10px 25px rgba(220,53,93,.10)}50%{box-shadow:0 12px 34px rgba(220,53,93,.22)}}
@keyframes livePulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(.75);opacity:.55}}
@media(max-width:1250px){.table-live-summary{grid-template-columns:repeat(4,1fr)}.table-live-connection{grid-column:1/-1;min-height:48px}.restaurant-config-grid{grid-template-columns:1fr}.management-form+.management-form{border-inline-start:0;border-top:1px solid var(--border)}.table-create-grid{grid-template-columns:repeat(2,minmax(150px,1fr)) auto}}
@media(max-width:800px){.table-live-summary{grid-template-columns:repeat(2,1fr)}.restaurant-tables-grid{grid-template-columns:1fr}.area-form-grid,.table-create-grid{grid-template-columns:1fr}.compact-form-grid .btn{width:100%}.table-management>summary{align-items:flex-start;flex-direction:column}}
@media(max-width:520px){.table-live-summary{grid-template-columns:1fr 1fr}.table-live-stat{padding:.75rem;min-height:75px}.table-live-stat strong{font-size:1.35rem}.page-actions{align-items:flex-start}.action-btns{width:100%;flex-wrap:wrap}}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const feedUrl = @json(route('restaurant.tables.status-feed', ['location_id' => $location->id]));
    const connection = document.querySelector('.table-live-connection span');

    function durationLabel(openedAt) {
        if (!openedAt) return '';
        const minutes = Math.max(0, Math.floor((Date.now() - new Date(openedAt).getTime()) / 60000));
        if (minutes < 60) return 'منذ ' + minutes + ' دقيقة';
        const hours = Math.floor(minutes / 60);
        const remainder = minutes % 60;
        return 'منذ ' + hours + ' ساعة' + (remainder ? ' و' + remainder + ' دقيقة' : '');
    }

    async function refreshTables() {
        try {
            const response = await fetch(feedUrl, {
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                cache: 'no-store'
            });
            if (!response.ok) throw new Error('تعذر جلب حالة الطاولات');

            const data = await response.json();
            let occupied = 0;
            let disabled = 0;

            for (const table of data.tables) {
                if (table.occupied) occupied++;
                if (!table.active) disabled++;

                const card = document.querySelector('[data-table-id="' + table.id + '"]');
                if (!card) continue;

                const wasOccupied = card.classList.contains('is-occupied');
                const wasDisabled = card.classList.contains('is-disabled');
                if (wasOccupied !== table.occupied || wasDisabled !== !table.active) {
                    window.location.reload();
                    return;
                }

                const guests = card.querySelector('.guest-count');
                const orders = card.querySelector('.open-orders');
                const duration = card.querySelector('.session-duration');
                if (guests) guests.textContent = table.guest_count + ' ضيوف';
                if (orders) orders.textContent = table.open_orders + ' طلب مفتوح';
                if (duration) duration.textContent = durationLabel(table.opened_at);
            }

            const free = data.tables.length - occupied - disabled;
            document.getElementById('occupied-count').textContent = occupied;
            document.getElementById('free-count').textContent = Math.max(0, free);
            document.getElementById('disabled-count').textContent = disabled;
            if (connection) connection.textContent = 'متصل — آخر تحديث الآن';
        } catch (error) {
            if (connection) connection.textContent = 'تعذر التحديث المباشر — ستتم إعادة المحاولة';
        }
    }

    refreshTables();
    window.setInterval(refreshTables, 10000);
});
</script>
@endsection
