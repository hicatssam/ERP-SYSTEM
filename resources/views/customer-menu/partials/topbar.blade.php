@php
    $backUrl = $backUrl ?? route('customer-menu.show', $location->code);
@endphp
<div class="shell">
 <div class="page-head">
  <a class="page-back" href="{{ $backUrl }}" aria-label="رجوع"><i class="fa-solid fa-arrow-right"></i></a>
  @if(!empty($pageTitle))
   <div><h1>{{ $pageTitle }}</h1>@if(!empty($pageSubtitle))<p>{{ $pageSubtitle }}</p>@endif</div>
  @endif
 </div>
</div>
