@extends('layouts.app')
@section('title', 'دليل الحسابات')
@section('page-title', 'دليل الحسابات')
@section('content')
<div class="page-header"><div><h1 class="page-heading">دليل الحسابات</h1><p class="page-subheading">تصنيف الحسابات المستخدمة في القيود والسندات. الحسابات الموجودة في قيود سابقة تبقى محفوظة حتى لو تغير استخدامها.</p></div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        @can('accounting.accounts.manage')<button type="button" class="btn btn-primary" data-open-dialog="account-create-dialog">إضافة حساب</button>@endcan
        <a href="{{ route('accounting.books.statements') }}" class="btn btn-outline">التقارير المالية</a>
    </div></div>
@can('accounting.accounts.manage')
<x-action-dialog id="account-create-dialog" title="إضافة حساب إلى الدليل" description="راجع الرمز والفئة قبل الحفظ، فالحسابات المستخدمة في القيود تبقى ضمن السجل.">
    <form method="POST" action="{{ route('accounting.books.accounts.store') }}">@csrf
        <input type="hidden" name="_modal" value="account-create-dialog">
        <div class="form-grid">
            <div class="form-group"><label class="form-label">رمز الحساب *</label><input name="code" class="form-input" maxlength="30" value="{{ old('code') }}" required placeholder="مثال: 1011"></div>
            <div class="form-group"><label class="form-label">اسم الحساب *</label><input name="name" class="form-input" maxlength="160" value="{{ old('name') }}" required></div>
            <div class="form-group"><label class="form-label">الفئة *</label><select name="type" class="form-input" required><option value="asset">أصول</option><option value="liability">التزامات</option><option value="equity">حقوق ملكية</option><option value="revenue">إيرادات</option><option value="expense">مصروفات</option></select></div>
            <div class="form-group"><label class="form-label">نوع حساب الخزينة</label><select name="subtype" class="form-input"><option value="">ليس حساب خزينة</option><option value="cash">صندوق نقدي</option><option value="bank">بنك / حوالات</option></select></div>
        </div>
        <div style="display:flex;gap:.5rem;justify-content:flex-end"><button type="button" class="btn btn-outline" data-close-dialog>إلغاء</button><button class="btn btn-primary">حفظ الحساب</button></div>
    </form>
</x-action-dialog>
@endcan
<div class="card"><div class="card-header"><span class="card-title">الحسابات</span></div><div class="card-body"><div class="table-wrap">
    <table class="data-table"><thead><tr><th>الرمز</th><th>الاسم</th><th>الفئة</th><th>الاستخدام</th><th>الحالة</th></tr></thead><tbody>
        @foreach($accounts as $account)<tr><td>{{ $account->code }}</td><td>{{ $account->name }}</td><td>{{ $account->typeLabel() }}</td><td>{{ ['cash' => 'صندوق', 'bank' => 'بنك'][$account->subtype] ?? 'حساب عام' }}</td><td>{{ $account->is_active ? 'نشط' : 'موقوف' }}</td></tr>@endforeach
    </tbody></table>
</div></div></div>
@endsection
