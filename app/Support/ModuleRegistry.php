<?php

namespace App\Support;

use App\Enums\ModuleType;

final class ModuleRegistry
{
    public static function modules(): array
    {
        $core = ModuleType::CORE->value;
        $industry = ModuleType::INDUSTRY->value;
        $optional = ModuleType::OPTIONAL->value;

        return [
            ['code'=>'dashboard','name'=>'لوحة التحكم','description'=>'لوحة التحكم الرئيسية ومؤشرات الأداء.','type'=>$core,'icon'=>'dashboard','route_prefix'=>'dashboard','is_core'=>true,'is_system'=>true,'sort_order'=>10,'implemented'=>true,'initial_active'=>true],
            ['code'=>'users','name'=>'المستخدمون','description'=>'حسابات مستخدمي النظام.','type'=>$core,'icon'=>'users','route_prefix'=>'users','is_core'=>true,'is_system'=>true,'sort_order'=>20,'implemented'=>true,'initial_active'=>true],
            ['code'=>'employees','name'=>'الموظفون','description'=>'بيانات الموظفين وربطهم بالمواقع.','type'=>$core,'icon'=>'employees','route_prefix'=>'employees','is_core'=>true,'is_system'=>false,'sort_order'=>30,'implemented'=>true,'initial_active'=>true],
            ['code'=>'roles_permissions','name'=>'الأدوار والصلاحيات','description'=>'نظام Spatie الحالي للأدوار والصلاحيات.','type'=>$core,'icon'=>'shield','route_prefix'=>'roles','is_core'=>true,'is_system'=>true,'sort_order'=>40,'implemented'=>true,'initial_active'=>true],
            ['code'=>'locations','name'=>'المواقع والفروع','description'=>'الفروع والمصنع ونطاق المواقع.','type'=>$core,'icon'=>'location','route_prefix'=>'locations','is_core'=>true,'is_system'=>true,'sort_order'=>50,'implemented'=>true,'initial_active'=>true],
            ['code'=>'products','name'=>'المنتجات','description'=>'كتالوج المنتجات الأساسي.','type'=>$core,'icon'=>'products','route_prefix'=>'products','is_core'=>true,'is_system'=>false,'sort_order'=>60,'implemented'=>true,'initial_active'=>true],
            ['code'=>'categories','name'=>'الفئات','description'=>'تصنيف المنتجات.','type'=>$core,'icon'=>'categories','route_prefix'=>'categories','is_core'=>true,'is_system'=>false,'sort_order'=>70,'implemented'=>true,'initial_active'=>true],
            ['code'=>'customers','name'=>'العملاء','description'=>'ملفات العملاء وحساباتهم.','type'=>$core,'icon'=>'customers','route_prefix'=>'customers','is_core'=>true,'is_system'=>false,'sort_order'=>80,'implemented'=>true,'initial_active'=>true],
            ['code'=>'sales','name'=>'المبيعات','description'=>'الطلبات وتدفقات البيع الحالية.','type'=>$core,'icon'=>'sales','route_prefix'=>'orders','is_core'=>true,'is_system'=>false,'sort_order'=>90,'implemented'=>true,'initial_active'=>true],
            ['code'=>'inventory','name'=>'المخزون','description'=>'المخزون والحركات والجرد والطلبات والتحويلات.','type'=>$core,'icon'=>'inventory','route_prefix'=>'inventory','is_core'=>true,'is_system'=>false,'sort_order'=>100,'implemented'=>true,'initial_active'=>true],
            ['code'=>'purchasing','name'=>'المشتريات','description'=>'أوامر الشراء والاستلام والمرتجعات.','type'=>$core,'icon'=>'purchasing','route_prefix'=>'procurement','is_core'=>true,'is_system'=>false,'sort_order'=>110,'implemented'=>true,'initial_active'=>true],
            ['code'=>'suppliers','name'=>'الموردون','description'=>'إدارة الموردين ومنتجاتهم.','type'=>$core,'icon'=>'suppliers','route_prefix'=>'suppliers','is_core'=>true,'is_system'=>false,'sort_order'=>120,'implemented'=>true,'initial_active'=>true],
            ['code'=>'finance','name'=>'المالية','description'=>'لوحة المالية والفترات والتحصيلات.','type'=>$core,'icon'=>'finance','route_prefix'=>'financial','is_core'=>true,'is_system'=>false,'sort_order'=>130,'implemented'=>true,'initial_active'=>true],
            ['code'=>'accounting','name'=>'المحاسبة','description'=>'طبقة القيود والملخصات المالية الموجودة حاليًا.','type'=>$core,'icon'=>'accounting','route_prefix'=>'financial','is_core'=>true,'is_system'=>false,'sort_order'=>140,'implemented'=>true,'initial_active'=>true],
            ['code'=>'payments','name'=>'المدفوعات','description'=>'الحركات المالية والتحقق والتصحيح والاسترداد.','type'=>$core,'icon'=>'payments','route_prefix'=>'payments','is_core'=>true,'is_system'=>false,'sort_order'=>150,'implemented'=>true,'initial_active'=>true],
            ['code'=>'invoices','name'=>'الفواتير','description'=>'الفواتير والطباعة والتحصيل.','type'=>$core,'icon'=>'invoices','route_prefix'=>'invoices','is_core'=>true,'is_system'=>false,'sort_order'=>160,'implemented'=>true,'initial_active'=>true],
            ['code'=>'reports','name'=>'التقارير','description'=>'التقارير والتصدير والتقارير المجدولة.','type'=>$core,'icon'=>'reports','route_prefix'=>'reports','is_core'=>true,'is_system'=>false,'sort_order'=>170,'implemented'=>true,'initial_active'=>true],
            ['code'=>'notifications','name'=>'الإشعارات','description'=>'إشعارات النظام الحالية.','type'=>$core,'icon'=>'notifications','route_prefix'=>'notifications','is_core'=>true,'is_system'=>false,'sort_order'=>180,'implemented'=>true,'initial_active'=>true],
            ['code'=>'chat','name'=>'المحادثات الداخلية','description'=>'المحادثات الداخلية الحالية.','type'=>$optional,'icon'=>'chat','route_prefix'=>'chat','is_core'=>false,'is_system'=>false,'sort_order'=>190,'implemented'=>true,'initial_active'=>true],
            ['code'=>'payment_methods','name'=>'طرق الدفع','description'=>'طرق الدفع وربطها بالمواقع.','type'=>$core,'icon'=>'payments','route_prefix'=>'payment-methods','is_core'=>true,'is_system'=>false,'sort_order'=>200,'implemented'=>true,'initial_active'=>true],
            ['code'=>'sales_channels','name'=>'قنوات البيع','description'=>'قنوات البيع والتسعير والتقارير.','type'=>$core,'icon'=>'channels','route_prefix'=>'settings/sales-channels','is_core'=>true,'is_system'=>false,'sort_order'=>210,'implemented'=>true,'initial_active'=>true],
            ['code'=>'settings','name'=>'الإعدادات','description'=>'الإعدادات والهوية والثيم.','type'=>$core,'icon'=>'settings','route_prefix'=>'settings','is_core'=>true,'is_system'=>true,'sort_order'=>220,'implemented'=>true,'initial_active'=>true],

            ['code'=>'bakery','name'=>'المخبوزات والحلويات','description'=>'وظائف الحلويات الموجودة مثل طلبات حلويات الفروع.','type'=>$industry,'icon'=>'bakery','route_prefix'=>'showroom-sweets-requests','is_core'=>false,'is_system'=>false,'sort_order'=>300,'implemented'=>true,'initial_active'=>true],
            ['code'=>'cake_orders','name'=>'طلبات الكيك','description'=>'طلبات الكيك الخاصة وطلبات كيك الفروع وتدفق المصنع.','type'=>$industry,'icon'=>'cake','route_prefix'=>'cake-orders','is_core'=>false,'is_system'=>false,'sort_order'=>310,'implemented'=>true,'initial_active'=>true],

            ['code'=>'restaurant','name'=>'المطعم','description'=>'أساس تشغيل المطعم والطلبات وأنواع الخدمة وربط الفروع.','type'=>$industry,'icon'=>'restaurant','route_prefix'=>'restaurant','is_core'=>false,'is_system'=>false,'sort_order'=>400,'implemented'=>true,'initial_active'=>false],
            ['code'=>'cafe','name'=>'الكافيه','description'=>'تشغيل قطاع الكافيه فوق محرك المطعم مع POS والطاولات والبار/KDS والوصفات والتكلفة.','type'=>$industry,'icon'=>'cafe','route_prefix'=>'restaurant','is_core'=>false,'is_system'=>false,'sort_order'=>410,'implemented'=>true,'initial_active'=>false],
            ['code'=>'restaurant_pos','name'=>'نقطة بيع المطعم','description'=>'واجهة POS للمطعم مبنية فوق الطلبات والمدفوعات وقنوات البيع الحالية.','type'=>$optional,'icon'=>'pos','route_prefix'=>'restaurant/pos','is_core'=>false,'is_system'=>false,'sort_order'=>420,'implemented'=>true,'initial_active'=>false],
            ['code'=>'restaurant_tables','name'=>'طاولات المطعم','description'=>'مناطق المطعم والطاولات والجلسات المفتوحة وربط الطلب بالطاولة.','type'=>$optional,'icon'=>'tables','route_prefix'=>'restaurant/tables','is_core'=>false,'is_system'=>false,'sort_order'=>430,'implemented'=>true,'initial_active'=>false],
            ['code'=>'kitchen','name'=>'المطبخ','description'=>'محطات المطبخ والتوجيه وتذاكر التحضير وربطها بطلبات المطعم.','type'=>$optional,'icon'=>'kitchen','route_prefix'=>'kitchen','is_core'=>false,'is_system'=>false,'sort_order'=>440,'implemented'=>true,'initial_active'=>false],
            ['code'=>'kds','name'=>'شاشة المطبخ KDS','description'=>'شاشة تشغيل مباشرة للمطبخ مع الحالات والأولوية والتنبيه الصوتي.','type'=>$optional,'icon'=>'kds','route_prefix'=>'kds','is_core'=>false,'is_system'=>false,'sort_order'=>450,'implemented'=>true,'initial_active'=>false],
            ['code'=>'production','name'=>'الإنتاج','description'=>'دفعات إنتاج فعلية مع حجز وصرف المواد والتكلفة والتتبع والمخرجات.','type'=>$optional,'icon'=>'production','route_prefix'=>'production/batches','is_core'=>false,'is_system'=>false,'sort_order'=>460,'implemented'=>true,'initial_active'=>false],
            ['code'=>'recipes','name'=>'الوصفات','description'=>'وصفات/BOM بإصدارات معتمدة وتكلفة مواد معيارية وآمنة.','type'=>$optional,'icon'=>'recipes','route_prefix'=>'production/recipes','is_core'=>false,'is_system'=>false,'sort_order'=>470,'implemented'=>true,'initial_active'=>false],
            ['code'=>'quality_control','name'=>'مراقبة الجودة','description'=>'فحص جودة دفعات الإنتاج مع قبول كامل/جزئي/رفض وربط الناتج بالمخزون.','type'=>$optional,'icon'=>'quality','route_prefix'=>'production/quality','is_core'=>false,'is_system'=>false,'sort_order'=>480,'implemented'=>true,'initial_active'=>false],

            // Sprint 08 — CRM + Loyalty + Delivery
            ['code'=>'crm','name'=>'إدارة علاقات العملاء CRM','description'=>'ملف العميل 360 والعناوين والوسوم والتفاعلات والمتابعات مع احترام نطاق الفروع الحالي.','type'=>$optional,'icon'=>'customers','route_prefix'=>'crm','is_core'=>false,'is_system'=>false,'sort_order'=>485,'implemented'=>true,'initial_active'=>false],
            ['code'=>'delivery','name'=>'التوصيل','description'=>'مناطق التوصيل ومهام التسليم وتعيين السائق وتتبع الحالة والسجل التشغيلي.','type'=>$optional,'icon'=>'delivery','route_prefix'=>'delivery','is_core'=>false,'is_system'=>false,'sort_order'=>490,'implemented'=>true,'initial_active'=>false],
            ['code'=>'loyalty','name'=>'الولاء','description'=>'برنامج نقاط العملاء مع الحسابات وسجل الحركات والكسب والاستبدال والعكس الآمن.','type'=>$optional,'icon'=>'loyalty','route_prefix'=>'loyalty','is_core'=>false,'is_system'=>false,'sort_order'=>500,'implemented'=>true,'initial_active'=>false],
            ['code'=>'clothing','name'=>'الملابس','description'=>'قطاع الملابس — مسجل للمستقبل فقط.','type'=>$industry,'icon'=>'clothing','route_prefix'=>'clothing','is_core'=>false,'is_system'=>false,'sort_order'=>510,'implemented'=>false,'initial_active'=>false],
            ['code'=>'shoes','name'=>'الأحذية','description'=>'قطاع الأحذية — مسجل للمستقبل فقط.','type'=>$industry,'icon'=>'shoes','route_prefix'=>'shoes','is_core'=>false,'is_system'=>false,'sort_order'=>520,'implemented'=>false,'initial_active'=>false],
            ['code'=>'product_variants','name'=>'متغيرات المنتجات','description'=>'SKU وباركود وسعر وخصائص مستقلة لكل متغير منتج.','type'=>$optional,'icon'=>'variants','route_prefix'=>'products','is_core'=>false,'is_system'=>false,'sort_order'=>530,'implemented'=>true,'initial_active'=>false],
            ['code'=>'sizes','name'=>'المقاسات','description'=>'كتالوج مقاسات مرن للملابس والأحذية والمنتجات ذات المتغيرات.','type'=>$optional,'icon'=>'sizes','route_prefix'=>'catalog/sizes','is_core'=>false,'is_system'=>false,'sort_order'=>540,'implemented'=>true,'initial_active'=>false],
            ['code'=>'colors','name'=>'الألوان','description'=>'كتالوج ألوان موحد مع HEX اختياري للمتغيرات.','type'=>$optional,'icon'=>'colors','route_prefix'=>'catalog/colors','is_core'=>false,'is_system'=>false,'sort_order'=>550,'implemented'=>true,'initial_active'=>false],
            ['code'=>'costing','name'=>'الربحية والتكلفة','description'=>'COGS ومصروفات معتمدة وربحية المنتجات والفروع مع تكامل الفترات المالية.','type'=>$optional,'icon'=>'finance','route_prefix'=>'financial/profitability','is_core'=>false,'is_system'=>false,'sort_order'=>555,'implemented'=>true,'initial_active'=>false],
            ['code'=>'brands','name'=>'العلامات التجارية','description'=>'علامات تجارية موحدة للمنتجات والتجزئة والأزياء.','type'=>$optional,'icon'=>'brands','route_prefix'=>'catalog/brands','is_core'=>false,'is_system'=>false,'sort_order'=>560,'implemented'=>true,'initial_active'=>false],
        ];
    }

    public static function dependencies(): array
    {
        return [
            'categories' => ['products'],
            'sales' => ['products', 'customers'],
            'inventory' => ['products', 'locations'],
            'purchasing' => ['suppliers', 'inventory', 'products'],
            'invoices' => ['sales'],
            'payments' => ['invoices', 'payment_methods'],
            'finance' => ['invoices', 'payments'],
            'accounting' => ['finance'],
            'reports' => ['sales'],
            'sales_channels' => ['sales'],
            'cake_orders' => ['sales', 'customers', 'products'],
            'bakery' => ['sales', 'products', 'inventory'],
            'restaurant' => ['sales', 'products', 'customers', 'locations'],
            'cafe' => [
                'restaurant',
                'restaurant_pos',
                'restaurant_tables',
                'product_variants',
                'sizes',
                'kitchen',
                'kds',
                'recipes',
                'costing',
            ],
            'restaurant_pos' => [
                'restaurant',
                'sales',
                'products',
                'payments',
                'payment_methods',
                'sales_channels',
            ],
            'restaurant_tables' => ['restaurant', 'locations'],
            'kitchen' => ['restaurant', 'sales', 'products', 'locations'],
            'kds' => ['kitchen'],
            'recipes' => ['inventory', 'products'],
            'production' => ['recipes', 'inventory', 'products', 'locations'],
            'quality_control' => ['production'],

            // Sprint 08
            'crm' => ['customers', 'locations'],
            'loyalty' => ['crm', 'sales'],
            'delivery' => ['sales', 'customers', 'locations'],

            'costing' => ['finance', 'accounting', 'sales', 'inventory'],

            'product_variants' => ['products'],
            'sizes' => ['product_variants'],
            'colors' => ['product_variants'],
            'brands' => ['products'],
            'clothing' => ['products', 'product_variants', 'sizes', 'colors', 'brands'],
            'shoes' => ['products', 'product_variants', 'sizes', 'colors', 'brands'],
        ];
    }
}
