@extends('layouts.app')

@section('title', 'مركز الجاهزية والتسليم')

@section('content')
    @php
        $summary = $report['summary'];
        $statusLabel = match($summary['status']) {
            'ready' => 'جاهز للتسليم',
            'review' => 'يحتاج مراجعة',
            default => 'يوجد مانع للتسليم',
        };

        $statusClass = match($summary['status']) {
            'ready' => 'release-ok',
            'review' => 'release-warning',
            default => 'release-error',
        };

        $groups = [
            'client' => 'إعداد العميل',
            'modules' => 'الوحدات',
            'database' => 'قاعدة البيانات',
            'filesystem' => 'الملفات والتخزين',
            'runtime' => 'Runtime',
            'production' => 'إعداد الإنتاج',
        ];
    @endphp

    <style>
        .release-shell{max-width:1180px;margin:0 auto}
        .release-hero{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.25rem;border:1px solid var(--border);border-radius:18px;background:var(--surface);margin-bottom:1rem}
        .release-score{width:92px;height:92px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:8px solid color-mix(in srgb,var(--theme-primary) 20%,var(--border));font-size:1.35rem;font-weight:800}
        .release-hero-main{display:flex;align-items:center;gap:1rem}
        .release-actions{display:flex;gap:.65rem;flex-wrap:wrap}
        .release-status{display:inline-flex;padding:.3rem .65rem;border-radius:999px;font-size:.75rem;font-weight:800}
        .release-ok{background:color-mix(in srgb,var(--theme-success) 12%,transparent);color:var(--theme-success)}
        .release-warning{background:color-mix(in srgb,var(--theme-warning) 14%,transparent);color:var(--theme-warning)}
        .release-error{background:color-mix(in srgb,var(--theme-danger) 12%,transparent);color:var(--theme-danger)}
        .release-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem;margin-bottom:1rem}
        .release-stat{padding:1rem;border:1px solid var(--border);border-radius:14px;background:var(--surface)}
        .release-stat small{display:block;color:var(--text-muted);margin-bottom:.3rem}
        .release-stat strong{font-size:1.3rem}
        .release-section{margin-top:1rem}
        .release-check{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:.9rem 0;border-bottom:1px dashed var(--border)}
        .release-check:last-child{border-bottom:0}
        .release-check-main{display:flex;align-items:flex-start;gap:.7rem}
        .release-dot{width:12px;height:12px;border-radius:50%;margin-top:.3rem;flex:0 0 12px}
        .release-dot.ok{background:var(--theme-success)}
        .release-dot.warning{background:var(--theme-warning)}
        .release-dot.error{background:var(--theme-danger)}
        .release-check small{display:block;color:var(--text-muted);line-height:1.7;margin-top:.25rem}
        .release-chip{display:inline-flex;padding:.2rem .55rem;border-radius:999px;background:color-mix(in srgb,var(--theme-primary) 9%,transparent);font-size:.7rem}
        @media(max-width:760px){.release-hero{align-items:stretch;flex-direction:column}.release-stats{grid-template-columns:1fr}.release-score{width:76px;height:76px}.release-actions .btn{flex:1}}
    </style>

    <div class="release-shell">
        <div class="page-header">
            <div>
                <h1 class="page-heading">مركز الجاهزية والتسليم</h1>
                <p class="page-subheading">
                    فحص حي لنسخة العميل قبل النشر أو التسليم.
                </p>
            </div>
        </div>

        <div class="release-hero">
            <div class="release-hero-main">
                <div class="release-score">
                    {{ $summary['score'] }}%
                </div>

                <div>
                    <span class="release-status {{ $statusClass }}">
                        {{ $statusLabel }}
                    </span>

                    <h2 style="margin:.45rem 0 .2rem">
                        {{ $report['client']['system_name'] ?: config('app.name') }}
                    </h2>

                    <small class="text-muted">
                        {{ $report['client']['business_profile_name'] ?? '—' }}
                        ·
                        تم الفحص {{ \Carbon\Carbon::parse($report['generated_at'])->format('Y-m-d H:i') }}
                    </small>
                </div>
            </div>

            <div class="release-actions">
                <a
                    href="{{ route('release-center.index') }}"
                    class="btn btn-outline"
                >
                    إعادة الفحص
                </a>

                <a
                    href="{{ route('release-center.export') }}"
                    class="btn btn-gold"
                >
                    تصدير ملف التسليم
                </a>
            </div>
        </div>

        <div class="release-stats">
            <div class="release-stat">
                <small>فحوصات ناجحة</small>
                <strong>{{ $summary['counts']['ok'] }}</strong>
            </div>

            <div class="release-stat">
                <small>تحتاج مراجعة</small>
                <strong>{{ $summary['counts']['warning'] }}</strong>
            </div>

            <div class="release-stat">
                <small>مشاكل مانعة</small>
                <strong>{{ $summary['counts']['error'] }}</strong>
            </div>
        </div>

        @foreach($groups as $groupKey => $groupLabel)
            @php
                $groupChecks = collect($report['checks'])
                    ->where('group', $groupKey);
            @endphp

            @if($groupChecks->isNotEmpty())
                <div class="card release-section">
                    <div class="card-header">
                        <span class="card-title">{{ $groupLabel }}</span>
                    </div>

                    <div class="card-body">
                        @foreach($groupChecks as $check)
                            <div class="release-check">
                                <div class="release-check-main">
                                    <span class="release-dot {{ $check['status'] }}"></span>

                                    <div>
                                        <strong>{{ $check['title'] }}</strong>
                                        <small>{{ $check['message'] }}</small>
                                    </div>
                                </div>

                                <span class="release-chip">
                                    {{ match($check['status']) {
                                        'ok' => 'سليم',
                                        'warning' => 'مراجعة',
                                        default => 'مشكلة',
                                    } }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        <div class="card release-section">
            <div class="card-header">
                <span class="card-title">الوحدات الحالية</span>
            </div>

            <div class="card-body">
                <div style="display:flex;gap:.45rem;flex-wrap:wrap">
                    @foreach($report['modules']['active'] as $code)
                        <span class="release-chip">{{ $code }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
