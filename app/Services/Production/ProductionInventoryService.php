<?php

namespace App\Services\Production;

use App\Enums\MovementReason;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchItem;
use App\Models\ProductionMaterialAllocation;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionInventoryService
{
    public function __construct(
        private InventoryService $inventory
    ) {
    }

    public function reservePlannedMaterials(
        ProductionBatch $batch,
        int $userId
    ): void {
        $batch->loadMissing('items');

        foreach (
            $batch->items
                ->sortBy([
                    ['ingredient_product_id', 'asc'],
                    ['id', 'asc'],
                ]) as $item
        ) {
            $this->inventory->reserve(
                (int) $batch->location_id,
                (int) $item->ingredient_product_id,
                (float) $item->planned_quantity,
                $userId,
                'production_batches',
                (int) $batch->id
            );
        }
    }

    public function releasePlannedReservations(
        ProductionBatch $batch,
        int $userId
    ): void {
        $batch->loadMissing('items');

        foreach (
            $batch->items
                ->sortBy([
                    ['ingredient_product_id', 'asc'],
                    ['id', 'asc'],
                ]) as $item
        ) {
            $qty =
                (float)
                $item->planned_quantity;

            if ($qty <= 0) {
                continue;
            }

            $this->inventory
                ->releaseReservation(
                    (int) $batch->location_id,
                    (int) $item->ingredient_product_id,
                    $qty,
                    $userId,
                    'production_batches',
                    (int) $batch->id
                );
        }
    }

    public function issuePlannedMaterials(
        ProductionBatch $batch,
        int $userId
    ): void {
        $batch->loadMissing([
            'items.ingredient',
        ]);

        foreach (
            $batch->items
                ->sortBy([
                    ['ingredient_product_id', 'asc'],
                    ['id', 'asc'],
                ]) as $item
        ) {
            $planned =
                (float)
                $item->planned_quantity;

            $this->inventory
                ->releaseReservation(
                    (int) $batch->location_id,
                    (int) $item->ingredient_product_id,
                    $planned,
                    $userId,
                    'production_batches',
                    (int) $batch->id
                );

            $this->consume(
                $batch,
                $item,
                $planned,
                $userId,
                'initial'
            );

            $item->update([
                'issued_quantity' =>
                    $planned,
            ]);
        }
    }

    public function reconcileActualConsumption(
        ProductionBatch $batch,
        array $actualByItemId,
        int $userId
    ): float {
        $batch->loadMissing([
            'items.allocations',
            'items.ingredient',
        ]);

        foreach ($batch->items as $item) {
            $payload =
                $actualByItemId[
                    $item->id
                ]
                ?? [];

            $actual =
                round(
                    (float) (
                        $payload[
                            'actual_consumed_quantity'
                        ]
                        ?? $item
                            ->issued_quantity
                    ),
                    3
                );

            $waste =
                round(
                    (float) (
                        $payload[
                            'waste_quantity'
                        ]
                        ?? 0
                    ),
                    3
                );

            if ($actual < 0) {
                throw ValidationException::withMessages([
                    "items.{$item->id}.actual_consumed_quantity" =>
                        'الاستهلاك الفعلي لا يمكن أن يكون سالبًا.',
                ]);
            }

            if (
                $waste < 0
                || $waste > $actual
            ) {
                throw ValidationException::withMessages([
                    "items.{$item->id}.waste_quantity" =>
                        'الهدر يجب أن يكون بين صفر وكمية الاستهلاك الفعلي.',
                ]);
            }

            $issued =
                round(
                    (float)
                    $item->issued_quantity,
                    3
                );

            if ($actual > $issued) {
                $extra =
                    round(
                        $actual
                        - $issued,
                        3
                    );

                $this->consume(
                    $batch,
                    $item,
                    $extra,
                    $userId,
                    'final-extra'
                );

                $issued =
                    $actual;
            } elseif ($actual < $issued) {
                $return =
                    round(
                        $issued
                        - $actual,
                        3
                    );

                $this->returnUnused(
                    $batch,
                    $item,
                    $return,
                    $userId
                );

                $issued =
                    $actual;
            }

            $item->update([
                'issued_quantity' =>
                    $issued,

                'actual_consumed_quantity' =>
                    $actual,

                'waste_quantity' =>
                    $waste,

                'actual_cost' =>
                    $this->actualItemCost(
                        $item->fresh(
                            'allocations'
                        )
                    ),
            ]);
        }

        return round(
            (float)
            $batch->items()
                ->sum('actual_cost'),
            4
        );
    }

    public function postFinishedOutput(
        ProductionBatch $batch,
        float $acceptedQuantity,
        int $userId
    ): ?InventoryBatch {
        if ($acceptedQuantity <= 0) {
            return null;
        }

        $batch->loadMissing('product');

        $unitCost =
            $acceptedQuantity > 0
                ? round(
                    (float)
                    $batch->actual_material_cost
                    / $acceptedQuantity,
                    4
                )
                : 0;

        $inventoryBatch =
            null;

        if (
            $batch->product->tracks_batch
            || $batch->product->tracks_expiry
        ) {
            if (
                $batch->product->tracks_expiry
                && ! $batch->output_expiry_date
            ) {
                throw ValidationException::withMessages([
                    'output_expiry_date' =>
                        'تاريخ انتهاء ناتج الإنتاج مطلوب لأن المنتج يتتبع الصلاحية.',
                ]);
            }

            $inventoryBatch =
                InventoryBatch::query()
                    ->firstOrCreate(
                        [
                            'production_batch_id' =>
                                $batch->id,
                        ],
                        [
                            'goods_receipt_item_id' =>
                                null,

                            'product_id' =>
                                $batch->product_id,

                            'location_id' =>
                                $batch->location_id,

                            'batch_number' =>
                                $batch->batch_number,

                            'manufacturing_date' =>
                                now()->toDateString(),

                            'expiry_date' =>
                                $batch->output_expiry_date,

                            'received_quantity' =>
                                $acceptedQuantity,

                            'available_quantity' =>
                                $acceptedQuantity,

                            'unit_cost' =>
                                $unitCost,

                            'base_unit_cost' =>
                                $unitCost,

                            'currency_id' =>
                                null,
                        ]
                    );
        }

        $this->inventory
            ->increase(
                locationId:
                    (int) $batch->location_id,

                productId:
                    (int) $batch->product_id,

                quantity:
                    $acceptedQuantity,

                reason:
                    MovementReason::ProductionIn,

                userId:
                    $userId,

                referenceType:
                    'production_batches',

                referenceId:
                    (int) $batch->id,

                unitCost:
                    number_format(
                        $unitCost,
                        4,
                        '.',
                        ''
                    ),

                inventoryBatchId:
                    $inventoryBatch?->id,

                idempotencyKey:
                    "production:{$batch->id}:finished-output",

                note:
                    'إدخال ناتج دفعة الإنتاج'
            );

        return $inventoryBatch;
    }

    private function consume(
        ProductionBatch $batch,
        ProductionBatchItem $item,
        float $quantity,
        int $userId,
        string $phase
    ): void {
        if ($quantity <= 0) {
            return;
        }

        $product =
            Product::query()
                ->findOrFail(
                    $item->ingredient_product_id
                );

        if (
            $product->tracks_batch
            || $product->tracks_expiry
        ) {
            $this->consumeFromTrackedBatches(
                $batch,
                $item,
                $product,
                $quantity,
                $userId,
                $phase
            );

            return;
        }

        $inventory =
            Inventory::query()
                ->where(
                    'location_id',
                    $batch->location_id
                )
                ->where(
                    'product_id',
                    $item->ingredient_product_id
                )
                ->lockForUpdate()
                ->first();

        $unitCost =
            (float) (
                $inventory?->unit_cost
                ?? 0
            );

        $this->inventory
            ->decrease(
                locationId:
                    (int) $batch->location_id,

                productId:
                    (int) $item->ingredient_product_id,

                quantity:
                    $quantity,

                reason:
                    MovementReason::ProductionConsumption,

                userId:
                    $userId,

                referenceType:
                    'production_batches',

                referenceId:
                    (int) $batch->id,

                idempotencyKey:
                    "production:{$batch->id}:item:{$item->id}:{$phase}",

                note:
                    'استهلاك مادة لدفعة الإنتاج',

                unitCost:
                    number_format(
                        $unitCost,
                        4,
                        '.',
                        ''
                    )
            );

        ProductionMaterialAllocation::query()
            ->create([
                'production_batch_item_id' =>
                    $item->id,

                'inventory_batch_id' =>
                    null,

                'quantity_issued' =>
                    $quantity,

                'quantity_returned' =>
                    0,

                'unit_cost' =>
                    $unitCost,
            ]);
    }

    private function consumeFromTrackedBatches(
        ProductionBatch $batch,
        ProductionBatchItem $item,
        Product $product,
        float $quantity,
        int $userId,
        string $phase
    ): void {
        $remaining =
            round(
                $quantity,
                3
            );

        $batches =
            InventoryBatch::query()
                ->where(
                    'location_id',
                    $batch->location_id
                )
                ->where(
                    'product_id',
                    $product->id
                )
                ->where(
                    'available_quantity',
                    '>',
                    0
                )
                ->orderByRaw(
                    'CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END'
                )
                ->orderBy(
                    'expiry_date'
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        $available =
            round(
                (float)
                $batches->sum(
                    'available_quantity'
                ),
                3
            );

        if ($available + 0.0005 < $remaining) {
            throw ValidationException::withMessages([
                'materials' =>
                    "دفعات المخزون للمادة «{$product->name_ar}» لا تغطي الكمية المطلوبة. المتاح بالدفعات: {$available}.",
            ]);
        }

        $allocationNo = 0;

        foreach ($batches as $inventoryBatch) {
            if ($remaining <= 0.0005) {
                break;
            }

            $take =
                min(
                    $remaining,
                    (float)
                    $inventoryBatch
                        ->available_quantity
                );

            $take =
                round(
                    $take,
                    3
                );

            if ($take <= 0) {
                continue;
            }

            $inventoryBatch->decrement(
                'available_quantity',
                $take
            );

            $unitCost =
                (float)
                $inventoryBatch
                    ->base_unit_cost;

            $allocationNo++;

            $this->inventory
                ->decrease(
                    locationId:
                        (int) $batch->location_id,

                    productId:
                        (int) $product->id,

                    quantity:
                        $take,

                    reason:
                        MovementReason::ProductionConsumption,

                    userId:
                        $userId,

                    referenceType:
                        'production_batches',

                    referenceId:
                        (int) $batch->id,

                    idempotencyKey:
                        "production:{$batch->id}:item:{$item->id}:{$phase}:batch:{$inventoryBatch->id}:{$allocationNo}",

                    note:
                        'استهلاك مادة من دفعة مخزون للإنتاج',

                    unitCost:
                        number_format(
                            $unitCost,
                            4,
                            '.',
                            ''
                        ),

                    inventoryBatchId:
                        (int) $inventoryBatch->id
                );

            ProductionMaterialAllocation::query()
                ->create([
                    'production_batch_item_id' =>
                        $item->id,

                    'inventory_batch_id' =>
                        $inventoryBatch->id,

                    'quantity_issued' =>
                        $take,

                    'quantity_returned' =>
                        0,

                    'unit_cost' =>
                        $unitCost,
                ]);

            $remaining =
                round(
                    $remaining
                    - $take,
                    3
                );
        }
    }

    private function returnUnused(
        ProductionBatch $batch,
        ProductionBatchItem $item,
        float $quantity,
        int $userId
    ): void {
        $remaining =
            round(
                $quantity,
                3
            );

        $allocations =
            $item->allocations()
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

        foreach ($allocations as $allocation) {
            if ($remaining <= 0.0005) {
                break;
            }

            $net =
                $allocation
                    ->netQuantity();

            if ($net <= 0) {
                continue;
            }

            $return =
                round(
                    min(
                        $remaining,
                        $net
                    ),
                    3
                );

            if ($allocation->inventory_batch_id) {
                $inventoryBatch =
                    InventoryBatch::query()
                        ->whereKey(
                            $allocation
                                ->inventory_batch_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $inventoryBatch->increment(
                    'available_quantity',
                    $return
                );
            }

            $this->inventory
                ->increase(
                    locationId:
                        (int) $batch->location_id,

                    productId:
                        (int) $item->ingredient_product_id,

                    quantity:
                        $return,

                    reason:
                        MovementReason::ProductionMaterialReturn,

                    userId:
                        $userId,

                    referenceType:
                        'production_batches',

                    referenceId:
                        (int) $batch->id,

                    unitCost:
                        number_format(
                            (float)
                            $allocation
                                ->unit_cost,
                            4,
                            '.',
                            ''
                        ),

                    inventoryBatchId:
                        $allocation
                            ->inventory_batch_id,

                    idempotencyKey:
                        "production:{$batch->id}:item:{$item->id}:return:{$allocation->id}",

                    note:
                        'إرجاع مادة غير مستخدمة من دفعة الإنتاج'
                );

            $allocation->increment(
                'quantity_returned',
                $return
            );

            $remaining =
                round(
                    $remaining
                    - $return,
                    3
                );
        }

        if ($remaining > 0.0005) {
            throw ValidationException::withMessages([
                'materials' =>
                    'تعذر مطابقة كمية الإرجاع مع المواد المصروفة لدفعة الإنتاج.',
            ]);
        }
    }

    private function actualItemCost(
        ProductionBatchItem $item
    ): float {
        return round(
            $item->allocations
                ->sum(
                    fn (
                        ProductionMaterialAllocation $allocation
                    ) =>
                        $allocation->netQuantity()
                        * (float)
                        $allocation->unit_cost
                ),
            4
        );
    }
}
