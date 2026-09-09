<?php

namespace App\Support;

final class BusinessProfileRegistry
{
    private static function common(): array
    {
        return [
            'dashboard',
            'users',
            'employees',
            'roles_permissions',
            'locations',

            'products',
            'categories',

            'customers',
            'sales',

            'inventory',
            'purchasing',
            'suppliers',

            'finance',
            'accounting',
            'payments',
            'invoices',

            'reports',
            'notifications',
            'chat',

            'payment_methods',
            'sales_channels',

            'settings',
        ];
    }

    public static function profiles(): array
    {
        $common = self::common();

        return [

            /*
            |--------------------------------------------------------------------------
            | Bakery & Sweets
            |--------------------------------------------------------------------------
            */
            [
                'code' => 'bakery_sweets',
                'name' => 'مخبز وحلويات',
                'description' => 'تشغيل المخابز والحلويات مع المبيعات والإنتاج وخدمة العملاء.',
                'icon' => 'bakery',
                'sort_order' => 10,

                'modules' => array_merge(
                    $common,
                    [
                        'bakery',
                        'cake_orders',

                        'production',
                        'recipes',
                        'quality_control',

                        // Sprint 08
                        'crm',
                        'loyalty',
                        'delivery',
                    ]
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Restaurant
            |--------------------------------------------------------------------------
            */
            [
                'code' => 'restaurant',
                'name' => 'مطعم',
                'description' => 'تشغيل المطاعم مع POS والطاولات والمطبخ وKDS والعملاء والولاء والتوصيل.',
                'icon' => 'restaurant',
                'sort_order' => 20,

                'modules' => array_merge(
                    $common,
                    [
                        'restaurant',
                        'restaurant_pos',
                        'restaurant_tables',

                        'kitchen',
                        'kds',

                        // Sprint 08
                        'crm',
                        'loyalty',
                        'delivery',
                    ]
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Cafe
            |--------------------------------------------------------------------------
            */
            [
                'code' => 'cafe',
                'name' => 'كافيه',
                'description' => 'تشغيل الكافيه مع نقطة البيع والمطبخ وخدمة العملاء والولاء والتوصيل.',
                'icon' => 'cafe',
                'sort_order' => 30,

                'modules' => array_merge(
                    $common,
                    [
                        // Cafe is an industry profile marker. Runtime operations
                        // intentionally reuse the proven restaurant engine.
                        'cafe',
                        'restaurant',
                        'restaurant_pos',
                        'restaurant_tables',
                        'product_variants',
                        'sizes',
                        'kitchen',
                        'kds',
                        'recipes',
                        'costing',

                        // Sprint 08
                        'crm',
                        'loyalty',
                        'delivery',
                    ]
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Clothing
            |--------------------------------------------------------------------------
            */
            [
                'code' => 'clothing',
                'name' => 'ملابس',
                'description' => 'متجر ملابس مع المتغيرات والمقاسات والألوان والعملاء والولاء والتوصيل.',
                'icon' => 'clothing',
                'sort_order' => 40,

                'modules' => array_merge(
                    $common,
                    [
                        'clothing',
                        'product_variants',
                        'sizes',
                        'colors',
                        'brands',

                        // Sprint 08
                        'crm',
                        'loyalty',
                        'delivery',
                    ]
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Shoes
            |--------------------------------------------------------------------------
            */
            [
                'code' => 'shoes',
                'name' => 'أحذية',
                'description' => 'متجر أحذية مع المقاسات والمتغيرات والعملاء والولاء والتوصيل.',
                'icon' => 'shoes',
                'sort_order' => 50,

                'modules' => array_merge(
                    $common,
                    [
                        'shoes',
                        'product_variants',
                        'sizes',
                        'colors',
                        'brands',

                        // Sprint 08
                        'crm',
                        'loyalty',
                        'delivery',
                    ]
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Retail
            |--------------------------------------------------------------------------
            */
            [
                'code' => 'retail',
                'name' => 'تجزئة',
                'description' => 'بيع تجزئة عام مع الكتالوج والمخزون والمبيعات وإدارة العملاء والولاء والتوصيل.',
                'icon' => 'retail',
                'sort_order' => 60,

                'modules' => array_merge(
                    $common,
                    [
                        'product_variants',
                        'brands',

                        // Sprint 08
                        'crm',
                        'loyalty',
                        'delivery',
                    ]
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | General Trading
            |--------------------------------------------------------------------------
            */
            [
                'code' => 'general_trading',
                'name' => 'تجارة عامة',
                'description' => 'نظام ERP عام للمبيعات والمخزون والمشتريات والمالية وإدارة العملاء.',
                'icon' => 'trading',
                'sort_order' => 70,

                'modules' => array_merge(
                    $common,
                    [
                        // CRM مناسب للتجارة العامة أيضاً.
                        'crm',
                    ]
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Manufacturing
            |--------------------------------------------------------------------------
            */
            [
                'code' => 'manufacturing',
                'name' => 'تصنيع',
                'description' => 'نظام تصنيع مع الإنتاج والوصفات والجودة والمخزون والمبيعات.',
                'icon' => 'manufacturing',
                'sort_order' => 80,

                'modules' => array_merge(
                    $common,
                    [
                        'production',
                        'recipes',
                        'quality_control',
                    ]
                ),
            ],
        ];
    }

    public static function bundles(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Bakery Operations
            |--------------------------------------------------------------------------
            */
            [
                'code' => 'bakery_operations',
                'profile' => 'bakery_sweets',
                'name' => 'تشغيل المخبز والحلويات',
                'description' => 'الحلويات وطلبات الكيك والإنتاج والوصفات والجودة.',
                'sort_order' => 10,

                'modules' => [
                    'bakery',
                    'cake_orders',
                    'production',
                    'recipes',
                    'quality_control',
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Restaurant Operations
            |--------------------------------------------------------------------------
            */
            [
                'code' => 'restaurant_operations',
                'profile' => 'restaurant',
                'name' => 'تشغيل المطعم',
                'description' => 'المطعم وPOS والطاولات والمطبخ وKDS.',
                'sort_order' => 20,

                'modules' => [
                    'restaurant',
                    'restaurant_pos',
                    'restaurant_tables',
                    'kitchen',
                    'kds',
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Fashion Catalog
            |--------------------------------------------------------------------------
            */
            [
                'code' => 'fashion_catalog',
                'profile' => 'clothing',
                'name' => 'كتالوج الأزياء',
                'description' => 'المتغيرات والمقاسات والألوان والعلامات التجارية.',
                'sort_order' => 30,

                'modules' => [
                    'product_variants',
                    'sizes',
                    'colors',
                    'brands',
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Customer Growth — Sprint 08
            |--------------------------------------------------------------------------
            */
            [
                'code' => 'customer_growth',
                'profile' => null,
                'name' => 'النمو وخدمة العملاء',
                'description' => 'إدارة علاقات العملاء CRM والولاء والتوصيل.',
                'sort_order' => 40,

                /*
                 * الترتيب هنا مقصود:
                 *
                 * CRM أولاً
                 * Loyalty يعتمد على CRM
                 * Delivery بعدهما
                 */
                'modules' => [
                    'crm',
                    'loyalty',
                    'delivery',
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Advanced Finance
            |--------------------------------------------------------------------------
            */
            [
                'code' => 'advanced_finance',
                'profile' => null,
                'name' => 'الربحية والتكلفة',
                'description' => 'COGS والمصروفات وربحية المنتجات والفروع.',
                'sort_order' => 50,

                'modules' => [
                    'costing',
                ],
            ],
        ];
    }
}