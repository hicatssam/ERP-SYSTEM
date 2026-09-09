<?php

namespace App\Notifications;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ExpenseWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Expense $expense,
        private readonly string $event,
        private readonly User $actor,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $labels = [
            'submitted' => 'مصروف جديد بانتظار الاعتماد',
            'approved' => 'تم اعتماد المصروف',
            'rejected' => 'تم رفض المصروف',
            'posted' => 'تم ترحيل المصروف محاسبيًا',
            'voided' => 'تم عكس المصروف محاسبيًا',
        ];

        return [
            'fingerprint' => 'expense_' . $this->expense->id . '_' . $this->event . '_' . $notifiable->id,
            'type' => 'expense_workflow',
            'title' => $labels[$this->event] ?? 'تحديث على مصروف',
            'message' => $this->expense->expense_number . ' — ₪' . number_format((float) $this->expense->amount, 2),
            'expense_id' => $this->expense->id,
            'location_id' => $this->expense->location_id,
            'actor_id' => $this->actor->id,
            'url' => '/financial/expenses/' . $this->expense->id,
            'priority' => in_array($this->event, ['submitted', 'rejected'], true) ? 'high' : 'medium',
        ];
    }
}
