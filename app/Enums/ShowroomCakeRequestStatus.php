<?php

namespace App\Enums;

enum ShowroomCakeRequestStatus: string
{
    case Draft      = 'draft';
    case Submitted  = 'submitted';
    case InProgress = 'in_progress';
    case Fulfilled  = 'fulfilled';
    case Rejected   = 'rejected';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Draft      => 'مسودة',
            self::Submitted  => 'مُرسل للمصنع',
            self::InProgress => 'قيد التنفيذ',
            self::Fulfilled  => 'تم التنفيذ',
            self::Rejected   => 'مرفوض',
            self::Cancelled  => 'ملغى',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Draft      => 'badge-secondary',
            self::Submitted  => 'badge-pending',
            self::InProgress => 'badge-warning',
            self::Fulfilled  => 'badge-success',
            self::Rejected   => 'badge-danger',
            self::Cancelled  => 'badge-muted',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Fulfilled, self::Rejected, self::Cancelled]);
    }

    /** Returns valid next statuses from current status */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::Draft      => [self::Submitted, self::Cancelled],
            self::Submitted  => [self::InProgress, self::Rejected, self::Cancelled],
            self::InProgress => [self::Fulfilled, self::Cancelled],
            default          => [],
        };
    }
}
