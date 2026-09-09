<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Notifications\Notification;

class InvoiceCancelledNotification extends Notification
{
    public function __construct(private readonly Invoice $invoice, private readonly string $reason = '') {}

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'invoice',
            'title'   => 'إلغاء فاتورة',
            'message' => "تم إلغاء الفاتورة {$this->invoice->invoice_number}" . ($this->reason ? " — {$this->reason}" : ''),
            'url'     => '/invoices/' . $this->invoice->id,
            'icon'    => 'invoice',
        ];
    }
}
