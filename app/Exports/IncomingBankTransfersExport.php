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
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IncomingBankTransfersExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithStyles,
    WithEvents,
    WithTitle
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

    public function title(): string
    {
        return 'الحوالات الواردة';
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
                    ?: (
                        $account->account_number
                        ?: $account->phone_number
                    ),
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
            $transfer->status?->label()
                ?? $transfer->statusValue(),
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

        /*
         * نفس ترويسة ملفات التقارير العامة في النظام:
         * نص ذهبي + خلفية بيج فاتحة + محاذاة وسط.
         */
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => [
                        'argb' => 'FF8C6818',
                    ],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FFF5EDD8',
                    ],
                ],
                'alignment' => [
                    'horizontal' =>
                        Alignment::HORIZONTAL_CENTER,
                    'vertical' =>
                        Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (
                AfterSheet $event
            ): void {
                $sheet = $event->sheet->getDelegate();

                $lastDataRow =
                    $this->transfers->count() + 1;

                $sheet->getRowDimension(1)
                    ->setRowHeight(24);

                if ($lastDataRow >= 2) {
                    $sheet
                        ->getStyle("A2:P{$lastDataRow}")
                        ->getAlignment()
                        ->setVertical(
                            Alignment::VERTICAL_TOP
                        );

                    $sheet
                        ->getStyle("A1:P{$lastDataRow}")
                        ->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(
                            Border::BORDER_THIN
                        )
                        ->getColor()
                        ->setARGB('FFE3E7EC');
                }

                $summaryRow = $lastDataRow + 2;

                $sheet->mergeCells(
                    "A{$summaryRow}:G{$summaryRow}"
                );

                $sheet->setCellValue(
                    "A{$summaryRow}",
                    'ملخص الحوالات حسب الفلاتر الحالية'
                );

                $sheet->setCellValue(
                    "H{$summaryRow}",
                    (int) (
                        $this->summary
                            ->transfers_count
                        ?? 0
                    )
                );

                $sheet->setCellValue(
                    "I{$summaryRow}",
                    (float) (
                        $this->summary
                            ->total_amount
                        ?? 0
                    )
                );

                $sheet->getStyle(
                    "A{$summaryRow}:P{$summaryRow}"
                )->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => [
                            'argb' => 'FF8C6818',
                        ],
                    ],
                    'fill' => [
                        'fillType' =>
                            Fill::FILL_SOLID,
                        'startColor' => [
                            'argb' => 'FFF5EDD8',
                        ],
                    ],
                    'alignment' => [
                        'horizontal' =>
                            Alignment::HORIZONTAL_CENTER,
                        'vertical' =>
                            Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $summaryRows = [
                    [
                        'المعتمد',
                        (int) (
                            $this->summary
                                ->confirmed_count
                            ?? 0
                        ),
                        (float) (
                            $this->summary
                                ->confirmed_total
                            ?? 0
                        ),
                    ],
                    [
                        'بانتظار التحقق',
                        (int) (
                            $this->summary
                                ->pending_count
                            ?? 0
                        ),
                        (float) (
                            $this->summary
                                ->pending_total
                            ?? 0
                        ),
                    ],
                    [
                        'المرفوض',
                        (int) (
                            $this->summary
                                ->rejected_count
                            ?? 0
                        ),
                        (float) (
                            $this->summary
                                ->rejected_total
                            ?? 0
                        ),
                    ],
                ];

                foreach (
                    $summaryRows
                    as $index => [
                        $label,
                        $count,
                        $amount,
                    ]
                ) {
                    $row =
                        $summaryRow
                        + $index
                        + 1;

                    $sheet->mergeCells(
                        "A{$row}:G{$row}"
                    );

                    $sheet->setCellValue(
                        "A{$row}",
                        $label
                    );

                    $sheet->setCellValue(
                        "H{$row}",
                        $count
                    );

                    $sheet->setCellValue(
                        "I{$row}",
                        $amount
                    );

                    $sheet->getStyle(
                        "A{$row}:P{$row}"
                    )->applyFromArray([
                        'fill' => [
                            'fillType' =>
                                Fill::FILL_SOLID,
                            'startColor' => [
                                'argb' => 'FFF8FAFC',
                            ],
                        ],
                        'alignment' => [
                            'horizontal' =>
                                Alignment::HORIZONTAL_CENTER,
                        ],
                    ]);
                }

                $filterValues = collect(
                    $this->filters
                )
                    ->filter(
                        fn ($value) =>
                            filled($value)
                            && ! in_array(
                                $value,
                                [
                                    'كل الفروع',
                                    'كل طرق الدفع',
                                    'كل الحالات',
                                ],
                                true
                            )
                    );

                if ($filterValues->isNotEmpty()) {
                    $filterRow =
                        $summaryRow + 5;

                    $sheet->mergeCells(
                        "A{$filterRow}:P{$filterRow}"
                    );

                    $sheet->setCellValue(
                        "A{$filterRow}",
                        'الفلاتر: '
                        . $filterValues
                            ->map(
                                fn (
                                    $value,
                                    $key
                                ) =>
                                    "{$key}: {$value}"
                            )
                            ->implode(' | ')
                    );

                    $sheet->getStyle(
                        "A{$filterRow}:P{$filterRow}"
                    )->applyFromArray([
                        'font' => [
                            'italic' => true,
                            'color' => [
                                'argb' => 'FF64748B',
                            ],
                        ],
                        'fill' => [
                            'fillType' =>
                                Fill::FILL_SOLID,
                            'startColor' => [
                                'argb' => 'FFF8FAFC',
                            ],
                        ],
                        'alignment' => [
                            'horizontal' =>
                                Alignment::HORIZONTAL_RIGHT,
                        ],
                    ]);
                }

                $sheet->getStyle(
                    "I2:I"
                    . max(
                        2,
                        $summaryRow + 3
                    )
                )
                    ->getNumberFormat()
                    ->setFormatCode(
                        '#,##0.00'
                    );
            },
        ];
    }
}
