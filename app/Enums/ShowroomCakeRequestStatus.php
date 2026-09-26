<?php

namespace App\Enums;

enum ShowroomCakeRequestStatus: string
{
    // Simplified visible workflow.
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    // Legacy values kept so historical rows remain readable before migration.
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Fulfilled = 'fulfilled';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this->workflowValue()) {
            'pending' => 'قيد المراجعة',
            'in_progress' => 'قيد التنفيذ',
            'ready' => 'جاهز للاستلام',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
            default => 'غير محدد',
        };
    }

    public function badgeClass(): string
    {
        return match ($this->workflowValue()) {
            'pending' => 'badge-pending',
            'in_progress' => 'badge-warning',
            'ready' => 'badge-info',
            'completed' => 'badge-success',
            'cancelled' => 'badge-danger',
            default => 'badge-secondary',
        };
    }

    public function isTerminal(): bool
    {
        return in_array(
            $this->workflowValue(),
            ['completed', 'cancelled'],
            true
        );
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this->workflowValue()) {
            'pending' => [
                self::InProgress,
                self::Cancelled,
            ],
            'in_progress' => [
                self::Ready,
                self::Cancelled,
            ],
            'ready' => [
                self::Completed,
                self::Cancelled,
            ],
            default => [],
        };
    }

    public function workflowValue(): string
    {
        return match ($this) {
            self::Draft,
            self::Submitted,
            self::Pending => 'pending',

            self::InProgress => 'in_progress',

            self::Ready => 'ready',

            self::Fulfilled,
            self::Completed => 'completed',

            self::Rejected,
            self::Cancelled => 'cancelled',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function workflowCases(): array
    {
        return [
            self::Pending,
            self::InProgress,
            self::Ready,
            self::Completed,
            self::Cancelled,
        ];
    }
}
