@props(['code'])

@if(app(\App\Services\ModuleService::class)->isEnabled($code))
    {{ $slot }}
@endif
