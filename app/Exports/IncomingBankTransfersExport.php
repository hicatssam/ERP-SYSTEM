<?php

namespace App\Exports;

use App\Models\IncomingBankTransfer;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IncomingBankTransfersExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithStyles
{
    public function __construct(
        private readonly Collection $transfers,
    ) {
    }

    public function collection(): Collection
    {
        return $this->transfers;
    }

    public function headings(): array
    {
        return [
            'التاريخ',
            'الفرع',
            'طريقة الدفع',
            'حساب الاستلام',
            'اسم المحوّل',
            'رقم الجوال',
            'رقم الحساب / المحفظة',
            'رقم الحوالة / المرجع',
            'المبلغ',
            'العملة',
            'الحالة',
            'سجلها',
            'اعتمدها / رفضها',
            'وقت التحقق',
            'سبب الرفض',
            'ملاحظات',
        ];
    }

    public function map($transfer): array
    {
        /** @var IncomingBankTransfer $transfer */
        $account = $transfer->locationPaymentAccount;

        $accountLabel = $account
            ? collect([
                $account->provider_name,
                $account->account_holder_name ?: $account->name,
                $account->iban
                    ?: ($account->account_number ?: $account->phone_number),
            ])->filter()->implode(' — ')
            : '';

        return [
            $transfer->received_at?->format('Y-m-d H:i') ?? '',
            $transfer->location?->name ?? '',
            $transfer->paymentMethod?->name_ar
                ?: ($transfer->paymentMethod?->name ?? ''),
            $accountLabel,
            $transfer->sender_name,
            $transfer->sender_phone,
            $transfer->sender_account_number,
            $transfer->reference_number,
            (float) $transfer->amount,
            $transfer->currency_code,
            $transfer->status?->label() ?? $transfer->statusValue(),
            $transfer->createdBy?->display_name ?? '',
            $transfer->verifiedBy?->display_name ?? '',
            $transfer->verified_at?->format('Y-m-d H:i') ?? '',
            $transfer->rejection_reason,
            $transfer->notes,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');

        return [
            1 => [
                'font' => [
                    'bold' => true,
                ],
            ],
        ];
    }
}
