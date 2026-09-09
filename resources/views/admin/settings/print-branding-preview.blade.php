@extends('layouts.print')

@section('document_title', 'معاينة هوية المستندات')
@section('document_number', 'PREVIEW-001')
@section('document_date', now()->format('Y-m-d'))
@section('document_subtitle', 'نموذج عام للفواتير والسندات والكشوف')
@section('document_meta', 'معاينة إعدادات الطباعة')
@section('signature_right', 'توقيع المستلم')
@section('signature_left', 'اعتماد الإدارة')

@section('print_content')
    <div class="print-section">
        <div class="print-section-title">بيانات المستند</div>

        <table class="print-summary" cellpadding="0" cellspacing="0">
            <tr>
                <td><small>اسم العميل / الموظف</small><strong>اسم تجريبي</strong></td>
                <td><small>الإجمالي</small><strong>1,250.00</strong></td>
                <td><small>المدفوع</small><strong class="print-credit">750.00</strong></td>
                <td><small>المتبقي</small><strong class="print-debit">500.00</strong></td>
            </tr>
        </table>
    </div>

    <div class="print-section">
        <div class="print-section-title">تفاصيل المستند</div>

        <table class="print-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr><th>#</th><th>البيان</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th></tr>
            </thead>
            <tbody>
                <tr><td>1</td><td>بند تجريبي أول</td><td>2</td><td class="print-money">250.00</td><td class="print-money">500.00</td></tr>
                <tr><td>2</td><td>بند تجريبي ثانٍ</td><td>1</td><td class="print-money">750.00</td><td class="print-money">750.00</td></tr>
            </tbody>
        </table>
    </div>

    <table class="print-total-box" style="width:42%;margin-right:auto" cellpadding="0" cellspacing="0">
        <tr><td>الإجمالي</td><td>1,250.00</td></tr>
        <tr><td>المدفوع</td><td class="print-credit">750.00</td></tr>
        <tr class="grand"><td>المتبقي</td><td>500.00</td></tr>
    </table>
@endsection
