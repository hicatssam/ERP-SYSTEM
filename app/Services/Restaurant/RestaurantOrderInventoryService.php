<?php

namespace App\Services\Restaurant;

use App\Enums\MovementReason;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderInventoryConsumption;
use App\Models\Product;
use App\Models\RestaurantMenuItem;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RestaurantOrderInventoryService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {
    }

    /**
     * Freeze the physical stock plan for a new confirmation, validate all
     * required stock first, then deduct through InventoryService only.
     *
     * Normal/non-restaurant orders remain direct finished-product sales.
     * Restaurant orders can use menu inventory_mode:
     * - product: deduct sold Product itself
     * - recipe: deduct active recipe ingredients
     * - auto: recipe when available, otherwise Product itself
     */
    public function consumeForConfirmation(Order $order, User $user): void
    {
        $order->loadMissing([
            'items.product',
            'items.productVariant',
            'items.modifiers.modifier.ingredientAdjustments',
        ]);

        if (
            OrderInventoryConsumption::query()
                ->where('order_id', $order->id)
                ->exists()
        ) {
            return;
        }

        $plan = $this->buildPlan($order);

        $this->assertAvailable(
            (int) $order->location_id,
            collect($plan)
        );

        foreach ($plan as $row) {
            $inventory = Inventory::query()
                ->where('location_id', $order->location_id)
                ->where('product_id', $row['stock_product_id'])
                ->first();

            $unitCost = (float) ($inventory?->unit_cost ?? 0);

            $consumption = OrderInventoryConsumption::query()->create([
                'order_id' => $order->id,
                'order_item_id' => $row['order_item_id'],
                'location_id' => $order->location_id,
                'sold_product_id' => $row['sold_product_id'],
                'stock_product_id' => $row['stock_product_id'],
                'recipe_id' => $row['recipe_id'],
                'recipe_item_id' => $row['recipe_item_id'],
                'source' => $row['source'],
                'inventory_mode' => $row['inventory_mode'],
                'order_quantity' => $row['order_quantity'],
                'recipe_yield_quantity' => $row['recipe_yield_quantity'],
                'quantity_per_yield' => $row['quantity_per_yield'],
                'waste_percent' => $row['waste_percent'],
                'consumed_quantity' => $row['consumed_quantity'],
                'unit_cost_snapshot' => $unitCost,
                'total_cost_snapshot' => round(
                    $unitCost * $row['consumed_quantity'],
                    4
                ),
                'revision' => 0,
            ]);

            $this->inventoryService->decrease(
                locationId: (int) $order->location_id,
                productId: (int) $row['stock_product_id'],
                quantity: (float) $row['consumed_quantity'],
                reason: MovementReason::OrderSale,
                userId: (int) $user->id,
                referenceType: 'orders',
                referenceId: (int) $order->id,
                idempotencyKey:
                    "order:{$order->id}:consumption:{$consumption->id}:confirm",
                note: $this->movementNote($consumption, 'sale'),
                unitCost: number_format($unitCost, 4, '.', ''),
            );
        }
    }

    /**
     * Keep the frozen confirmation recipe/product basis when a confirmed order
     * quantity is edited. We never re-read the current active recipe here.
     *
     * @param array<int, float|int|string> $newQuantityByOrderItemId
     */
    public function syncConfirmedQuantities(
        Order $order,
        array $newQuantityByOrderItemId,
        User $user
    ): void {
        if ($newQuantityByOrderItemId === []) {
            return;
        }

        $consumptions = OrderInventoryConsumption::query()
            ->where('order_id', $order->id)
            ->whereNull('restored_at')
            ->orderBy('id')
            ->get();

        // Backward compatibility:
        // orders confirmed before installing this feature were deducted directly
        // from sold Product stock. Keep that exact historical behavior.
        if ($consumptions->isEmpty()) {
            $this->syncLegacyDirectQuantities(
                $order,
                $newQuantityByOrderItemId,
                $user
            );

            return;
        }

        $rows = [];

        foreach ($consumptions as $consumption) {
            $orderItemId = (int) $consumption->order_item_id;

            if (! array_key_exists($orderItemId, $newQuantityByOrderItemId)) {
                continue;
            }

            $oldOrderQty = (float) $consumption->order_quantity;
            $newOrderQty = round(
                (float) $newQuantityByOrderItemId[$orderItemId],
                3
            );

            if ($newOrderQty <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'كمية صنف الطلب يجب أن تكون أكبر من صفر.',
                ]);
            }

            if ($oldOrderQty <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'تعذر احتساب استهلاك المخزون لسطر طلب قديم.',
                ]);
            }

            $oldConsumed = (float) $consumption->consumed_quantity;
            $newConsumed = $this->stockQuantity(
                ($oldConsumed / $oldOrderQty) * $newOrderQty
            );

            $delta = $this->stockQuantity(
                $newConsumed - $oldConsumed
            );

            $rows[] = [
                'model' => $consumption,
                'new_order_quantity' => $newOrderQty,
                'new_consumed_quantity' => $newConsumed,
                'delta' => $delta,
            ];
        }

        $positive = collect($rows)
            ->filter(fn (array $row) => $row['delta'] > 0)
            ->map(fn (array $row) => [
                'stock_product_id' =>
                    (int) $row['model']->stock_product_id,
                'consumed_quantity' =>
                    (float) $row['delta'],
            ]);

        $this->assertAvailable(
            (int) $order->location_id,
            $positive
        );

        foreach ($rows as $row) {
            /** @var OrderInventoryConsumption $consumption */
            $consumption = $row['model'];
            $delta = (float) $row['delta'];

            if ($delta == 0.0) {
                if (
                    (float) $consumption->order_quantity
                    !== (float) $row['new_order_quantity']
                ) {
                    $consumption->update([
                        'order_quantity' => $row['new_order_quantity'],
                    ]);
                }

                continue;
            }

            $nextRevision = ((int) $consumption->revision) + 1;
            $direction = $delta > 0 ? 'out' : 'in';

            if ($delta > 0) {
                $this->inventoryService->decrease(
                    locationId: (int) $order->location_id,
                    productId: (int) $consumption->stock_product_id,
                    quantity: $delta,
                    reason: MovementReason::OrderSale,
                    userId: (int) $user->id,
                    referenceType: 'orders',
                    referenceId: (int) $order->id,
                    idempotencyKey:
                        "order:{$order->id}:consumption:{$consumption->id}:revision:{$nextRevision}:{$direction}",
                    note: $this->movementNote($consumption, 'edit+'),
                );
            } else {
                $this->inventoryService->increase(
                    locationId: (int) $order->location_id,
                    productId: (int) $consumption->stock_product_id,
                    quantity: abs($delta),
                    reason: MovementReason::OrderCancellation,
                    userId: (int) $user->id,
                    referenceType: 'orders',
                    referenceId: (int) $order->id,
                    unitCost: $consumption->unit_cost_snapshot !== null
                        ? (string) $consumption->unit_cost_snapshot
                        : null,
                    idempotencyKey:
                        "order:{$order->id}:consumption:{$consumption->id}:revision:{$nextRevision}:{$direction}",
                    note: $this->movementNote($consumption, 'edit-'),
                );
            }

            $unitCost = (float) ($consumption->unit_cost_snapshot ?? 0);

            $consumption->update([
                'order_quantity' => $row['new_order_quantity'],
                'consumed_quantity' =>
                    $row['new_consumed_quantity'],
                'total_cost_snapshot' => round(
                    $unitCost * $row['new_consumed_quantity'],
                    4
                ),
                'revision' => $nextRevision,
            ]);
        }
    }

    /**
     * Reverse exactly what was frozen and consumed at confirmation.
     * If an old order has no snapshots, restore the direct sold Products because
     * that is how historical OrderService deducted them.
     */
    public function restoreForCancellation(Order $order, User $user): void
    {
        $consumptions = OrderInventoryConsumption::query()
            ->where('order_id', $order->id)
            ->whereNull('restored_at')
            ->orderBy('id')
            ->get();

        if ($consumptions->isEmpty()) {
            $order->loadMissing('items');

            foreach ($order->items as $item) {
                $this->inventoryService->increase(
                    locationId: (int) $order->location_id,
                    productId: (int) $item->product_id,
                    quantity: (float) $item->quantity,
                    reason: MovementReason::OrderCancellation,
                    userId: (int) $user->id,
                    referenceType: 'orders',
                    referenceId: (int) $order->id,
                    idempotencyKey:
                        "order:{$order->id}:legacy-item:{$item->id}:cancel",
                    note: 'إلغاء طلب قديم قبل تفعيل سجل استهلاك الوصفات.',
                );
            }

            return;
        }

        foreach ($consumptions as $consumption) {
            $this->inventoryService->increase(
                locationId: (int) $order->location_id,
                productId: (int) $consumption->stock_product_id,
                quantity: (float) $consumption->consumed_quantity,
                reason: MovementReason::OrderCancellation,
                userId: (int) $user->id,
                referenceType: 'orders',
                referenceId: (int) $order->id,
                unitCost: $consumption->unit_cost_snapshot !== null
                    ? (string) $consumption->unit_cost_snapshot
                    : null,
                idempotencyKey:
                    "order:{$order->id}:consumption:{$consumption->id}:cancel",
                note: $this->movementNote($consumption, 'cancel'),
            );

            $consumption->update([
                'restored_at' => now(),
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPlan(Order $order): array
    {
        $plan = [];
        $isRestaurant = $order->isRestaurantOrder();

        $order->loadMissing([
            'items.product',
            'items.productVariant',
            'items.modifiers.modifier.ingredientAdjustments',
        ]);

        foreach ($order->items as $orderItem) {
            $product = $orderItem->product;

            if (! $product) {
                throw ValidationException::withMessages([
                    'items' =>
                        "المنتج المرتبط بسطر الطلب #{$orderItem->id} غير موجود.",
                ]);
            }

            $orderQty = $this->stockQuantity((float) $orderItem->quantity);

            if ($orderQty <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'كل كميات الطلب يجب أن تكون أكبر من صفر.',
                ]);
            }

            $menuItem = null;
            $inventoryMode = 'product';

            if ($isRestaurant) {
                $menuItem = RestaurantMenuItem::query()
                    ->where('location_id', $order->location_id)
                    ->where('product_id', $product->id)
                    ->first();

                $inventoryMode = $menuItem?->inventory_mode ?: 'auto';
            }

            $recipe = null;

            if ($isRestaurant && $inventoryMode !== 'product') {
                $recipe = $this->resolveActiveRecipe(
                    $product->id,
                    $orderItem->product_variant_id
                );
            }

            $useRecipe = $isRestaurant
                && $inventoryMode !== 'product'
                && $recipe
                && $recipe->items->isNotEmpty();

            if (
                $isRestaurant
                && $inventoryMode === 'recipe'
                && ! $useRecipe
            ) {
                throw ValidationException::withMessages([
                    'stock' => [
                        'صنف المنيو "'
                        . ($menuItem?->displayName()
                            ?: $product->name_ar
                            ?: $product->name)
                        . '" مضبوط على خصم الوصفة، لكن لا توجد وصفة فعالة تحتوي مكونات للحجم المحدد.',
                    ],
                ]);
            }

            /** @var array<int, array<string, mixed>> $itemPlan */
            $itemPlan = [];

            if (! $useRecipe) {
                $itemPlan[(int) $product->id] = [
                    'order_item_id' => (int) $orderItem->id,
                    'sold_product_id' => (int) $product->id,
                    'stock_product_id' => (int) $product->id,
                    'recipe_id' => null,
                    'recipe_item_id' => null,
                    'source' => 'product',
                    'inventory_mode' => $inventoryMode,
                    'order_quantity' => $orderQty,
                    'recipe_yield_quantity' => null,
                    'quantity_per_yield' => null,
                    'waste_percent' => 0,
                    'consumed_quantity' => $orderQty,
                ];
            } else {
                $yield = (float) $recipe->yield_quantity;

                if ($yield <= 0) {
                    throw ValidationException::withMessages([
                        'stock' => [
                            'الوصفة الفعالة للمنتج "'
                            . ($product->name_ar ?: $product->name)
                            . '" لديها كمية إنتاج Yield غير صالحة.',
                        ],
                    ]);
                }

                $batchFactor = $orderQty / $yield;

                foreach ($recipe->items as $recipeItem) {
                    $consumed = $this->stockQuantity(
                        $recipeItem->effectiveQuantity($batchFactor)
                    );

                    if ($consumed <= 0) {
                        continue;
                    }

                    $stockProductId = (int) $recipeItem->ingredient_product_id;

                    $itemPlan[$stockProductId] = [
                        'order_item_id' => (int) $orderItem->id,
                        'sold_product_id' => (int) $product->id,
                        'stock_product_id' => $stockProductId,
                        'recipe_id' => (int) $recipe->id,
                        'recipe_item_id' => (int) $recipeItem->id,
                        'source' => 'recipe',
                        'inventory_mode' => $inventoryMode,
                        'order_quantity' => $orderQty,
                        'recipe_yield_quantity' => $yield,
                        'quantity_per_yield' => (float) $recipeItem->quantity,
                        'waste_percent' => (float) $recipeItem->waste_percent,
                        'consumed_quantity' => $consumed,
                    ];
                }
            }

            /*
             * Apply structured cafe modifiers to the frozen physical stock plan.
             * Negative deltas reduce an ingredient already present in the base
             * recipe; positive deltas consume additional/new ingredients.
             */
            if ($isRestaurant) {
                foreach ($orderItem->modifiers as $selectedModifier) {
                    $modifier = $selectedModifier->modifier;

                    if (! $modifier) {
                        continue;
                    }

                    $modifierQty = max(1, (int) $selectedModifier->quantity);

                    foreach ($modifier->ingredientAdjustments as $adjustment) {
                        $stockProductId = (int) $adjustment->ingredient_product_id;
                        $delta = $this->stockQuantity(
                            $adjustment->stockQuantityDelta()
                            * $orderQty
                            * $modifierQty
                        );

                        if ($delta == 0.0) {
                            continue;
                        }

                        if (! isset($itemPlan[$stockProductId])) {
                            if ($delta < 0) {
                                throw ValidationException::withMessages([
                                    'items' =>
                                        'إعداد الإضافة "'
                                        . $selectedModifier->modifier_name_snapshot
                                        . '" يحاول تقليل مكوّن غير موجود في الوصفة الأساسية.',
                                ]);
                            }

                            $itemPlan[$stockProductId] = [
                                'order_item_id' => (int) $orderItem->id,
                                'sold_product_id' => (int) $product->id,
                                'stock_product_id' => $stockProductId,
                                'recipe_id' => $recipe?->id,
                                'recipe_item_id' => null,
                                'source' => 'modifier',
                                'inventory_mode' => $inventoryMode,
                                'order_quantity' => $orderQty,
                                'recipe_yield_quantity' => $recipe?->yield_quantity,
                                'quantity_per_yield' => null,
                                'waste_percent' => 0,
                                'consumed_quantity' => $delta,
                            ];

                            continue;
                        }

                        $next = $this->stockQuantity(
                            (float) $itemPlan[$stockProductId]['consumed_quantity'] + $delta
                        );

                        if ($next < 0) {
                            throw ValidationException::withMessages([
                                'items' =>
                                    'إعداد الإضافة "'
                                    . $selectedModifier->modifier_name_snapshot
                                    . '" يخفض استهلاك أحد المكونات إلى قيمة سالبة.',
                            ]);
                        }

                        $itemPlan[$stockProductId]['consumed_quantity'] = $next;
                        $itemPlan[$stockProductId]['source'] =
                            $itemPlan[$stockProductId]['source'] === 'recipe'
                                ? 'recipe_modifier'
                                : $itemPlan[$stockProductId]['source'];
                    }
                }
            }

            $itemRows = collect($itemPlan)
                ->filter(
                    fn (array $row): bool =>
                        (float) $row['consumed_quantity'] > 0
                )
                ->values();

            if ($itemRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'stock' => [
                        'سطر الطلب "'
                        . ($orderItem->product_name ?: $product->name_ar ?: $product->name)
                        . '" لا ينتج أي استهلاك مخزني صالح.',
                    ],
                ]);
            }

            foreach ($itemRows as $row) {
                $plan[] = $row;
            }
        }

        return $plan;
    }

    private function resolveActiveRecipe(
        int $productId,
        ?int $variantId
    ): ?Recipe {
        $base = Recipe::query()
            ->where('product_id', $productId)
            ->approved()
            ->active()
            ->with('items.ingredient');

        if ($variantId) {
            $variantRecipe = (clone $base)
                ->where('product_variant_id', $variantId)
                ->orderByDesc('version')
                ->first();

            if ($variantRecipe) {
                return $variantRecipe;
            }
        }

        return (clone $base)
            ->whereNull('product_variant_id')
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     */
    private function assertAvailable(
        int $locationId,
        Collection $rows
    ): void {
        $requiredByProduct = $rows
            ->groupBy('stock_product_id')
            ->map(
                fn (Collection $group) =>
                    $this->stockQuantity(
                        $group->sum('consumed_quantity')
                    )
            );

        $stockErrors = [];

        foreach ($requiredByProduct->sortKeys() as $productId => $required) {
            Inventory::query()->firstOrCreate(
                [
                    'location_id' => $locationId,
                    'product_id' => (int) $productId,
                ],
                [
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'damaged_quantity' => 0,
                    'in_transit_quantity' => 0,
                    'unit_cost' => 0,
                ]
            );

            $inventory = Inventory::query()
                ->where('location_id', $locationId)
                ->where('product_id', (int) $productId)
                ->lockForUpdate()
                ->firstOrFail();

            $available = max(
                0,
                round(
                    (float) $inventory->quantity
                    - (float) $inventory->reserved_quantity,
                    3
                )
            );

            if ($available + 0.0005 >= $required) {
                continue;
            }

            $stockProduct = Product::query()
                ->find((int) $productId);

            $productName =
                $stockProduct?->name_ar
                ?? $stockProduct?->name
                ?? "Product #{$productId}";

            $stockErrors[] =
                'المخزون غير كافٍ للمكوّن/المنتج "'
                . $productName
                . '". المطلوب: '
                . number_format($required, 3)
                . '، المتاح: '
                . number_format($available, 3)
                . '، العجز: '
                . number_format(max(0, $required - $available), 3)
                . '.';
        }

        if ($stockErrors !== []) {
            throw ValidationException::withMessages([
                'items' => $stockErrors,
            ]);
        }
    }

    /**
     * Historical fallback for confirmed orders that pre-date consumption
     * snapshots. Their original stock deduction was direct Product quantity.
     *
     * @param array<int, float|int|string> $newQuantityByOrderItemId
     */
    private function syncLegacyDirectQuantities(
        Order $order,
        array $newQuantityByOrderItemId,
        User $user
    ): void {
        $order->loadMissing('items.product');

        $positiveRows = [];
        $deltas = [];

        foreach ($order->items as $item) {
            if (! array_key_exists((int) $item->id, $newQuantityByOrderItemId)) {
                continue;
            }

            $newQty = $this->stockQuantity(
                (float) $newQuantityByOrderItemId[(int) $item->id]
            );

            if ($newQty <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'كمية صنف الطلب يجب أن تكون أكبر من صفر.',
                ]);
            }

            $delta = $this->stockQuantity(
                $newQty - (float) $item->quantity
            );

            $deltas[] = [
                'item' => $item,
                'delta' => $delta,
                'new_quantity' => $newQty,
            ];

            if ($delta > 0) {
                $positiveRows[] = [
                    'stock_product_id' => (int) $item->product_id,
                    'consumed_quantity' => $delta,
                ];
            }
        }

        $this->assertAvailable(
            (int) $order->location_id,
            collect($positiveRows)
        );

        foreach ($deltas as $row) {
            $delta = (float) $row['delta'];

            if ($delta > 0) {
                $this->inventoryService->decrease(
                    locationId: (int) $order->location_id,
                    productId: (int) $row['item']->product_id,
                    quantity: $delta,
                    reason: MovementReason::OrderSale,
                    userId: (int) $user->id,
                    referenceType: 'orders',
                    referenceId: (int) $order->id,
                    note: 'تعديل كمية طلب مؤكد قديم.',
                );
            } elseif ($delta < 0) {
                $this->inventoryService->increase(
                    locationId: (int) $order->location_id,
                    productId: (int) $row['item']->product_id,
                    quantity: abs($delta),
                    reason: MovementReason::OrderCancellation,
                    userId: (int) $user->id,
                    referenceType: 'orders',
                    referenceId: (int) $order->id,
                    note: 'تخفيض كمية طلب مؤكد قديم.',
                );
            }
        }
    }

    private function movementNote(
        OrderInventoryConsumption $consumption,
        string $action
    ): string {
        $source = $consumption->source === 'recipe'
            ? 'مكوّن وصفة'
            : 'منتج مباشر';

        return "طلب مطعم — {$source} — {$action}"
            . " — sold_product_id={$consumption->sold_product_id}"
            . " — order_item_id={$consumption->order_item_id}";
    }

    private function stockQuantity(float|int|string|null $value): float
    {
        return round((float) ($value ?? 0), 3);
    }
}
