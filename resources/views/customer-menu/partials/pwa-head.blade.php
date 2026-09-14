{{-- Per-branch PWA manifest + mobile/iOS home-screen meta tags. --}}
@php
    $pwaLocation = $location ?? (isset($order) ? $order->location : null);
    $pwaLocationCode = $pwaLocation?->code;
@endphp

@if($pwaLocationCode)
<link rel="manifest" href="{{ route('pwa.customer-manifest', $pwaLocationCode) }}">
@endif
<meta name="theme-color" content="{{ $theme['primary'] ?? '#C40035' }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $branding['name'] ?? 'المنيو' }}">
<link rel="apple-touch-icon" href="{{ route('pwa.icon', ['size' => 180]) }}">

{{-- Keep the public customer app at a true 1:1 mobile viewport. --}}
<script>
(function(){
    let viewport = document.querySelector('meta[name="viewport"]');
    const content = 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover';

    if (!viewport) {
        viewport = document.createElement('meta');
        viewport.setAttribute('name', 'viewport');
        document.head.prepend(viewport);
    }

    viewport.setAttribute('content', content);
})();
</script>
<style>
html{-webkit-text-size-adjust:100%;text-size-adjust:100%;touch-action:manipulation}
body{touch-action:manipulation}
input,select,textarea{font-size:16px!important}
</style>
