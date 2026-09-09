<?php

namespace Database\Seeders;

use App\Models\SystemCurrency;
use Illuminate\Database\Seeder;

class SystemCurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            [
                'code' => 'ILS',
                'name_ar' => 'شيكل إسرائيلي',
                'name_en' => 'Israeli New Shekel',
                'symbol' => '₪',
                'icon' => '₪',
                'decimal_places' => 2,
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'USD',
                'name_ar' => 'دولار أمريكي',
                'name_en' => 'US Dollar',
                'symbol' => '$',
                'icon' => '$',
                'decimal_places' => 2,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 2,
            ],
            [
                'code' => 'EUR',
                'name_ar' => 'يورو',
                'name_en' => 'Euro',
                'symbol' => '€',
                'icon' => '€',
                'decimal_places' => 2,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 3,
            ],
            [
                'code' => 'JOD',
                'name_ar' => 'دينار أردني',
                'name_en' => 'Jordanian Dinar',
                'symbol' => 'د.أ',
                'icon' => 'JD',
                'decimal_places' => 3,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 4,
            ],
            [
                'code' => 'SAR',
                'name_ar' => 'ريال سعودي',
                'name_en' => 'Saudi Riyal',
                'symbol' => 'ر.س',
                'icon' => 'SAR',
                'decimal_places' => 2,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 5,
            ],
            [
                'code' => 'TRY',
                'name_ar' => 'ليرة تركية',
                'name_en' => 'Turkish Lira',
                'symbol' => '₺',
                'icon' => '₺',
                'decimal_places' => 2,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 6,
            ],
        ];

        foreach ($currencies as $currency) {
            SystemCurrency::query()->updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}
