@props([
    'type',
    'record',
    'compact' => false,
])

@php
    $workflowSteps = \App\Support\WorkflowToolbar::build($type, $record);
    $workflowTitle = \App\Support\WorkflowToolbar::title($type);
    $workflowSubtitle = \App\Support\WorkflowToolbar::subtitle($type);

    $currentStep = collect($workflowSteps)->first(
        fn ($step) => in_array($step['state'], ['current', 'danger'], true)
    );

    $deliveryActive = collect($workflowSteps)->contains(
        fn ($step) => $step['is_delivery'] && in_array($step['state'], ['done', 'current'], true)
    );
@endphp

@if(count($workflowSteps))
<div class="dahab-workflow {{ $compact ? 'is-compact' : '' }}">
    <div class="dahab-workflow__header">
        <div>
            <div class="dahab-workflow__eyebrow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12h16"/><path d="m14 6 6 6-6 6"/><circle cx="5" cy="12" r="2"/></svg>
                متابعة العملية
            </div>
            <div class="dahab-workflow__title">{{ $workflowTitle }}</div>
            <div class="dahab-workflow__subtitle">{{ $workflowSubtitle }}</div>
        </div>

        @if($currentStep)
            <div class="dahab-workflow__current {{ $currentStep['state'] === 'danger' ? 'is-danger' : '' }}">
                <span>الحالة الحالية</span>
                <strong>{{ $currentStep['label'] }}</strong>
            </div>
        @endif
    </div>

    <div class="dahab-workflow__scroll">
        <div class="dahab-workflow__track">
            @foreach($workflowSteps as $index => $step)
                <div class="dahab-workflow__step state-{{ $step['state'] }} {{ $step['is_delivery'] ? 'is-delivery' : '' }}">
                    @if($index > 0)
                        <div class="dahab-workflow__connector"></div>
                    @endif

                    <div class="dahab-workflow__node">
                        <div class="dahab-workflow__icon">
                            @switch($step['icon'])
                                @case('file-plus')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 12v6"/><path d="M9 15h6"/></svg>
                                    @break
                                @case('factory')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21V9l6 3V9l6 3V4h6v17z"/><path d="M7 17h2M13 17h2M18 17h2"/></svg>
                                    @break
                                @case('check-circle')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                                    @break
                                @case('calendar')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
                                    @break
                                @case('chef')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 10a4 4 0 1 1 1-7.87A5 5 0 0 1 17 5a3.5 3.5 0 0 1 0 7H7z"/><path d="M7 10v9h10v-9"/><path d="M9 15h6"/></svg>
                                    @break
                                @case('cake')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h16v10H4z"/><path d="M4 14c2 2 4-2 6 0s4-2 6 0 4-2 4-2"/><path d="M8 10V7M12 10V6M16 10V7"/></svg>
                                    @break
                                @case('search-check')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="10" cy="10" r="6"/><path d="m14.5 14.5 5 5"/><path d="m7.5 10 1.7 1.7 3.3-3.5"/></svg>
                                    @break
                                @case('package-check')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 7 9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="m8 15 2 2 4-4"/></svg>
                                    @break
                                @case('truck')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h11v10H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>
                                    @break
                                @case('store-check')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10v10h16V10"/><path d="M3 10 5 4h14l2 6"/><path d="M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/><path d="m9 16 2 2 4-4"/></svg>
                                    @break
                                @case('user-check')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="m16 12 2 2 4-4"/></svg>
                                    @break
                                @case('send')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg>
                                    @break
                                @case('flag')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 22V4"/><path d="M5 5h12l-2 4 2 4H5"/></svg>
                                    @break
                                @case('x-circle')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/></svg>
                                    @break
                                @case('ban')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="m6 6 12 12"/></svg>
                                    @break
                                @default
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
                            @endswitch
                        </div>

                        @if($step['state'] === 'done')
                            <span class="dahab-workflow__done-mark">✓</span>
                        @elseif($step['state'] === 'current')
                            <span class="dahab-workflow__pulse"></span>
                        @endif
                    </div>

                    <div class="dahab-workflow__step-body">
                        <div class="dahab-workflow__step-title">{{ $step['label'] }}</div>
                        <div class="dahab-workflow__role">{{ $step['role'] }}</div>

                        @if($step['actor'])
                            <div class="dahab-workflow__meta">👤 {{ $step['actor'] }}</div>
                        @endif

                        @if($step['at'])
                            <div class="dahab-workflow__meta">🕒 {{ \App\Support\ArabicDate::compactDateTime($step['at']) }}</div>
                        @endif

                        @if($step['note'])
                            <div class="dahab-workflow__note" title="{{ $step['note'] }}">{{ \Illuminate\Support\Str::limit($step['note'], 60) }}</div>
                        @endif

                        @if($step['is_delivery'])
                            <span class="dahab-workflow__delivery-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h11v10H3z"/><path d="M14 10h4l3 3v3h-7z"/></svg>
                                التوصيل
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if($deliveryActive)
        @php
            $dispatchStep = collect($workflowSteps)->first(fn ($step) => in_array($step['status'], ['sent_to_branch', 'out_for_delivery'], true) && in_array($step['state'], ['done', 'current'], true));
            $receiveStep = collect($workflowSteps)->first(fn ($step) => in_array($step['status'], ['received_by_branch', 'received_at_branch'], true) && in_array($step['state'], ['done', 'current'], true));
        @endphp

        <div class="dahab-workflow__delivery-panel">
            <div class="dahab-workflow__delivery-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h11v10H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>
                تفاصيل التوصيل
            </div>

            <div class="dahab-workflow__delivery-grid">
                <div><small>موظف التوصيل</small><strong>{{ data_get($dispatchStep, 'actor') ?: 'بانتظار تحديد الموظف' }}</strong></div>
                <div><small>وقت الخروج</small><strong>{{ data_get($dispatchStep, 'at')
    ? \App\Support\ArabicDate::compactDateTime(data_get($dispatchStep, 'at'))
    : '—' }}</strong></div>
                <div><small>استلمه في الفرع</small><strong>{{ data_get($receiveStep, 'actor') ?: 'لم يتم الاستلام بعد' }}</strong></div>
                <div><small>وقت الاستلام</small><strong>{{ data_get($receiveStep, 'at')
    ? \App\Support\ArabicDate::compactDateTime(data_get($receiveStep, 'at'))
    : '—' }}</strong></div>
            </div>
        </div>
    @endif
</div>

@once
@push('styles')
<style>
.dahab-workflow{--wg:#d4a017;--wgs:rgba(212,160,23,.10);--wgr:#16a34a;--wgrs:rgba(22,163,74,.10);--wb:#2563eb;--wbs:rgba(37,99,235,.10);--wr:#dc2626;--wrs:rgba(220,38,38,.09);--wm:#94a3b8;margin:0 0 1.25rem;padding:1rem;background:#fff;border:1px solid var(--border);border-radius:18px;box-shadow:0 5px 18px rgba(15,23,42,.045)}
.dahab-workflow__header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem}.dahab-workflow__eyebrow{display:flex;align-items:center;gap:.35rem;color:var(--wg);font-size:.68rem;font-weight:800}.dahab-workflow__eyebrow svg{width:15px;height:15px}.dahab-workflow__title{margin-top:.22rem;color:var(--text);font-size:1rem;font-weight:900}.dahab-workflow__subtitle{margin-top:.15rem;color:var(--text-muted);font-size:.68rem}.dahab-workflow__current{min-width:145px;padding:.55rem .75rem;border-radius:11px;background:var(--wgs);border:1px solid rgba(212,160,23,.22);text-align:center}.dahab-workflow__current span{display:block;color:var(--text-muted);font-size:.61rem}.dahab-workflow__current strong{display:block;margin-top:.12rem;color:var(--wg);font-size:.75rem}.dahab-workflow__current.is-danger{background:var(--wrs);border-color:rgba(220,38,38,.20)}.dahab-workflow__current.is-danger strong{color:var(--wr)}
.dahab-workflow__scroll{width:100%;overflow-x:auto;overflow-y:hidden;padding:.35rem .15rem .7rem;scrollbar-width:thin}.dahab-workflow__track{display:flex;align-items:flex-start;min-width:max-content}.dahab-workflow__step{position:relative;width:150px;flex:0 0 150px;text-align:center;color:var(--wm)}.dahab-workflow__connector{position:absolute;top:24px;right:-50%;width:100%;height:3px;background:#e7e9ed;z-index:0}.dahab-workflow__step.state-done .dahab-workflow__connector,.dahab-workflow__step.state-current .dahab-workflow__connector{background:linear-gradient(90deg,var(--wgr),var(--wg))}.dahab-workflow__step.is-delivery.state-done .dahab-workflow__connector,.dahab-workflow__step.is-delivery.state-current .dahab-workflow__connector{background:var(--wb)}
.dahab-workflow__node{position:relative;z-index:1;width:50px;height:50px;margin:0 auto .55rem}.dahab-workflow__icon{width:50px;height:50px;display:flex;align-items:center;justify-content:center;border-radius:15px;background:#f8fafc;border:2px solid #e6e9ed;color:var(--wm);transition:.2s ease}.dahab-workflow__icon svg{width:22px;height:22px}.dahab-workflow__step.state-done .dahab-workflow__icon{color:var(--wgr);background:var(--wgrs);border-color:rgba(22,163,74,.30)}.dahab-workflow__step.state-current .dahab-workflow__icon{color:var(--wg);background:var(--wgs);border-color:var(--wg);box-shadow:0 0 0 5px rgba(212,160,23,.08)}.dahab-workflow__step.is-delivery.state-current .dahab-workflow__icon,.dahab-workflow__step.is-delivery.state-done .dahab-workflow__icon{color:var(--wb);background:var(--wbs);border-color:rgba(37,99,235,.35)}.dahab-workflow__step.state-danger .dahab-workflow__icon{color:var(--wr);background:var(--wrs);border-color:rgba(220,38,38,.35)}.dahab-workflow__done-mark{position:absolute;left:-4px;top:-4px;width:18px;height:18px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:var(--wgr);color:#fff;border:2px solid #fff;font-size:.6rem;font-weight:900}.dahab-workflow__pulse{position:absolute;left:-2px;top:-2px;width:12px;height:12px;border-radius:50%;background:var(--wg);border:2px solid #fff;box-shadow:0 0 0 3px rgba(212,160,23,.18)}.dahab-workflow__step.is-delivery .dahab-workflow__pulse{background:var(--wb);box-shadow:0 0 0 3px rgba(37,99,235,.16)}
.dahab-workflow__step-body{padding:0 .35rem}.dahab-workflow__step-title{color:var(--text);font-size:.72rem;font-weight:850;line-height:1.45}.dahab-workflow__step.state-current .dahab-workflow__step-title{color:var(--wg)}.dahab-workflow__step.is-delivery.state-current .dahab-workflow__step-title,.dahab-workflow__step.is-delivery.state-done .dahab-workflow__step-title{color:var(--wb)}.dahab-workflow__step.state-danger .dahab-workflow__step-title{color:var(--wr)}.dahab-workflow__role{min-height:29px;margin-top:.12rem;color:var(--text-muted);font-size:.59rem;line-height:1.45}.dahab-workflow__meta{display:flex;align-items:center;justify-content:center;gap:.22rem;margin-top:.22rem;color:var(--text-muted);font-size:.57rem;white-space:nowrap}.dahab-workflow__note{margin-top:.3rem;padding:.28rem .35rem;border-radius:7px;background:#f8fafc;color:var(--text-muted);font-size:.56rem;line-height:1.4}.dahab-workflow__delivery-badge{display:inline-flex;align-items:center;gap:.2rem;margin-top:.35rem;padding:.2rem .4rem;border-radius:999px;color:var(--wb);background:var(--wbs);font-size:.55rem;font-weight:800}.dahab-workflow__delivery-badge svg{width:11px;height:11px}
.dahab-workflow__delivery-panel{margin-top:.75rem;padding:.85rem;border:1px solid rgba(37,99,235,.17);border-radius:13px;background:linear-gradient(135deg,rgba(37,99,235,.055),#fff)}.dahab-workflow__delivery-title{display:flex;align-items:center;gap:.4rem;color:var(--wb);font-size:.74rem;font-weight:900}.dahab-workflow__delivery-title svg{width:17px;height:17px}.dahab-workflow__delivery-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.7rem;margin-top:.7rem}.dahab-workflow__delivery-grid>div{padding:.6rem .7rem;background:#fff;border:1px solid #e8ebef;border-radius:10px}.dahab-workflow__delivery-grid small{display:block;color:var(--text-muted);font-size:.58rem}.dahab-workflow__delivery-grid strong{display:block;margin-top:.18rem;color:var(--text);font-size:.68rem;line-height:1.5}.dahab-workflow.is-compact .dahab-workflow__subtitle,.dahab-workflow.is-compact .dahab-workflow__role,.dahab-workflow.is-compact .dahab-workflow__meta,.dahab-workflow.is-compact .dahab-workflow__note{display:none}
@media(max-width:800px){.dahab-workflow__header{flex-direction:column}.dahab-workflow__current{width:100%}.dahab-workflow__delivery-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:520px){.dahab-workflow__delivery-grid{grid-template-columns:1fr}}
</style>
@endpush
@endonce
@endif