<?php

namespace App\Enums;

enum SalesChannelType: string
{
    case Direct = 'direct';
    case DeliveryApp = 'delivery_app';
    case Website = 'website';
    case Online = 'online';
    case Social = 'social';
    case Phone = 'phone';
    case Marketplace = 'marketplace';
    case Aggregator = 'aggregator';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'بيع مباشر / فرع',
            self::DeliveryApp => 'تطبيق توصيل',
            self::Website => 'موقع إلكتروني',
            self::Online => 'بيع أونلاين',
            self::Social => 'تواصل اجتماعي',
            self::Phone => 'هاتف',
            self::Marketplace => 'منصة خارجية',
            self::Aggregator => 'منصة تجميع طلبات',
            self::Other => 'أخرى',
        };
    }
}