<?php

namespace App\Enums;

enum StockRequestStatus: string
{
    case PendingFactoryReview = 'pending_factory_review';
    case Accepted             = 'accepted';
    case PartiallyAccepted    = 'partially_accepted';
    case Rejected             = 'rejected';
    case Preparing            = 'preparing';
    case Dispatched           = 'dispatched';
    case Received             = 'received';
    case DiscrepancyOpen      = 'discrepancy_open';
    case Resolved             = 'resolved';

    public function label(): string
    {
        return match($this) {
            self::PendingFactoryReview => 'بانتظار مراجعة المصنع',
            self::Accepted             => 'مقبول',
            self::PartiallyAccepted    => 'مقبول جزئياً',
            self::Rejected             => 'مرفوض',
            self::Preparing            => 'قيد التجهيز',
            self::Dispatched           => 'تم الشحن',
            self::Received             => 'مستلم',
            self::DiscrepancyOpen      => 'فروقات مفتوحة',
            self::Resolved             => 'محلول',
        };
    }
}
