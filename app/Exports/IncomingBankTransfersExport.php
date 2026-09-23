<?php

namespace App\Exports;

use App\Models\IncomingBankTransfer;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IncomingBankTransfersExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithStyles,
    WithEvents
{
    public function __construct(
        private readonly Collection $transfers,
        private readonly object $summary,
        private readonly array $filters = [],
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $row = $this->transfers->count() + 3;

                $sheet->mergeCells("A{$row}:H{$row}");
                $sheet->setCellValue("A{$row}", 'ملخص الحوالات حسب الفلاتر الحالية');
                $sheet->setCellValue("I{$row}", (float) ($this->summary->total_amount ?? 0));

                $summaryRows = [
                    [
                        'المعتمد',
                        (int) ($this->summary->confirmed_count ?? 0),
                        (float) ($this->summary->confirmed_total ?? 0),
                    ],
                    [
                        'بانتظار التحقق',
                        (int) ($this->summary->pending_count ?? 0),
                        (float) ($this->summary->pending_total ?? 0),
                    ],
                    [
                        'المرفوض',
                        (int) ($this->summary->rejected_count ?? 0),
                        (float) ($this->summary->rejected_total ?? 0),
                    ],
                ];

                foreach ($summaryRows as $index => [$label, $count, $amount]) {
                    $summaryRow = $row + $index + 1;

                    $sheet->mergeCells("A{$summaryRow}:G{$summaryRow}");
                    $sheet->setCellValue("A{$summaryRow}", $label);
                    $sheet->setCellValue("H{$summaryRow}", $count);
                    $sheet->setCellValue("I{$summaryRow}", $amount);
                }

                $filterRow = $row + 5;

                if ($this->filters !== []) {
                    $sheet->mergeCells("A{$filterRow}:P{$filterRow}");
                    $sheet->setCellValue(
                        "A{$filterRow}",
                        'الفلاتر: ' . collect($this->filters)
                            ->filter(fn ($value) => filled($value))
                            ->map(
                                fn ($value, $key) => "{$key}: {$value}"
                            )
                            ->implode(' | ')
                    );
                }

                $sheet->getStyle("A{$row}:P" . ($filterRow))
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getStyle("A{$row}:P{$row}")
                    ->getFont()
                    ->setBold(true);

                $sheet->getStyle("H{$row}:I" . ($row + 3))
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            },
        ];
    }
}
