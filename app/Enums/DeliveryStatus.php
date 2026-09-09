<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case PickedUp = 'picked_up';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار التعيين',
            self::Assigned => 'تم تعيين السائق',
            self::PickedUp => 'تم الاستلام من الفرع',
            self::OutForDelivery => 'في الطريق للعميل',
            self::Delivered => 'تم التسليم',
            self::Failed => 'تعذر التسليم',
            self::Cancelled => 'ملغي',
        };
    }

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Assigned, self::Cancelled],
            self::Assigned => [self::PickedUp, self::Cancelled],
            self::PickedUp => [self::OutForDelivery, self::Failed, self::Cancelled],
            self::OutForDelivery => [self::Delivered, self::Failed],
            self::Failed => [self::Assigned, self::Cancelled],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
