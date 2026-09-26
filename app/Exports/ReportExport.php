<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportExport implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        private array $rows,
        private array $headings,
        private string $title = 'تقرير',
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31); // sheet name max 31 chars
    }

    public function styles(Worksheet $sheet): array
    {
        $theme = app(
            \App\Services\PrintThemeService::class
        )->settings();

        $secondary = strtoupper(
            ltrim(
                (string) (
                    $theme['secondary_color']
                    ?? '#111827'
                ),
                '#'
            )
        );

        if (
            ! preg_match(
                '/^[0-9A-F]{6}$/',
                $secondary
            )
        ) {
            $secondary = '111827';
        }

        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');

        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => [
                        'argb' => 'FFFFFFFF',
                    ],
                ],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => [
                        'argb' => 'FF' . $secondary,
                    ],
                ],
                'alignment' => [
                    'horizontal' => 'center',
                    'vertical' => 'center',
                ],
            ],
        ];
    }
}
