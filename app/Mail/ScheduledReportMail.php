<?php

namespace App\Mail;

use App\Models\ReportSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ReportSchedule $schedule,
        public readonly string $filePath,
        public readonly string $fileName,
        public readonly string $dateFrom,
        public readonly string $dateTo,
    ) {}

    public function envelope(): Envelope
    {
        $reportTypes = [
            'orders'          => 'تقرير الطلبات',
            'cake-orders'     => 'تقرير طلبات الكيك الخاصة',
            'inventory'       => 'تقرير المخزون',
            'stock-movements' => 'تقرير حركات المخزون',
            'low-stock'       => 'تقرير المخزون المنخفض',
            'stock-transfers' => 'تقرير التحويلات',
            'payments'        => 'تقرير الدفعات',
            'invoices'        => 'تقرير الفواتير',
            'cash-sessions'   => 'تقرير جلسات الكاشير',
            'daily-sales'     => 'تقرير المبيعات اليومية',
            'monthly-sales'   => 'تقرير المبيعات الشهرية',
            'branch-sales'    => 'تقرير أداء الفروع',
            'product-sales'   => 'تقرير مبيعات المنتجات',
            'collections'     => 'تقرير التحصيلات',
            'outstanding'     => 'تقرير الأرصدة المعلقة',
            'activity-logs'   => 'سجل النشاطات',
        ];

        $typeName = $reportTypes[$this->schedule->report_type] ?? $this->schedule->report_type;

        return new Envelope(
            subject: "{$typeName} — {$this->dateFrom} إلى {$this->dateTo}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.scheduled-report',
            with: [
                'schedule'  => $this->schedule,
                'dateFrom'  => $this->dateFrom,
                'dateTo'    => $this->dateTo,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->filePath)
                ->as($this->fileName)
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
