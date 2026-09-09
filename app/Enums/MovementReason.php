<?php

namespace App\Enums;

/**
 * استبدال مباشر للـ enum الحالي مع الإبقاء على كل القيم القديمة.
 * `movement_type` يحدد الاتجاه، و`reason` يحدد العملية التجارية.
 */
enum MovementReason: string
{
    case FactoryProduction = 'factory_production';
    case ProductionIn = 'production_in';
    case ProductionConsumption = 'production_consumption';
    case ProductionMaterialReturn = 'production_material_return';
    case StockReceived = 'stock_received';
    case PurchaseReceipt = 'purchase_receipt';
    case PurchaseReturn = 'purchase_return';
    case Order = 'order';
    case OrderSale = 'order_sale';
    case SaleReturn = 'sale_return';
    case OrderCancellation = 'order_cancellation';
    case Damaged = 'damaged';
    case Damage = 'damage';
    case Expired = 'expired';
    case Waste = 'waste';
    case Return = 'return';
    case InternalUse = 'internal_use';
    case Correction = 'correction';
    case OpeningStock = 'opening_stock';
    case StockCountAdjustment = 'stock_count_adjustment';
    case ManualAdjustment = 'manual_adjustment';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case TransferDispatch = 'transfer_dispatch';
    case TransferReceipt = 'transfer_receipt';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case StockRequestReceipt = 'stock_request_receipt';

    public function label(): string
    {
        return match ($this) {
            self::FactoryProduction => 'إنتاج المصنع',
            self::ProductionIn => 'إدخال إنتاج',
            self::ProductionConsumption => 'استهلاك إنتاج',
            self::ProductionMaterialReturn => 'إرجاع مواد من الإنتاج',
            self::StockReceived => 'مخزون مستلم',
            self::PurchaseReceipt => 'استلام مشتريات',
            self::PurchaseReturn => 'إرجاع إلى المورد',
            self::Order, self::OrderSale => 'بيع',
            self::SaleReturn => 'مردود مبيعات',
            self::OrderCancellation => 'إلغاء طلب',
            self::Damaged, self::Damage => 'تالف',
            self::Expired => 'منتهي الصلاحية',
            self::Waste => 'هدر',
            self::Return => 'مردود',
            self::InternalUse => 'استخدام داخلي',
            self::Correction => 'تصحيح',
            self::OpeningStock => 'رصيد افتتاحي',
            self::StockCountAdjustment => 'تعديل جرد',
            self::ManualAdjustment => 'تعديل يدوي',
            self::AdjustmentIn => 'تسوية زيادة',
            self::AdjustmentOut => 'تسوية نقص',
            self::TransferDispatch, self::TransferOut => 'إرسال تحويل',
            self::TransferReceipt, self::TransferIn => 'استلام تحويل',
            self::StockRequestReceipt => 'استلام طلب مخزون',
        };
    }
}
