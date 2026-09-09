<?php

namespace App\Exports;

use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Memory-bounded Excel export using maatwebsite/excel's FromGenerator concern.
 *
 * HOW THE MEMORY BOUND WORKS
 * ──────────────────────────
 * Sheet::fromGenerator() wraps the PHP Generator in a LazyCollection and passes
 * it to Sheet::appendRows(), which processes rows via
 *   flatMap(…)->chunk(1000)->each(write batch)
 * Because LazyCollection is lazy, each chunk of 1000 rows is processed and written
 * to the spreadsheet before the next chunk is fetched from the generator.
 *
 * The generator itself calls Eloquent/query-builder lazy($chunkSize), which issues
 * a new SELECT …  LIMIT $chunkSize OFFSET $n query per batch and applies any
 * requested eager loads per batch (unlike cursor(), which uses a single unbuffered
 * query and does NOT honour eager loads). Once $cap rows have been yielded the
 * generator returns and no further DB queries are issued.
 *
 * MEMORY PROFILE (approximate)
 * ─────────────────────────────
 *   • DB:          one batch of $chunkSize hydrated models in memory at a time
 *   • Spreadsheet: PhpSpreadsheet retains all written cells — this is unavoidable
 *     with in-process generation. For exports beyond ~50k rows, streaming writers
 *     (Xlsx streaming / CSV) would be needed. At the 10k default cap, peak cell
 *     memory is acceptable.
 *
 * IMPORTANT: all queries passed to this class must have a deterministic ORDER BY
 * so that offset-based chunking produces a stable, non-overlapping result set.
 */
class ChunkedQueryReportExport implements FromGenerator, WithMapping, WithHeadings, WithTitle, WithStyles, ShouldAutoSize, WithEvents
{
    private int $chunkSize;

    public function __construct(
        /** Unexecuted Eloquent or query-builder instance. Must have ORDER BY. */
        private \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query,
        private array $headings,
        private string $title,
        private \Closure $mapper,
        private int $cap = 10000,
        private bool $truncated = false,
    ) {
        // Honour the global chunk_size config so tests and staging can lower it easily.
        $this->chunkSize = (int) config('excel.exports.chunk_size', 1000);
    }

    // ─── FromGenerator ───────────────────────────────────────────────────────────
    // lazy($chunkSize) issues a fresh SELECT per batch with eager loads applied,
    // then yields models one-by-one. We break out of the loop once $cap is reached.

    public function generator(): Generator
    {
        $yielded = 0;
        foreach ($this->query->lazy($this->chunkSize) as $row) {
            if ($yielded >= $this->cap) {
                break;
            }
            yield $row;
            $yielded++;
        }
    }

    // ─── WithMapping ─────────────────────────────────────────────────────────────

    public function map($row): array
    {
        return ($this->mapper)($row);
    }

    // ─── WithHeadings ────────────────────────────────────────────────────────────

    public function headings(): array
    {
        return $this->headings;
    }

    // ─── WithTitle ───────────────────────────────────────────────────────────────

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }

    // ─── WithStyles ──────────────────────────────────────────────────────────────

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FF8C6818']],
                'fill'      => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFF5EDD8']],
                'alignment' => ['horizontal' => 'center'],
            ],
        ];
    }

    // ─── WithEvents ──────────────────────────────────────────────────────────────
    // Appends a highlighted warning row when the result was capped at $cap rows.

    public function registerEvents(): array
    {
        if (! $this->truncated) {
            return [];
        }

        $cap = $this->cap;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($cap) {
                $sheet    = $event->sheet->getDelegate();
                $lastRow  = $sheet->getHighestRow() + 1;
                $colCount = max(1, count($this->headings));
                $lastCol  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

                if ($colCount > 1) {
                    $sheet->mergeCells("A{$lastRow}:{$lastCol}{$lastRow}");
                }

                $sheet->setCellValue(
                    "A{$lastRow}",
                    "⚠️  تنبيه: تم عرض أول {$cap} صف فقط. قلّص نطاق التاريخ للحصول على بيانات كاملة."
                );

                $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'color' => ['argb' => 'FF92400E'],
                        'size'  => 10,
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFFEF3C7'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
            },
        ];
    }
}
