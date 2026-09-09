<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Notifications\Notification;

class InvoiceCreatedNotification extends Notification
{
    public function __construct(private readonly Invoice $invoice) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'invoice',
            'title'   => 'فاتورة جديدة',
            'message' => "تم إنشاء الفاتورة رقم {$this->invoice->invoice_number} بقيمة {$this->invoice->total_amount} ₪",
            'url'     => '/invoices/' . $this->invoice->id,
            'icon'    => 'invoice',
        ];
    }
}
