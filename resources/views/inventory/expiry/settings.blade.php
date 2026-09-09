@extends('layouts.app')
@section('title','إعدادات صلاحية المخزون')
@section('content')
<div class="page-header"><div><h1 class="page-heading">إعدادات صلاحية المخزون</h1><p class="page-subheading">مراحل التحذير وقنوات الإرسال.</p></div><a class="btn btn-outline" href="{{ route('inventory.expiry.index') }}">رجوع</a></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<form method="POST" action="{{ route('settings.inventory-expiry.update') }}" style="max-width:850px">@csrf @method('PUT')
<div class="card"><div class="card-body">
<div class="form-group"><label class="form-label">مراحل التنبيه بالأيام</label><input class="form-input" name="inventory_expiry_alert_days" value="{{ old('inventory_expiry_alert_days',$settings['alert_days']) }}" required><small class="text-muted">مثال: 60,30,7,0</small></div>
@foreach([
['inventory_expiry_monitoring_enabled','تفعيل مراقبة الصلاحية',$settings['enabled']],
['inventory_expiry_notify_database','إشعار داخل النظام',$settings['database']],
['inventory_expiry_notify_email','إرسال Email',$settings['email']],
['inventory_expiry_notify_whatsapp','إرسال WhatsApp',$settings['whatsapp']],
['inventory_expiry_block_expired_stock','منع استخدام المخزون المنتهي',$settings['block_expired']],
] as [$name,$label,$checked])<label style="display:flex;gap:.65rem;align-items:center;padding:.75rem 0;border-bottom:1px solid var(--border)"><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name,$checked))><strong>{{ $label }}</strong></label>@endforeach
</div></div>
<div class="card" style="margin-top:1rem"><div class="card-body"><p>Mail driver: <strong>{{ $mailDriver }}</strong></p><p>WhatsApp Cloud API: <strong>{{ $whatsAppConfigured?'مهيأ':'غير مهيأ' }}</strong></p>@if($mailDriver==='log')<div class="alert alert-warning">البريد حاليًا LOG ولن يخرج فعليًا قبل ضبط SMTP.</div>@endif @if(!$whatsAppConfigured)<div class="alert alert-warning">لا تفعل WhatsApp قبل ضبط بيانات Meta Cloud API.</div>@endif</div></div>
<div style="margin-top:1rem;text-align:left"><button class="btn btn-gold">حفظ الإعدادات</button></div></form>
@endsection
