<?php

namespace App\Enums;

enum ShowroomSweetsRequestStatus: string
{
    // Simplified visible workflow.
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    // Legacy values kept for historical compatibility.
    case Submitted = 'submitted';
    case ReadyForDispatch = 'ready_for_dispatch';
    case OutForDelivery = 'out_for_delivery';
    case ReceivedAtBranch = 'received_at_branch';
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
            'cancelled' => 'badge-inactive',
            default => 'badge-secondary',
        };
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

    public function canBeCancelledByBranch(): bool
    {
        return in_array(
            $this->workflowValue(),
            ['pending', 'in_progress', 'ready'],
            true
        );
    }

    public function isTerminal(): bool
    {
        return in_array(
            $this->workflowValue(),
            ['completed', 'cancelled'],
            true
        );
    }

    public function isFactoryStage(): bool
    {
        return in_array(
            $this->workflowValue(),
            ['pending', 'in_progress', 'ready'],
            true
        );
    }

    public function isDeliveryStage(): bool
    {
        return $this->workflowValue() === 'ready';
    }

    public function isReceived(): bool
    {
        return $this->workflowValue() === 'completed';
    }

    public function workflowValue(): string
    {
        return match ($this) {
            self::Submitted,
            self::Pending => 'pending',

            self::InProgress => 'in_progress',

            self::Ready,
            self::ReadyForDispatch,
            self::OutForDelivery => 'ready',

            self::ReceivedAtBranch,
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
