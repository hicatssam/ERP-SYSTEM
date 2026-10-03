@extends('layouts.app')
@section('title', 'قيد تسوية يدوي')
@section('page-title', 'قيد تسوية يدوي')
@section('content')
<div class="page-header"><div><h1 class="page-heading">قيد تسوية يدوي</h1><p class="page-subheading">للتسويات والأرصدة الافتتاحية غير النقدية. حركات النقد والبنك تسجل بسندات قبض وصرف، ولا تكرر الحركات الموجودة في النظام.</p></div><a href="{{ route('accounting.books.journals') }}" class="btn btn-outline">القيود</a></div>
<form method="POST" action="{{ route('accounting.books.journals.store') }}" class="card" id="journalForm">@csrf
    <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <div class="card-header"><span class="card-title">بيانات القيد</span></div><div class="card-body">
        <div class="form-grid">
            <div class="form-group"><label class="form-label">الفرع *</label><select name="location_id" class="form-input" required><option value="">اختر الفرع</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((int) old('location_id', $locationId) === $location->id)>{{ $location->name }}</option>@endforeach</select></div>
            <div class="form-group"><label class="form-label">التاريخ *</label><input type="date" name="entry_date" class="form-input" value="{{ old('entry_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></div>
        </div>
        <div class="form-group"><label class="form-label">سبب التسوية *</label><input name="description" class="form-input" value="{{ old('description') }}" minlength="5" maxlength="500" required></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>الحساب</th><th>مدين</th><th>دائن</th><th>شرح السطر</th><th></th></tr></thead><tbody id="journalLines"></tbody></table></div>
        <button type="button" class="btn btn-outline btn-sm" id="addJournalLine" style="margin-top:.75rem">إضافة سطر</button>
        <p style="margin:1rem 0"><strong>المجموع:</strong> مدين <span id="debitTotal">0.00</span> · دائن <span id="creditTotal">0.00</span></p>
        <button class="btn btn-primary">ترحيل القيد المتوازن</button>
    </div>
</form>
<template id="journalLineTemplate"><tr><td><select class="form-input account" required><option value="">اختر الحساب</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></td>
    <td><input class="form-input debit" type="number" min="0" step="0.01" value="0" style="max-width:150px"></td>
    <td><input class="form-input credit" type="number" min="0" step="0.01" value="0" style="max-width:150px"></td>
    <td><input class="form-input memo" maxlength="255"></td><td><button type="button" class="btn btn-outline btn-sm remove-line">حذف</button></td></tr></template>
@endsection
@push('scripts')<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('journalLines');
    const sum = selector => [...table.querySelectorAll(selector)].reduce((n, el) => n + Math.round(Number(el.value || 0) * 100), 0);
    function totals() {
        document.getElementById('debitTotal').textContent = (sum('.debit') / 100).toFixed(2);
        document.getElementById('creditTotal').textContent = (sum('.credit') / 100).toFixed(2);
    }
    function renumber() {
        [...table.children].forEach((row, i) => {
            for (const cls of ['account', 'debit', 'credit', 'memo']) {
                row.querySelector('.' + cls).name = `lines[${i}][${cls === 'account' ? 'account_id' : cls}]`;
            }
        });
        totals();
    }
    function add() {
        if (table.children.length >= 30) return;
        const row = document.getElementById('journalLineTemplate').content.firstElementChild.cloneNode(true);
        row.querySelector('.remove-line').addEventListener('click', () => { if (table.children.length > 2) { row.remove(); renumber(); } });
        row.querySelectorAll('.debit,.credit').forEach(input => input.addEventListener('input', totals));
        table.appendChild(row); renumber();
    }
    document.getElementById('addJournalLine').addEventListener('click', add);
    document.getElementById('journalForm').addEventListener('submit', event => {
        if (sum('.debit') <= 0 || sum('.debit') !== sum('.credit') || !confirm('ترحيل القيد الآن؟ التصحيح لاحقًا بقيد عكسي.')) {
            event.preventDefault(); alert('راجع مجموع المدين والدائن وتأكيد الترحيل.');
        }
    });
    add(); add();
});
</script>@endpush
