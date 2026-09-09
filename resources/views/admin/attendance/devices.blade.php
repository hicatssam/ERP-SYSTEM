@extends('layouts.app')

@section('title', 'أجهزة الحضور والبصمة')

@section('content')

<style>
.bio-page{
    max-width:1380px;
    margin:0 auto;
}

/* =========================================================
   Header
========================================================= */
.bio-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:1rem;
    margin-bottom:1rem;
}

.bio-head-actions{
    display:flex;
    align-items:center;
    gap:.55rem;
    flex-wrap:wrap;
}

/* =========================================================
   Stats
========================================================= */
.bio-stats{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:.8rem;
    margin-bottom:1rem;
}

.bio-stat{
    position:relative;
    overflow:hidden;
    min-height:105px;
    padding:1rem 1.05rem;
    border:1px solid var(--border);
    border-radius:17px;
    background:var(--surface);
}

.bio-stat::after{
    content:'';
    position:absolute;
    width:90px;
    height:90px;
    left:-32px;
    bottom:-38px;
    border-radius:999px;
    background:color-mix(
        in srgb,
        var(--border) 30%,
        transparent
    );
}

.bio-stat-head{
    position:relative;
    z-index:1;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:.7rem;
}

.bio-stat-label{
    color:var(--text-muted);
    font-size:.73rem;
    font-weight:800;
}

.bio-stat-icon{
    width:35px;
    height:35px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:color-mix(
        in srgb,
        var(--border) 42%,
        transparent
    );
}

.bio-stat strong{
    position:relative;
    z-index:1;
    display:block;
    margin-top:.55rem;
    font-size:1.5rem;
}

.bio-stat small{
    position:relative;
    z-index:1;
    display:block;
    margin-top:.35rem;
    color:var(--text-muted);
    font-size:.64rem;
}

.bio-stat-online .bio-stat-icon{
    color:var(--theme-success);
    background:color-mix(
        in srgb,
        var(--theme-success) 10%,
        transparent
    );
}

.bio-stat-offline .bio-stat-icon{
    color:var(--theme-danger);
    background:color-mix(
        in srgb,
        var(--theme-danger) 9%,
        transparent
    );
}

/* =========================================================
   Token
========================================================= */
.bio-token{
    position:relative;
    margin-bottom:1rem;
    padding:1rem 1.05rem;
    border-radius:15px;
    border:1px solid color-mix(
        in srgb,
        var(--theme-warning) 35%,
        var(--border)
    );
    background:color-mix(
        in srgb,
        var(--theme-warning) 7%,
        var(--surface)
    );
}

.bio-token-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:1rem;
}

.bio-token-title{
    font-weight:900;
    font-size:.84rem;
}

.bio-token-desc{
    margin-top:.3rem;
    color:var(--text-muted);
    font-size:.69rem;
    line-height:1.6;
}

.bio-token-code{
    display:flex;
    align-items:center;
    gap:.6rem;
    margin-top:.8rem;
}

.bio-token-code code{
    flex:1;
    direction:ltr;
    text-align:left;
    word-break:break-all;
    padding:.75rem;
    border-radius:10px;
    background:color-mix(
        in srgb,
        var(--border) 32%,
        transparent
    );
    font-size:.73rem;
}

/* =========================================================
   Main grid
========================================================= */
.bio-grid{
    display:grid;
    grid-template-columns:340px minmax(0,1fr);
    gap:1rem;
    align-items:start;
}

/* =========================================================
   Device list
========================================================= */
.bio-sidebar{
    position:sticky;
    top:1rem;
}

.bio-sidebar-card{
    border:1px solid var(--border);
    border-radius:17px;
    background:var(--surface);
    overflow:hidden;
}

.bio-sidebar-head{
    padding:1rem;
    border-bottom:1px solid var(--border);
}

.bio-sidebar-title{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:.7rem;
}

.bio-sidebar-title strong{
    font-size:.82rem;
}

.bio-sidebar-count{
    display:inline-flex;
    min-width:28px;
    height:28px;
    align-items:center;
    justify-content:center;
    padding:0 .45rem;
    border-radius:999px;
    color:var(--text-muted);
    background:color-mix(
        in srgb,
        var(--border) 45%,
        transparent
    );
    font-size:.66rem;
    font-weight:900;
}

.bio-search{
    position:relative;
    margin-top:.7rem;
}

.bio-search-icon{
    position:absolute;
    right:.75rem;
    top:50%;
    transform:translateY(-50%);
    color:var(--text-muted);
    pointer-events:none;
}

.bio-search .form-input{
    padding-right:2.2rem;
}

.bio-device-list{
    padding:.65rem;
    display:flex;
    flex-direction:column;
    gap:.55rem;
    max-height:720px;
    overflow:auto;
}

.bio-device-card{
    display:block;
    padding:.85rem;
    border:1px solid transparent;
    border-radius:13px;
    background:color-mix(
        in srgb,
        var(--surface) 93%,
        var(--background)
    );
    color:var(--text);
    text-decoration:none;
    transition:.18s ease;
}

.bio-device-card:hover{
    border-color:color-mix(
        in srgb,
        var(--theme-primary) 35%,
        var(--border)
    );
}

.bio-device-card.active{
    border-color:color-mix(
        in srgb,
        var(--theme-primary) 60%,
        var(--border)
    );
    background:color-mix(
        in srgb,
        var(--theme-primary) 6%,
        var(--surface)
    );
}

.bio-device-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:.7rem;
}

.bio-device-name{
    font-size:.8rem;
    font-weight:900;
}

.bio-device-model{
    margin-top:.18rem;
    font-size:.63rem;
    color:var(--text-muted);
}

.bio-status{
    display:inline-flex;
    align-items:center;
    gap:.32rem;
    padding:.28rem .5rem;
    border-radius:999px;
    font-size:.62rem;
    font-weight:900;
}

.bio-status::before{
    content:'';
    width:6px;
    height:6px;
    border-radius:999px;
    background:currentColor;
}

.bio-online{
    color:var(--theme-success);
    background:color-mix(
        in srgb,
        var(--theme-success) 11%,
        transparent
    );
}

.bio-offline{
    color:var(--text-muted);
    background:color-mix(
        in srgb,
        var(--border) 50%,
        transparent
    );
}

.bio-device-meta{
    display:flex;
    gap:.38rem;
    align-items:center;
    flex-wrap:wrap;
    margin-top:.55rem;
    color:var(--text-muted);
    font-size:.61rem;
}

.bio-meta-pill{
    padding:.2rem .4rem;
    border-radius:7px;
    background:color-mix(
        in srgb,
        var(--border) 36%,
        transparent
    );
}

/* =========================================================
   Detail panel
========================================================= */
.bio-panel{
    border:1px solid var(--border);
    border-radius:18px;
    background:var(--surface);
    overflow:hidden;
}

.bio-panel-head{
    padding:1rem 1.1rem;
    border-bottom:1px solid var(--border);
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:1rem;
}

.bio-panel-title{
    display:flex;
    align-items:flex-start;
    gap:.75rem;
}

.bio-device-icon{
    width:44px;
    height:44px;
    flex:0 0 44px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:13px;
    background:color-mix(
        in srgb,
        var(--theme-primary) 11%,
        transparent
    );
    color:var(--theme-primary);
    font-size:1.1rem;
}

.bio-panel-name{
    font-weight:900;
}

.bio-panel-sub{
    margin-top:.22rem;
    font-size:.66rem;
    color:var(--text-muted);
}

.bio-panel-actions{
    display:flex;
    gap:.45rem;
    flex-wrap:wrap;
}

.bio-panel-body{
    padding:1rem;
}

/* =========================================================
   KV
========================================================= */
.bio-kv{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:.7rem;
}

.bio-kv-item{
    min-height:82px;
    padding:.75rem;
    border:1px solid var(--border);
    border-radius:12px;
    background:color-mix(
        in srgb,
        var(--surface) 95%,
        var(--background)
    );
}

.bio-kv-item small{
    display:block;
    color:var(--text-muted);
    font-size:.62rem;
}

.bio-kv-item strong{
    display:block;
    margin-top:.3rem;
    font-size:.76rem;
    word-break:break-word;
}

/* =========================================================
   Endpoint
========================================================= */
.bio-endpoint{
    margin-top:1rem;
    padding:.9rem;
    border-radius:13px;
    border:1px solid var(--border);
    background:color-mix(
        in srgb,
        var(--surface) 92%,
        var(--background)
    );
}

.bio-endpoint-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:.6rem;
}

.bio-endpoint-head strong{
    font-size:.76rem;
}

.bio-endpoint-row{
    display:flex;
    align-items:center;
    gap:.55rem;
    margin-top:.6rem;
}

.bio-endpoint code{
    flex:1;
    direction:ltr;
    text-align:left;
    word-break:break-all;
    padding:.65rem .7rem;
    border-radius:9px;
    background:var(--surface);
    border:1px solid var(--border);
    font-size:.68rem;
}

/* =========================================================
   Sections
========================================================= */
.bio-section{
    margin-top:1rem;
    border:1px solid var(--border);
    border-radius:15px;
    overflow:hidden;
}

.bio-section-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:.8rem;
    padding:.85rem 1rem;
    border-bottom:1px solid var(--border);
}

.bio-section-title{
    display:flex;
    flex-direction:column;
    gap:.15rem;
}

.bio-section-title strong{
    font-size:.78rem;
}

.bio-section-title small{
    color:var(--text-muted);
    font-size:.61rem;
}

.bio-section-body{
    padding:1rem;
}

.bio-section-body.no-padding{
    padding:0;
}

/* =========================================================
   Mapping form
========================================================= */
.bio-map-form{
    display:grid;
    grid-template-columns:minmax(180px,1.5fr) minmax(150px,1fr) auto;
    align-items:end;
    gap:.7rem;
}

.bio-map-form .form-group{
    margin:0;
}

/* =========================================================
   Tables
========================================================= */
.bio-table-wrap{
    overflow:auto;
}

.bio-table{
    width:100%;
    border-collapse:collapse;
}

.bio-table th,
.bio-table td{
    padding:.78rem .85rem;
    border-bottom:1px solid var(--border);
    text-align:right;
    vertical-align:middle;
    white-space:nowrap;
}

.bio-table th{
    font-size:.65rem;
    font-weight:900;
    color:var(--text-muted);
    background:color-mix(
        in srgb,
        var(--surface) 90%,
        var(--background)
    );
}

.bio-table td{
    font-size:.72rem;
}

.bio-table tbody tr:hover{
    background:color-mix(
        in srgb,
        var(--surface) 92%,
        var(--background)
    );
}

.bio-employee{
    display:flex;
    align-items:center;
    gap:.55rem;
}

.bio-avatar{
    width:34px;
    height:34px;
    flex:0 0 34px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:10px;
    background:color-mix(
        in srgb,
        var(--theme-primary) 11%,
        var(--surface)
    );
    color:var(--theme-primary);
    font-weight:900;
}

.bio-code{
    direction:ltr;
    display:inline-flex;
    padding:.25rem .45rem;
    border-radius:7px;
    background:color-mix(
        in srgb,
        var(--border) 38%,
        transparent
    );
    font-family:monospace;
    font-size:.68rem;
}

.bio-badge{
    display:inline-flex;
    align-items:center;
    gap:.3rem;
    padding:.27rem .5rem;
    border-radius:999px;
    font-size:.62rem;
    font-weight:900;
}

.bio-badge.success{
    color:var(--theme-success);
    background:color-mix(
        in srgb,
        var(--theme-success) 10%,
        transparent
    );
}

.bio-badge.danger{
    color:var(--theme-danger);
    background:color-mix(
        in srgb,
        var(--theme-danger) 9%,
        transparent
    );
}

.bio-badge.muted{
    color:var(--text-muted);
    background:color-mix(
        in srgb,
        var(--border) 45%,
        transparent
    );
}

/* =========================================================
   Empty
========================================================= */
.bio-empty{
    text-align:center;
    padding:2.4rem 1rem;
    color:var(--text-muted);
}

.bio-empty-icon{
    width:54px;
    height:54px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0 auto .7rem;
    border-radius:15px;
    background:color-mix(
        in srgb,
        var(--border) 40%,
        transparent
    );
}

.bio-empty strong{
    display:block;
    color:var(--text);
    font-size:.8rem;
}

.bio-empty span{
    display:block;
    margin-top:.28rem;
    font-size:.66rem;
}

/* =========================================================
   Modal
========================================================= */
.bio-modal{
    position:fixed;
    inset:0;
    z-index:9999;
    display:none;
    align-items:center;
    justify-content:center;
    padding:1rem;
}

.bio-modal.open{
    display:flex;
}

.bio-backdrop{
    position:absolute;
    inset:0;
    background:rgba(15,23,42,.6);
    backdrop-filter:blur(4px);
}

.bio-dialog{
    position:relative;
    z-index:2;
    width:min(760px,100%);
    max-height:calc(100vh - 2rem);
    overflow:auto;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:20px;
    box-shadow:0 25px 80px rgba(0,0,0,.25);
}

.bio-dialog-head{
    padding:1.1rem 1.2rem;
    border-bottom:1px solid var(--border);
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:1rem;
}

.bio-dialog-head h3{
    margin:0;
    font-size:1rem;
}

.bio-dialog-head p{
    margin:.3rem 0 0;
    color:var(--text-muted);
    font-size:.67rem;
}

.bio-dialog-body{
    padding:1.2rem;
}

.bio-form-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:.85rem;
}

.bio-full{
    grid-column:1/-1;
}

.bio-dialog-foot{
    padding:1rem 1.2rem;
    border-top:1px solid var(--border);
    display:flex;
    justify-content:flex-end;
    gap:.6rem;
}

.bio-info{
    display:flex;
    gap:.65rem;
    align-items:flex-start;
    padding:.8rem .9rem;
    border-radius:12px;
    border:1px solid color-mix(
        in srgb,
        var(--theme-info) 15%,
        var(--border)
    );
    background:color-mix(
        in srgb,
        var(--theme-info) 6%,
        transparent
    );
    color:var(--text-muted);
    font-size:.67rem;
    line-height:1.7;
}

/* =========================================================
   Responsive
========================================================= */
@media(max-width:1100px){
    .bio-grid{
        grid-template-columns:290px minmax(0,1fr);
    }

    .bio-kv{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:900px){
    .bio-grid{
        grid-template-columns:1fr;
    }

    .bio-sidebar{
        position:static;
    }

    .bio-device-list{
        max-height:none;
    }

    .bio-stats{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:700px){
    .bio-head{
        flex-direction:column;
        align-items:stretch;
    }

    .bio-head-actions{
        width:100%;
    }

    .bio-head-actions .btn{
        flex:1;
        justify-content:center;
    }

    .bio-panel-head{
        flex-direction:column;
    }

    .bio-kv{
        grid-template-columns:1fr;
    }

    .bio-map-form{
        grid-template-columns:1fr;
    }

    .bio-form-grid{
        grid-template-columns:1fr;
    }

    .bio-full{
        grid-column:auto;
    }

    .bio-token-code,
    .bio-endpoint-row{
        align-items:stretch;
        flex-direction:column;
    }
}

@media(max-width:480px){
    .bio-stats{
        grid-template-columns:1fr 1fr;
    }
}
</style>

@php
    $deviceCollection = method_exists($devices, 'getCollection')
        ? $devices->getCollection()
        : collect($devices);

    $onlineDevices = $deviceCollection->filter(function ($device) {
        return $device->last_seen_at
            && $device->last_seen_at->gt(now()->subMinutes(10));
    })->count();

    $offlineDevices = max(
        0,
        $deviceCollection->count() - $onlineDevices
    );

    $totalMappings = $deviceCollection->sum(
        fn ($device) => (int) ($device->mappings_count ?? 0)
    );

    $totalPunches = $deviceCollection->sum(
        fn ($device) => (int) ($device->punches_count ?? 0)
    );

    $selectedOnline = $selectedDevice
        && $selectedDevice->last_seen_at
        && $selectedDevice->last_seen_at->gt(
            now()->subMinutes(10)
        );
@endphp


<div class="bio-page">

    {{-- =====================================================
         Header
    ====================================================== --}}
    <div class="bio-head">

        <div>
            <h1 class="page-heading">
                أجهزة الحضور والبصمة
            </h1>

            <p class="page-subheading">
                إدارة وربط أجهزة الحضور بالنظام،
                متابعة الاتصال والمزامنة وربط مستخدمي
                الجهاز بسجلات الموظفين.
            </p>
        </div>

        <div class="bio-head-actions">

            <a
                class="btn btn-outline"
                href="{{ route('attendance.index') }}"
            >
                العودة للحضور
            </a>

            @can('attendance.devices.manage')

                <button
                    class="btn btn-gold"
                    type="button"
                    id="openDeviceModal"
                >
                    + إضافة جهاز
                </button>

            @endcan

        </div>

    </div>


    {{-- =====================================================
         Stats
    ====================================================== --}}
    <div class="bio-stats">

        <div class="bio-stat">

            <div class="bio-stat-head">
                <span class="bio-stat-label">
                    إجمالي الأجهزة
                </span>

                <span class="bio-stat-icon">
                    ◫
                </span>
            </div>

            <strong>
                {{ number_format($deviceCollection->count()) }}
            </strong>

            <small>
                أجهزة حضور مسجلة
            </small>

        </div>


        <div class="bio-stat bio-stat-online">

            <div class="bio-stat-head">
                <span class="bio-stat-label">
                    متصلة الآن
                </span>

                <span class="bio-stat-icon">
                    ●
                </span>
            </div>

            <strong>
                {{ number_format($onlineDevices) }}
            </strong>

            <small>
                آخر اتصال خلال 10 دقائق
            </small>

        </div>


        <div class="bio-stat bio-stat-offline">

            <div class="bio-stat-head">
                <span class="bio-stat-label">
                    غير متصلة
                </span>

                <span class="bio-stat-icon">
                    ○
                </span>
            </div>

            <strong>
                {{ number_format($offlineDevices) }}
            </strong>

            <small>
                تحتاج فحص الاتصال
            </small>

        </div>


        <div class="bio-stat">

            <div class="bio-stat-head">
                <span class="bio-stat-label">
                    مستخدمون مربوطون
                </span>

                <span class="bio-stat-icon">
                    ◉
                </span>
            </div>

            <strong>
                {{ number_format($totalMappings) }}
            </strong>

            <small>
                {{ number_format($totalPunches) }}
                بصمة مستلمة
            </small>

        </div>

    </div>


    {{-- =====================================================
         New device token
    ====================================================== --}}
    @if(session('attendance_device_token'))

        <div class="bio-token">

            <div class="bio-token-head">

                <div>
                    <div class="bio-token-title">
                        Token الجهاز جاهز
                    </div>

                    <div class="bio-token-desc">
                        يظهر هذا الـToken مرة واحدة فقط.
                        انسخه إلى الـConnector المحلي واحفظه
                        في مكان آمن، لأن النظام يخزن Hash فقط.
                    </div>
                </div>

                <span class="bio-badge success">
                    تم الإنشاء
                </span>

            </div>


            <div class="bio-token-code">

                <code id="deviceTokenValue">
                    {{ session('attendance_device_token') }}
                </code>

                <button
                    type="button"
                    class="btn btn-sm btn-outline js-copy"
                    data-copy-target="deviceTokenValue"
                >
                    نسخ
                </button>

            </div>

        </div>

    @endif


    {{-- =====================================================
         Main
    ====================================================== --}}
    <div class="bio-grid">

        {{-- =================================================
             Sidebar
        ================================================== --}}
        <aside class="bio-sidebar">

            <div class="bio-sidebar-card">

                <div class="bio-sidebar-head">

                    <div class="bio-sidebar-title">
                        <strong>
                            الأجهزة
                        </strong>

                        <span class="bio-sidebar-count">
                            {{ $deviceCollection->count() }}
                        </span>
                    </div>


                    <div class="bio-search">

                        <span class="bio-search-icon">
                            ⌕
                        </span>

                        <input
                            type="search"
                            class="form-input"
                            id="deviceSearch"
                            placeholder="بحث عن جهاز..."
                            autocomplete="off"
                        >

                    </div>

                </div>


                <div class="bio-device-list">

                    @forelse($devices as $device)

                        @php
                            $online = $device->last_seen_at
                                && $device->last_seen_at->gt(
                                    now()->subMinutes(10)
                                );
                        @endphp


                        <a
                            class="
                                bio-device-card
                                bio-device-search-item
                                {{ $selectedDevice?->id === $device->id ? 'active' : '' }}
                            "

                            data-search="{{ mb_strtolower(
                                $device->name
                                .' '
                                .$device->code
                                .' '
                                .($device->vendor ?? '')
                                .' '
                                .($device->model ?? '')
                            ) }}"

                            href="{{ route(
                                'attendance.devices.index',
                                ['device' => $device->id]
                            ) }}"
                        >

                            <div class="bio-device-top">

                                <div>

                                    <div class="bio-device-name">
                                        {{ $device->name }}
                                    </div>

                                    <div class="bio-device-model">
                                        {{ $device->vendor ?: 'شركة غير محددة' }}

                                        @if($device->model)
                                            · {{ $device->model }}
                                        @endif
                                    </div>

                                </div>


                                <span
                                    class="
                                        bio-status
                                        {{ $online ? 'bio-online' : 'bio-offline' }}
                                    "
                                >
                                    {{ $online ? 'متصل' : 'غير متصل' }}
                                </span>

                            </div>


                            <div class="bio-device-meta">

                                <span class="bio-meta-pill">
                                    {{ $device->code }}
                                </span>

                                <span class="bio-meta-pill">
                                    {{ $device->connection_mode }}
                                </span>

                                <span>
                                    {{ $device->mappings_count }} موظف
                                </span>

                                <span>
                                    ·
                                </span>

                                <span>
                                    {{ $device->punches_count }} بصمة
                                </span>

                            </div>

                        </a>

                    @empty

                        <div class="bio-empty">

                            <div class="bio-empty-icon">
                                ◫
                            </div>

                            <strong>
                                لا توجد أجهزة
                            </strong>

                            <span>
                                أضف أول جهاز حضور للبدء.
                            </span>

                        </div>

                    @endforelse

                </div>

            </div>

        </aside>


        {{-- =================================================
             Device detail
        ================================================== --}}
        <div>

            @if($selectedDevice)

                <div class="bio-panel">

                    {{-- Header --}}
                    <div class="bio-panel-head">

                        <div class="bio-panel-title">

                            <div class="bio-device-icon">
                                ◫
                            </div>

                            <div>

                                <div class="bio-panel-name">
                                    {{ $selectedDevice->name }}
                                </div>

                                <div class="bio-panel-sub">
                                    {{ $selectedDevice->vendor ?: 'شركة غير محددة' }}

                                    @if($selectedDevice->model)
                                        · {{ $selectedDevice->model }}
                                    @endif

                                    · {{ $selectedDevice->code }}
                                </div>

                                <div style="margin-top:.45rem">

                                    <span
                                        class="
                                            bio-status
                                            {{ $selectedOnline ? 'bio-online' : 'bio-offline' }}
                                        "
                                    >
                                        {{ $selectedOnline ? 'متصل الآن' : 'غير متصل' }}
                                    </span>

                                </div>

                            </div>

                        </div>


                        @can('attendance.devices.manage')

                            <div class="bio-panel-actions">

                                <form
                                    method="POST"
                                    action="{{ route(
                                        'attendance.devices.reprocess',
                                        $selectedDevice
                                    ) }}"
                                >
                                    @csrf

                                    <button
                                        class="btn btn-sm btn-outline"
                                        type="submit"
                                    >
                                        إعادة معالجة
                                    </button>
                                </form>


                                <form
                                    method="POST"
                                    action="{{ route(
                                        'attendance.devices.regenerate-token',
                                        $selectedDevice
                                    ) }}"

                                    onsubmit="
                                        return confirm(
                                            'سيتم إيقاف التوكن الحالي فورًا. هل تريد المتابعة؟'
                                        )
                                    "
                                >
                                    @csrf

                                    <button
                                        class="btn btn-sm btn-outline"
                                        type="submit"
                                    >
                                        Token جديد
                                    </button>
                                </form>

                            </div>

                        @endcan

                    </div>


                    <div class="bio-panel-body">

                        {{-- Device info --}}
                        <div class="bio-kv">

                            <div class="bio-kv-item">
                                <small>
                                    طريقة الربط
                                </small>

                                <strong>
                                    {{ $selectedDevice->connection_mode }}
                                </strong>
                            </div>


                            <div class="bio-kv-item">
                                <small>
                                    IP / Port
                                </small>

                                <strong>
                                    {{ $selectedDevice->ip_address ?: '—' }}

                                    @if($selectedDevice->port)
                                        :{{ $selectedDevice->port }}
                                    @endif
                                </strong>
                            </div>


                            <div class="bio-kv-item">
                                <small>
                                    الموقع
                                </small>

                                <strong>
                                    {{ $selectedDevice->location?->name ?? 'كل المواقع' }}
                                </strong>
                            </div>


                            <div class="bio-kv-item">
                                <small>
                                    آخر اتصال
                                </small>

                                <strong>
                                    {{ $selectedDevice->last_seen_at?->format('Y-m-d H:i:s') ?? 'لم يتصل بعد' }}
                                </strong>
                            </div>


                            <div class="bio-kv-item">
                                <small>
                                    آخر مزامنة
                                </small>

                                <strong>
                                    {{ $selectedDevice->last_sync_at?->format('Y-m-d H:i:s') ?? '—' }}
                                </strong>
                            </div>


                            <div class="bio-kv-item">
                                <small>
                                    Serial Number
                                </small>

                                <strong>
                                    {{ $selectedDevice->serial_number ?: '—' }}
                                </strong>
                            </div>

                        </div>


                        {{-- Endpoint --}}
                        <div class="bio-endpoint">

                            <div class="bio-endpoint-head">

                                <strong>
                                    Endpoint الـConnector
                                </strong>

                                <span class="bio-badge muted">
                                    POST
                                </span>

                            </div>


                            <div class="bio-endpoint-row">

                                <code id="connectorEndpoint">
                                    {{ route(
                                        'attendance.integrations.devices.punches',
                                        $selectedDevice
                                    ) }}
                                </code>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline js-copy"
                                    data-copy-target="connectorEndpoint"
                                >
                                    نسخ
                                </button>

                            </div>

                        </div>


                        {{-- Mapping form --}}
                        @can('attendance.devices.manage')

                            <div class="bio-section">

                                <div class="bio-section-head">

                                    <div class="bio-section-title">

                                        <strong>
                                            ربط مستخدم الجهاز بموظف
                                        </strong>

                                        <small>
                                            اربط User ID الموجود في جهاز البصمة
                                            بموظف داخل النظام.
                                        </small>

                                    </div>

                                </div>


                                <div class="bio-section-body">

                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'attendance.devices.mappings.store',
                                            $selectedDevice
                                        ) }}"
                                    >

                                        @csrf


                                        <div class="bio-map-form">

                                            <div class="form-group">

                                                <label class="form-label">
                                                    الموظف
                                                </label>

                                                <select
                                                    class="form-input"
                                                    name="employee_id"
                                                    required
                                                >

                                                    <option value="">
                                                        اختر الموظف
                                                    </option>

                                                    @foreach($employees as $employee)

                                                        <option
                                                            value="{{ $employee->id }}"
                                                        >
                                                            {{ $employee->full_name }}

                                                            @if($employee->job_title)
                                                                — {{ $employee->job_title }}
                                                            @endif
                                                        </option>

                                                    @endforeach

                                                </select>

                                            </div>


                                            <div class="form-group">

                                                <label class="form-label">
                                                    User ID داخل الجهاز
                                                </label>

                                                <input
                                                    class="form-input"
                                                    name="device_user_id"
                                                    placeholder="مثال: 23"
                                                    required
                                                >

                                            </div>


                                            <button
                                                class="btn btn-gold"
                                                type="submit"
                                            >
                                                ربط الموظف
                                            </button>

                                        </div>

                                    </form>

                                </div>

                            </div>

                        @endcan


                        {{-- Mappings --}}
                        <div class="bio-section">

                            <div class="bio-section-head">

                                <div class="bio-section-title">
                                    <strong>
                                        ربط الموظفين
                                    </strong>

                                    <small>
                                        {{ $selectedDevice->mappings->count() }}
                                        مستخدم مربوط
                                    </small>
                                </div>

                            </div>


                            <div class="bio-section-body no-padding">

                                <div class="bio-table-wrap">

                                    <table class="bio-table">

                                        <thead>
                                            <tr>
                                                <th>Device User ID</th>
                                                <th>الموظف</th>
                                                <th>الحالة</th>
                                                <th>الإجراء</th>
                                            </tr>
                                        </thead>


                                        <tbody>

                                            @forelse(
                                                $selectedDevice->mappings
                                                as $mapping
                                            )

                                                @php
                                                    $mappingEmployee =
                                                        $mapping->employee?->full_name
                                                        ?: 'موظف غير معروف';

                                                    $initial = mb_substr(
                                                        trim($mappingEmployee),
                                                        0,
                                                        1
                                                    );
                                                @endphp

                                                <tr>

                                                    <td>
                                                        <span class="bio-code">
                                                            {{ $mapping->device_user_id }}
                                                        </span>
                                                    </td>


                                                    <td>

                                                        <div class="bio-employee">

                                                            <div class="bio-avatar">
                                                                {{ $initial }}
                                                            </div>

                                                            <strong>
                                                                {{ $mappingEmployee }}
                                                            </strong>

                                                        </div>

                                                    </td>


                                                    <td>

                                                        @if($mapping->is_active)

                                                            <span class="bio-badge success">
                                                                فعال
                                                            </span>

                                                        @else

                                                            <span class="bio-badge muted">
                                                                موقوف
                                                            </span>

                                                        @endif

                                                    </td>


                                                    <td>

                                                        @can('attendance.devices.manage')

                                                            <form
                                                                method="POST"
                                                                action="{{ route(
                                                                    'attendance.devices.mappings.destroy',
                                                                    [
                                                                        $selectedDevice,
                                                                        $mapping
                                                                    ]
                                                                ) }}"

                                                                onsubmit="
                                                                    return confirm(
                                                                        'هل تريد حذف ربط هذا الموظف؟'
                                                                    )
                                                                "
                                                            >

                                                                @csrf
                                                                @method('DELETE')

                                                                <button
                                                                    class="btn btn-sm btn-ghost"
                                                                    type="submit"
                                                                >
                                                                    حذف
                                                                </button>

                                                            </form>

                                                        @else
                                                            —
                                                        @endcan

                                                    </td>

                                                </tr>

                                            @empty

                                                <tr>
                                                    <td
                                                        colspan="4"
                                                        class="bio-empty"
                                                    >
                                                        لا يوجد ربط موظفين لهذا الجهاز.
                                                    </td>
                                                </tr>

                                            @endforelse

                                        </tbody>

                                    </table>

                                </div>

                            </div>

                        </div>


                        {{-- Recent punches --}}
                        <div class="bio-section">

                            <div class="bio-section-head">

                                <div class="bio-section-title">
                                    <strong>
                                        آخر البصمات
                                    </strong>

                                    <small>
                                        آخر الأحداث المستلمة من الجهاز
                                    </small>
                                </div>

                            </div>


                            <div class="bio-section-body no-padding">

                                <div class="bio-table-wrap">

                                    <table class="bio-table">

                                        <thead>
                                            <tr>
                                                <th>الوقت</th>
                                                <th>User ID</th>
                                                <th>الموظف</th>
                                                <th>النوع</th>
                                                <th>الحالة</th>
                                            </tr>
                                        </thead>


                                        <tbody>

                                            @forelse($recentPunches as $punch)

                                                <tr>

                                                    <td>
                                                        {{ $punch->punch_at?->format('Y-m-d H:i:s') ?? '—' }}
                                                    </td>

                                                    <td>
                                                        <span class="bio-code">
                                                            {{ $punch->device_user_id }}
                                                        </span>
                                                    </td>

                                                    <td>
                                                        {{ $punch->employee?->full_name ?? 'غير مربوط' }}
                                                    </td>

                                                    <td>
                                                        {{ $punch->punch_type ?: '—' }}
                                                    </td>

                                                    <td>
                                                        <span class="bio-badge muted">
                                                            @statusArabic($punch->status)
                                                        </span>
                                                    </td>

                                                </tr>

                                            @empty

                                                <tr>
                                                    <td
                                                        colspan="5"
                                                        class="bio-empty"
                                                    >
                                                        لم تصل بصمات من الجهاز بعد.
                                                    </td>
                                                </tr>

                                            @endforelse

                                        </tbody>

                                    </table>

                                </div>

                            </div>

                        </div>


                        {{-- Sync logs --}}
                        <div class="bio-section">

                            <div class="bio-section-head">

                                <div class="bio-section-title">
                                    <strong>
                                        سجل المزامنة
                                    </strong>

                                    <small>
                                        نتائج عمليات استقبال ومعالجة بيانات الجهاز
                                    </small>
                                </div>

                            </div>


                            <div class="bio-section-body no-padding">

                                <div class="bio-table-wrap">

                                    <table class="bio-table">

                                        <thead>
                                            <tr>
                                                <th>الوقت</th>
                                                <th>الاتجاه</th>
                                                <th>الحالة</th>
                                                <th>مستلم</th>
                                                <th>معالج</th>
                                                <th>فشل</th>
                                            </tr>
                                        </thead>


                                        <tbody>

                                            @forelse(
                                                $selectedDevice->syncLogs
                                                as $log
                                            )

                                                <tr>

                                                    <td>
                                                        {{ $log->started_at?->format('Y-m-d H:i:s') ?? '—' }}
                                                    </td>

                                                    <td>
                                                        {{ $log->direction }}
                                                    </td>

                                                    <td>
                                                        <span class="bio-badge muted">
                                                            @statusArabic($log->status)
                                                        </span>
                                                    </td>

                                                    <td>
                                                        {{ $log->received_count }}
                                                    </td>

                                                    <td>
                                                        {{ $log->processed_count }}
                                                    </td>

                                                    <td>
                                                        {{ $log->failed_count }}
                                                    </td>

                                                </tr>

                                            @empty

                                                <tr>
                                                    <td
                                                        colspan="6"
                                                        class="bio-empty"
                                                    >
                                                        لا يوجد سجل مزامنة لهذا الجهاز.
                                                    </td>
                                                </tr>

                                            @endforelse

                                        </tbody>

                                    </table>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            @else

                <div class="bio-panel">

                    <div class="bio-empty" style="padding:4rem 1rem">

                        <div class="bio-empty-icon">
                            ◫
                        </div>

                        <strong>
                            اختر جهاز حضور
                        </strong>

                        <span>
                            اختر جهازًا من القائمة لعرض تفاصيله،
                            الربط، البصمات وسجل المزامنة.
                        </span>

                    </div>

                </div>

            @endif

        </div>

    </div>

</div>


{{-- =========================================================
     Create Device Modal
========================================================= --}}
@can('attendance.devices.manage')

<div
    class="bio-modal"
    id="deviceModal"
    aria-hidden="true"
>

    <div
        class="bio-backdrop"
        data-device-close
    ></div>


    <div
        class="bio-dialog"
        role="dialog"
        aria-modal="true"
    >

        <div class="bio-dialog-head">

            <div>

                <h3>
                    إضافة جهاز حضور
                </h3>

                <p>
                    بعد الحفظ سيتم إنشاء Token آمن
                    لاستخدامه بواسطة الـConnector.
                </p>

            </div>


            <button
                class="btn btn-ghost"
                type="button"
                data-device-close
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            action="{{ route('attendance.devices.store') }}"
        >

            @csrf


            <div class="bio-dialog-body">

                <div class="bio-form-grid">

                    <div class="form-group">

                        <label class="form-label">
                            كود الجهاز *
                        </label>

                        <input
                            class="form-input"
                            name="code"
                            value="{{ old('code') }}"
                            placeholder="مثال: BIO-01"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label class="form-label">
                            اسم الجهاز *
                        </label>

                        <input
                            class="form-input"
                            name="name"
                            value="{{ old('name') }}"
                            placeholder="مثال: بصمة الفرع الرئيسي"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label class="form-label">
                            الشركة المصنعة
                        </label>

                        <input
                            class="form-input"
                            name="vendor"
                            value="{{ old('vendor') }}"
                            placeholder="ZKTeco / Hikvision / Anviz"
                        >

                    </div>


                    <div class="form-group">

                        <label class="form-label">
                            الموديل
                        </label>

                        <input
                            class="form-input"
                            name="model"
                            value="{{ old('model') }}"
                        >

                    </div>


                    <div class="form-group">

                        <label class="form-label">
                            Serial Number
                        </label>

                        <input
                            class="form-input"
                            name="serial_number"
                            value="{{ old('serial_number') }}"
                        >

                    </div>


                    <div class="form-group">

                        <label class="form-label">
                            طريقة الربط *
                        </label>

                        <select
                            class="form-input"
                            name="connection_mode"
                            required
                        >

                            <option
                                value="local_bridge"
                                @selected(
                                    old('connection_mode')
                                    === 'local_bridge'
                                )
                            >
                                Local Bridge / SDK
                            </option>

                            <option
                                value="push"
                                @selected(
                                    old('connection_mode')
                                    === 'push'
                                )
                            >
                                Push مباشر
                            </option>

                            <option
                                value="rest_api"
                                @selected(
                                    old('connection_mode')
                                    === 'rest_api'
                                )
                            >
                                REST API
                            </option>

                            <option
                                value="sdk"
                                @selected(
                                    old('connection_mode')
                                    === 'sdk'
                                )
                            >
                                SDK
                            </option>

                            <option
                                value="manual"
                                @selected(
                                    old('connection_mode')
                                    === 'manual'
                                )
                            >
                                يدوي
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label class="form-label">
                            IP Address
                        </label>

                        <input
                            class="form-input"
                            name="ip_address"
                            value="{{ old('ip_address') }}"
                            placeholder="192.168.1.50"
                        >

                    </div>


                    <div class="form-group">

                        <label class="form-label">
                            Port
                        </label>

                        <input
                            class="form-input"
                            type="number"
                            min="1"
                            max="65535"
                            name="port"
                            value="{{ old('port') }}"
                            placeholder="4370"
                        >

                    </div>


                    <div class="form-group bio-full">

                        <label class="form-label">
                            الموقع
                        </label>

                        <select
                            class="form-input"
                            name="location_id"
                        >

                            <option value="">
                                كل المواقع
                            </option>

                            @foreach($locations as $location)

                                <option
                                    value="{{ $location->id }}"
                                    @selected(
                                        old('location_id')
                                        == $location->id
                                    )
                                >
                                    {{ $location->name }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    <div class="form-group bio-full">

                        <label class="form-label">
                            ملاحظات
                        </label>

                        <textarea
                            class="form-input"
                            name="notes"
                            rows="3"
                            placeholder="أي معلومات إضافية عن الجهاز..."
                        >{{ old('notes') }}</textarea>

                    </div>


                    <div class="bio-info bio-full">

                        <span>
                            ⓘ
                        </span>

                        <span>
                            جهاز البصمة لا يكتب مباشرة في سجل
                            الحضور. البيانات تصل أولًا عبر
                            الـConnector ويتم التحقق منها وربطها
                            بالموظف ثم معالجتها داخل نظام الحضور.
                        </span>

                    </div>

                </div>

            </div>


            <div class="bio-dialog-foot">

                <button
                    type="button"
                    class="btn btn-ghost"
                    data-device-close
                >
                    إلغاء
                </button>

                <button
                    class="btn btn-gold"
                    type="submit"
                >
                    إنشاء الجهاز
                </button>

            </div>

        </form>

    </div>

</div>

@endcan


@push('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* =====================================================
       Device modal
    ====================================================== */
    const modal =
        document.getElementById('deviceModal');

    const openButton =
        document.getElementById('openDeviceModal');


    const openModal = function () {

        if (!modal) {
            return;
        }

        modal.classList.add('open');

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.style.overflow =
            'hidden';
    };


    const closeModal = function () {

        if (!modal) {
            return;
        }

        modal.classList.remove('open');

        modal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.style.overflow =
            '';
    };


    if (openButton) {

        openButton.addEventListener(
            'click',
            openModal
        );

    }


    if (modal) {

        modal
            .querySelectorAll('[data-device-close]')
            .forEach(function (button) {

                button.addEventListener(
                    'click',
                    closeModal
                );

            });

    }


    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key === 'Escape') {
                closeModal();
            }

        }
    );


    @if($errors->any())
        openModal();
    @endif


    /* =====================================================
       Device search
    ====================================================== */
    const deviceSearch =
        document.getElementById('deviceSearch');

    const deviceItems =
        Array.from(
            document.querySelectorAll(
                '.bio-device-search-item'
            )
        );


    if (deviceSearch) {

        deviceSearch.addEventListener(
            'input',
            function () {

                const value =
                    deviceSearch.value
                        .trim()
                        .toLocaleLowerCase('ar');


                deviceItems.forEach(function (item) {

                    const searchable =
                        (
                            item.dataset.search
                            || ''
                        ).toLocaleLowerCase('ar');


                    item.style.display =
                        !value
                        || searchable.includes(value)
                            ? ''
                            : 'none';

                });

            }
        );

    }


    /* =====================================================
       Copy helper
    ====================================================== */
    document
        .querySelectorAll('.js-copy')
        .forEach(function (button) {

            button.addEventListener(
                'click',
                async function () {

                    const targetId =
                        button.dataset.copyTarget;

                    const target =
                        document.getElementById(
                            targetId
                        );

                    if (!target) {
                        return;
                    }

                    const text =
                        target.textContent.trim();


                    try {

                        await navigator.clipboard.writeText(
                            text
                        );

                        const oldText =
                            button.textContent;

                        button.textContent =
                            'تم النسخ ✓';

                        setTimeout(function () {
                            button.textContent =
                                oldText;
                        }, 1600);

                    } catch (error) {

                        const range =
                            document.createRange();

                        range.selectNodeContents(target);

                        const selection =
                            window.getSelection();

                        selection.removeAllRanges();
                        selection.addRange(range);

                    }

                }
            );

        });

});
</script>

@endpush

@endsection