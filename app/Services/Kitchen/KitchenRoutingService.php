<?php

namespace App\Services\Kitchen;

use App\Enums\KitchenRoutingSource;
use App\Models\KitchenCategoryRoute;
use App\Models\KitchenProductRoute;
use App\Models\KitchenStation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KitchenRoutingService
{
    /**
     * @return array{0: KitchenStation, 1: KitchenRoutingSource}
     */
    public function resolve(
        int $locationId,
        Product $product
    ): array {
        $productRoute = KitchenProductRoute::query()
            ->where('location_id', $locationId)
            ->where('product_id', $product->id)
            ->with('station')
            ->first();

        if ($productRoute?->station?->is_active) {
            return [
                $productRoute->station,
                KitchenRoutingSource::PRODUCT,
            ];
        }

        if ($product->category_id) {
            $categoryRoute = KitchenCategoryRoute::query()
                ->where('location_id', $locationId)
                ->where('category_id', $product->category_id)
                ->with('station')
                ->first();

            if ($categoryRoute?->station?->is_active) {
                return [
                    $categoryRoute->station,
                    KitchenRoutingSource::CATEGORY,
                ];
            }
        }

        $default = KitchenStation::query()
            ->forLocation($locationId)
            ->active()
            ->where('is_default', true)
            ->orderBy('sort_order')
            ->first();

        if ($default) {
            return [
                $default,
                KitchenRoutingSource::DEFAULT_STATION,
            ];
        }

        $fallback = KitchenStation::query()
            ->forLocation($locationId)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($fallback) {
            return [
                $fallback,
                KitchenRoutingSource::FALLBACK_STATION,
            ];
        }

        throw ValidationException::withMessages([
            'kitchen' =>
                'لا توجد محطة مطبخ فعالة لهذا الفرع. '
                . 'أنشئ محطة من «محطات المطبخ» قبل إرسال الطلبات للمطبخ.',
        ]);
    }

    public function createDefaultStationIfMissing(
        int $locationId,
        ?User $actor = null
    ): KitchenStation {
        $existing = KitchenStation::query()
            ->forLocation($locationId)
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return KitchenStation::query()->create([
            'location_id' => $locationId,
            'name' => 'المطبخ الرئيسي',
            'code' => 'MAIN',
            'description' => 'محطة افتراضية أنشأها النظام عند تفعيل وحدة المطبخ.',
            'target_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 10,
            'created_by' => $actor?->id,
        ]);
    }

    public function syncRoutes(
        KitchenStation $station,
        array $productIds,
        array $categoryIds
    ): void {
        DB::transaction(function () use (
            $station,
            $productIds,
            $categoryIds
        ): void {
            $productIds = array_values(array_unique(array_map('intval', $productIds)));
            $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));

            KitchenProductRoute::query()
                ->where('kitchen_station_id', $station->id)
                ->whereNotIn(
                    'product_id',
                    $productIds ?: [-1]
                )
                ->delete();

            foreach ($productIds as $productId) {
                KitchenProductRoute::query()->updateOrCreate(
                    [
                        'location_id' => $station->location_id,
                        'product_id' => $productId,
                    ],
                    [
                        'kitchen_station_id' => $station->id,
                    ]
                );
            }

            KitchenCategoryRoute::query()
                ->where('kitchen_station_id', $station->id)
                ->whereNotIn(
                    'category_id',
                    $categoryIds ?: [-1]
                )
                ->delete();

            foreach ($categoryIds as $categoryId) {
                KitchenCategoryRoute::query()->updateOrCreate(
                    [
                        'location_id' => $station->location_id,
                        'category_id' => $categoryId,
                    ],
                    [
                        'kitchen_station_id' => $station->id,
                    ]
                );
            }
        });
    }

    public function makeDefault(KitchenStation $station): void
    {
        DB::transaction(function () use ($station): void {
            KitchenStation::query()
                ->forLocation($station->location_id)
                ->whereKeyNot($station->id)
                ->update([
                    'is_default' => false,
                ]);

            $station->update([
                'is_default' => true,
                'is_active' => true,
            ]);
        });
    }
}
