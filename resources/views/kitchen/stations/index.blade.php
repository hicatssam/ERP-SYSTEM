@extends('layouts.app')

@section('title', 'محطات المطبخ')

@section('content')
@php
    $activeStations = $stations->where('is_active', true)->count();
    $defaultStation = $stations->firstWhere('is_default', true);
    $routedProducts = $stations->sum(fn ($station) => $station->productRoutes->count());
    $routedCategories = $stations->sum(fn ($station) => $station->categoryRoutes->count());
@endphp

<div class="kitchen-page">
    <section class="kitchen-hero">
        <div class="kitchen-hero-main">
            <div class="kitchen-hero-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M4 3v8a3 3 0 0 0 3 3h1V3M6 3v5M8 3v5M16 3v18M16 8c3 0 4-2 4-5v8h-4"/>
                </svg>
            </div>
            <div>
                <span class="kitchen-eyebrow">إدارة مسار التحضير</span>
                <h1>محطات المطبخ</h1>
                <p>{{ $location->name }} — وزّع المنتجات والفئات على فرق التحضير وشاشة KDS.</p>
            </div>
        </div>

        <div class="kitchen-hero-actions">
            <button type="button" class="btn btn-gold" id="toggleCreateStation" aria-expanded="{{ $errors->any() ? 'true' : 'false' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                محطة جديدة
            </button>
            <a href="{{ route('kitchen.tickets.index', ['location_id' => $location->id]) }}" class="btn btn-outline">تذاكر المطبخ</a>
            @if(\Illuminate\Support\Facades\Route::has('kds.index'))
                @can('kds.view')
                    <a href="{{ route('kds.index', ['location_id' => $location->id]) }}" class="btn btn-dark">شاشة KDS</a>
                @endcan
            @endif
        </div>
    </section>

    @if($errors->any())
        <div class="alert alert-danger kitchen-alert" role="alert">
            <strong>تعذر حفظ المحطة</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="station-stats" aria-label="ملخص محطات المطبخ">
        <article class="station-stat"><span class="station-stat-icon stat-gold">{{ $stations->count() }}</span><div><strong>إجمالي المحطات</strong><small>في الفرع الحالي</small></div></article>
        <article class="station-stat"><span class="station-stat-icon stat-green">{{ $activeStations }}</span><div><strong>محطات فعالة</strong><small>{{ $stations->count() - $activeStations }} متوقفة</small></div></article>
        <article class="station-stat"><span class="station-stat-icon stat-blue">{{ $routedProducts }}</span><div><strong>منتجات موجهة</strong><small>توجيه مباشر للمحطة</small></div></article>
        <article class="station-stat"><span class="station-stat-icon stat-purple">{{ $routedCategories }}</span><div><strong>فئات موجهة</strong><small>{{ $defaultStation?->name ?: 'لا توجد محطة افتراضية' }}</small></div></article>
    </section>

    <section class="kitchen-toolbar">
        <div class="routing-priority">
            <span class="routing-priority-icon">i</span>
            <div><strong>أولوية التوجيه</strong><p>المنتج المحدد ← فئة المنتج ← المحطة الافتراضية ← أول محطة فعالة.</p></div>
        </div>
        @if($locations->count() > 1)
            <form method="GET" class="branch-switcher">
                <label for="kitchen-location">الفرع</label>
                <select id="kitchen-location" name="location_id" class="form-select" onchange="this.form.submit()">
                    @foreach($locations as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $branch->id === (int) $location->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </section>

    <section id="createStationPanel" class="card create-station-panel" @if(!$errors->any()) hidden @endif>
        <div class="create-station-head">
            <div><span class="create-station-kicker">إعداد محطة جديدة</span><h2>أضف نقطة تحضير للمطبخ</h2><p>حدد زمن التحضير والتوجيهات، ويمكن تعديلها في أي وقت.</p></div>
            <button type="button" class="station-close" data-close-create aria-label="إغلاق">×</button>
        </div>
        <form method="POST" action="{{ route('kitchen.stations.store') }}" class="create-station-body">
            @csrf
            <input type="hidden" name="location_id" value="{{ $location->id }}">
            @include('kitchen.stations.partials.form', [
                'station' => null,
                'selectedProductIds' => [],
                'selectedCategoryIds' => [],
                'formPrefix' => 'new',
            ])
            <div class="station-form-actions"><button type="button" class="btn btn-ghost" data-close-create>إلغاء</button><button type="submit" class="btn btn-gold">حفظ المحطة</button></div>
        </form>
    </section>

    <section class="stations-section">
        <div class="stations-section-head">
            <div><h2>المحطات الحالية</h2><p>افتح أي محطة لتعديل بياناتها أو مسارات منتجاتها.</p></div>
            <div class="station-list-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>
                <input type="search" id="stationListSearch" placeholder="ابحث باسم المحطة أو الكود...">
            </div>
        </div>

        <div class="station-list" id="stationList">
            @forelse($stations as $station)
                @php
                    $selectedProductIds = $station->productRoutes->pluck('product_id')->map(fn ($id) => (int) $id)->all();
                    $selectedCategoryIds = $station->categoryRoutes->pluck('category_id')->map(fn ($id) => (int) $id)->all();
                @endphp
                <details class="card station-card {{ $station->is_default ? 'is-default' : '' }}" data-station-search="{{ mb_strtolower($station->name.' '.$station->code) }}">
                    <summary class="station-summary">
                        <span class="station-status-dot {{ $station->is_active ? 'is-active' : 'is-inactive' }}"></span>
                        <div class="station-identity">
                            <div class="station-name-row"><strong>{{ $station->name }}</strong><code>{{ $station->code }}</code></div>
                            <small>{{ $station->description ?: 'لا يوجد وصف لهذه المحطة.' }}</small>
                        </div>
                        <div class="station-metrics">
                            <span><b>{{ $station->target_minutes }}</b> دقيقة</span>
                            <span><b>{{ count($selectedProductIds) }}</b> منتج</span>
                            <span><b>{{ count($selectedCategoryIds) }}</b> فئة</span>
                        </div>
                        <div class="station-badges">
                            @if($station->is_default)<span class="badge badge-active">افتراضية</span>@endif
                            <span class="badge {{ $station->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $station->is_active ? 'فعالة' : 'متوقفة' }}</span>
                        </div>
                        <span class="station-chevron"></span>
                    </summary>
                    <form method="POST" action="{{ route('kitchen.stations.update', $station) }}" class="station-edit-body">
                        @csrf
                        @method('PUT')
                        <div class="station-edit-heading"><div><strong>تعديل المحطة</strong><small>يُطبّق التوجيه الجديد فور حفظ التعديلات.</small></div></div>
                        @include('kitchen.stations.partials.form', [
                            'station' => $station,
                            'selectedProductIds' => $selectedProductIds,
                            'selectedCategoryIds' => $selectedCategoryIds,
                            'formPrefix' => 'station-'.$station->id,
                        ])
                        <div class="station-form-actions"><button type="button" class="btn btn-ghost" data-close-details>إغلاق</button><button type="submit" class="btn btn-gold">حفظ التعديلات</button></div>
                    </form>
                </details>
            @empty
                <div class="card station-empty">
                    <div class="station-empty-icon">+</div><h3>لا توجد محطات مطبخ</h3><p>أضف محطة واحدة على الأقل قبل تشغيل الطلبات على KDS.</p>
                    <button type="button" class="btn btn-gold" data-open-create>إضافة أول محطة</button>
                </div>
            @endforelse
        </div>
        <div class="card station-no-results" id="stationNoResults" hidden>لا توجد محطة مطابقة لعبارة البحث.</div>
    </section>
</div>

<style>
.kitchen-page{--kg:var(--gold,#d4af37);display:grid;gap:1rem;max-width:1500px;margin:0 auto}
.kitchen-hero{position:relative;display:flex;align-items:center;justify-content:space-between;gap:1.25rem;padding:1.35rem 1.5rem;overflow:hidden;border:1px solid color-mix(in srgb,var(--kg) 22%,var(--border));border-radius:18px;background:linear-gradient(135deg,color-mix(in srgb,var(--kg) 9%,var(--surface)),var(--surface) 58%);box-shadow:0 12px 32px rgba(15,23,42,.05)}
.kitchen-hero::after{content:'';position:absolute;inset-inline-end:-70px;top:-95px;width:230px;height:230px;border-radius:50%;background:color-mix(in srgb,var(--kg) 9%,transparent)}
.kitchen-hero-main,.kitchen-hero-actions{position:relative;z-index:1;display:flex;align-items:center}.kitchen-hero-main{gap:1rem}.kitchen-hero-actions{justify-content:flex-end;gap:.55rem;flex-wrap:wrap}
.kitchen-hero-icon{display:grid;place-items:center;width:58px;height:58px;flex:0 0 58px;border-radius:16px;color:var(--kg);background:color-mix(in srgb,var(--kg) 12%,var(--surface));border:1px solid color-mix(in srgb,var(--kg) 28%,transparent)}.kitchen-hero-icon svg{width:29px;height:29px}
.kitchen-eyebrow,.create-station-kicker{display:block;margin-bottom:.2rem;color:var(--kg);font-size:.68rem;font-weight:800}.kitchen-hero h1{margin:0;font-size:1.45rem}.kitchen-hero p,.create-station-head p,.stations-section-head p{margin:.25rem 0 0;color:var(--text-muted);font-size:.76rem}.kitchen-hero-actions .btn svg{width:16px;height:16px}.btn-dark{color:#fff;background:#0f2942;border-color:#0f2942}.kitchen-alert{margin:0}.kitchen-alert ul{margin:.45rem 1rem 0 0}
.station-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.station-stat{display:flex;align-items:center;gap:.75rem;min-width:0;padding:.9rem 1rem;border:1px solid var(--border);border-radius:14px;background:var(--surface)}.station-stat-icon{display:grid;place-items:center;width:42px;height:42px;flex:0 0 42px;border-radius:12px;font-size:.9rem;font-weight:900}.stat-gold{color:#9a6700;background:#fff7d6}.stat-green{color:#087443;background:#e3f8ed}.stat-blue{color:#145d9b;background:#e8f3ff}.stat-purple{color:#7047a8;background:#f2eaff}.station-stat strong,.station-stat small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.station-stat strong{font-size:.76rem}.station-stat small{margin-top:.15rem;color:var(--text-muted);font-size:.65rem}
.kitchen-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.8rem 1rem;border:1px solid var(--border);border-radius:14px;background:var(--surface)}.routing-priority{display:flex;align-items:center;gap:.7rem}.routing-priority-icon{display:grid;place-items:center;width:28px;height:28px;flex:0 0 28px;border-radius:50%;color:#856000;background:#fff4c2;font-weight:900}.routing-priority strong{display:block;font-size:.73rem}.routing-priority p{margin:.12rem 0 0;color:var(--text-muted);font-size:.67rem}.branch-switcher{display:flex;align-items:center;gap:.6rem;min-width:300px}.branch-switcher label{font-size:.7rem;font-weight:800}.branch-switcher .form-select{min-width:220px}
.create-station-panel{overflow:hidden;border-color:color-mix(in srgb,var(--kg) 30%,var(--border));box-shadow:0 14px 38px rgba(15,23,42,.07)}.create-station-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1rem 1.2rem;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--kg) 5%,var(--surface))}.create-station-head h2,.stations-section-head h2{margin:0;font-size:1rem}.station-close{display:grid;place-items:center;width:34px;height:34px;border:1px solid var(--border);border-radius:10px;color:var(--text-muted);background:var(--surface);font-size:1.3rem;cursor:pointer}.create-station-body{padding:1.1rem 1.2rem}
.stations-section{display:grid;gap:.75rem}.stations-section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;padding:.25rem .15rem}.station-list-search{display:flex;align-items:center;gap:.45rem;width:min(340px,100%);padding:.55rem .7rem;border:1px solid var(--border);border-radius:11px;background:var(--surface)}.station-list-search svg{width:17px;height:17px;color:var(--text-muted)}.station-list-search input{width:100%;border:0;outline:0;color:var(--text);background:transparent;font:inherit;font-size:.72rem}.station-list{display:grid;gap:.65rem}
.station-card{overflow:hidden;transition:border-color .18s ease,box-shadow .18s ease}.station-card[open]{border-color:color-mix(in srgb,var(--kg) 34%,var(--border));box-shadow:0 12px 30px rgba(15,23,42,.06)}.station-card.is-default{border-inline-start:3px solid var(--kg)}.station-summary{display:grid;grid-template-columns:auto minmax(210px,1fr) auto auto auto;align-items:center;gap:1rem;padding:.9rem 1rem;cursor:pointer;list-style:none}.station-summary::-webkit-details-marker{display:none}.station-status-dot{width:10px;height:10px;border-radius:50%;box-shadow:0 0 0 4px rgba(148,163,184,.12)}.station-status-dot.is-active{background:#18a566;box-shadow:0 0 0 4px rgba(24,165,102,.12)}.station-status-dot.is-inactive{background:#9aa5b1}.station-identity{min-width:0}.station-name-row{display:flex;align-items:center;gap:.55rem}.station-name-row strong{font-size:.82rem}.station-name-row code{padding:.15rem .38rem;border-radius:6px;color:var(--text-muted);background:var(--theme-bg);font-size:.62rem}.station-identity small{display:block;max-width:520px;margin-top:.2rem;overflow:hidden;color:var(--text-muted);font-size:.66rem;text-overflow:ellipsis;white-space:nowrap}.station-metrics{display:flex;gap:.4rem}.station-metrics span{min-width:68px;padding:.42rem .55rem;border-radius:9px;color:var(--text-muted);background:var(--theme-bg);font-size:.62rem;text-align:center}.station-metrics b{color:var(--text);font-size:.72rem}.station-badges{display:flex;justify-content:flex-end;gap:.35rem;flex-wrap:wrap}.station-chevron{width:8px;height:8px;border-inline-end:2px solid var(--text-muted);border-block-end:2px solid var(--text-muted);transform:rotate(45deg);transition:transform .18s}.station-card[open] .station-chevron{transform:rotate(225deg)}
.station-edit-body{padding:1.1rem 1.2rem;border-top:1px solid var(--border);background:color-mix(in srgb,var(--theme-bg) 48%,var(--surface))}.station-edit-heading{margin-bottom:.9rem;padding-bottom:.75rem;border-bottom:1px dashed var(--border)}.station-edit-heading strong,.station-edit-heading small{display:block}.station-edit-heading strong{font-size:.78rem}.station-edit-heading small{margin-top:.15rem;color:var(--text-muted);font-size:.64rem}.station-form-actions{display:flex;align-items:center;justify-content:flex-end;gap:.55rem;margin-top:1rem;padding-top:.9rem;border-top:1px solid var(--border)}
.station-form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}.station-form-grid .span-2{grid-column:span 2}.route-picker{grid-column:span 2;border:1px solid var(--border);border-radius:12px;padding:.8rem;background:var(--surface)}.route-picker-head{display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-bottom:.55rem}.route-picker-head strong{font-size:.73rem}.route-search{margin-bottom:.5rem}.route-options{max-height:230px;overflow:auto;display:grid;gap:.2rem;padding-inline-end:.2rem;scrollbar-width:thin}.route-option{display:flex;align-items:center;gap:.55rem;padding:.48rem .55rem;border:1px solid transparent;border-radius:8px;font-size:.68rem;cursor:pointer}.route-option:hover{border-color:var(--border);background:var(--theme-bg)}.route-option:has(input:checked){border-color:color-mix(in srgb,var(--kg) 25%,var(--border));background:color-mix(in srgb,var(--kg) 7%,var(--surface))}.route-option small{color:var(--text-muted)}.boolean-row{display:flex;gap:.65rem;flex-wrap:wrap}.boolean-row label{display:flex;align-items:center;gap:.45rem;padding:.55rem .7rem;border:1px solid var(--border);border-radius:9px;background:var(--surface);font-size:.69rem;cursor:pointer}
.route-picker-tools{display:flex;gap:.3rem;margin-inline-start:auto}.route-picker-tool{padding:.2rem .4rem;border:1px solid var(--border);border-radius:6px;color:var(--text-muted);background:transparent;font:inherit;font-size:.56rem;cursor:pointer}.route-picker-tool:hover{color:var(--text);background:var(--theme-bg)}.station-empty,.station-no-results{padding:2.2rem;text-align:center}.station-empty-icon{display:grid;place-items:center;width:50px;height:50px;margin:0 auto .7rem;border-radius:15px;color:var(--kg);background:color-mix(in srgb,var(--kg) 12%,var(--surface));font-size:1.5rem}.station-empty h3{margin:0}.station-empty p{margin:.3rem 0 1rem;color:var(--text-muted);font-size:.72rem}.station-no-results{color:var(--text-muted);font-size:.75rem}
@media(max-width:1200px){.station-stats{grid-template-columns:repeat(2,1fr)}.station-summary{grid-template-columns:auto minmax(180px,1fr) auto auto}.station-badges{display:none}.station-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:800px){.kitchen-hero,.kitchen-toolbar,.stations-section-head{align-items:stretch;flex-direction:column}.kitchen-hero-actions{justify-content:flex-start}.branch-switcher{min-width:0}.branch-switcher .form-select{min-width:0;flex:1}.station-summary{grid-template-columns:auto 1fr auto}.station-metrics{grid-column:2/-1}.station-list-search{width:100%}.station-form-grid{grid-template-columns:1fr}.station-form-grid .span-2,.route-picker{grid-column:auto}}
@media(max-width:520px){.station-stats{grid-template-columns:1fr}.kitchen-hero{padding:1rem}.kitchen-hero-icon{display:none}.station-summary{gap:.65rem}.station-metrics{flex-wrap:wrap}.station-metrics span{min-width:60px}.routing-priority{align-items:flex-start}.branch-switcher{align-items:stretch;flex-direction:column}}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const panel = document.getElementById('createStationPanel');
    const toggle = document.getElementById('toggleCreateStation');
    const setCreateOpen = function (open) {
        if (!panel) return;
        panel.hidden = !open;
        toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) requestAnimationFrame(() => panel.scrollIntoView({behavior:'smooth',block:'start'}));
    };
    toggle?.addEventListener('click', () => setCreateOpen(panel?.hidden ?? true));
    document.querySelectorAll('[data-open-create]').forEach(button => button.addEventListener('click', () => setCreateOpen(true)));
    document.querySelectorAll('[data-close-create]').forEach(button => button.addEventListener('click', () => setCreateOpen(false)));
    document.querySelectorAll('[data-close-details]').forEach(button => button.addEventListener('click', () => { const details = button.closest('details'); if (details) details.open = false; }));

    document.querySelectorAll('.route-picker').forEach(picker => {
        const options = picker.querySelector('.route-options');
        const search = picker.querySelector('[data-route-search]');
        const badge = picker.querySelector('.route-picker-head .badge');
        const updateCount = () => { if (badge) badge.textContent = (options?.querySelectorAll('input[type="checkbox"]:checked').length ?? 0) + ' محددة'; };
        const tools = document.createElement('div');
        tools.className = 'route-picker-tools';
        tools.innerHTML = '<button type="button" class="route-picker-tool" data-select-all>تحديد الظاهر</button><button type="button" class="route-picker-tool" data-clear-all>مسح</button>';
        picker.querySelector('.route-picker-head')?.appendChild(tools);
        search?.addEventListener('input', () => {
            const query = search.value.trim().toLocaleLowerCase('ar');
            options?.querySelectorAll('.route-option').forEach(row => { row.hidden = Boolean(query) && !row.textContent.toLocaleLowerCase('ar').includes(query); });
        });
        tools.querySelector('[data-select-all]')?.addEventListener('click', () => { options?.querySelectorAll('.route-option:not([hidden]) input[type="checkbox"]').forEach(input => input.checked = true); updateCount(); });
        tools.querySelector('[data-clear-all]')?.addEventListener('click', () => { options?.querySelectorAll('input[type="checkbox"]').forEach(input => input.checked = false); updateCount(); });
        options?.addEventListener('change', updateCount);
        updateCount();
    });

    const search = document.getElementById('stationListSearch');
    const cards = Array.from(document.querySelectorAll('[data-station-search]'));
    const noResults = document.getElementById('stationNoResults');
    search?.addEventListener('input', () => {
        const query = search.value.trim().toLocaleLowerCase('ar');
        let visible = 0;
        cards.forEach(card => { card.hidden = Boolean(query) && !card.dataset.stationSearch.includes(query); if (!card.hidden) visible++; });
        if (noResults) noResults.hidden = visible > 0;
    });
});
</script>
@endsection
