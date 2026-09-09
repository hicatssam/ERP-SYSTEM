<?php

namespace App\Services\Inventory;

use App\Enums\MovementReason;
use App\Enums\MovementType;
use App\Events\LowStockDetected;
use App\Models\Inventory;
use App\Models\LocationProduct;
use App\Models\StockMovement;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only service allowed to change `inventories.quantity`.
 * Every physical quantity change creates one immutable stock movement in the
 * same database transaction and supports an idempotency key for retries.
 */
class InventoryService
{
    public function increase(
        int $locationId,
        int $productId,
        float $quantity,
        MovementReason|string $reason,
        int $userId,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $unitCost = null,
        ?int $currencyId = null,
        ?string $exchangeRate = null,
        ?int $inventoryBatchId = null,
        ?string $idempotencyKey = null,
        ?MovementType $movementType = null,
        ?string $note = null,
    ): Inventory {
        $this->assertPositiveQuantity($quantity);

        return DB::transaction(function () use (
            $locationId,
            $productId,
            $quantity,
            $reason,
            $userId,
            $referenceType,
            $referenceId,
            $unitCost,
            $currencyId,
            $exchangeRate,
            $inventoryBatchId,
            $idempotencyKey,
            $movementType,
            $note,
        ) {
            $existing = $this->idempotentInventory($idempotencyKey, $locationId, $productId);

            if ($existing) {
                return $existing;
            }

            $inventory = $this->lockInventory($locationId, $productId);
            $before = $this->quantity($inventory->quantity);
            $after = $this->quantity($before + $quantity);
            $baseUnitCost = $this->baseUnitCost($inventory, $unitCost, $exchangeRate);
            $newAverageCost = $this->weightedAverageCost($inventory, $quantity, $baseUnitCost, $after);

            $inventory->update([
                'quantity' => $after,
                'unit_cost' => $newAverageCost,
                'last_movement_at' => now(),
            ]);

            $this->recordMovement(
                locationId: $locationId,
                productId: $productId,
                movementType: $movementType ?? MovementType::In,
                reason: $reason,
                quantity: $quantity,
                balanceBefore: $before,
                balanceAfter: $after,
                userId: $userId,
                referenceType: $referenceType,
                referenceId: $referenceId,
                currencyId: $currencyId,
                exchangeRate: $exchangeRate,
                unitCost: $unitCost ?? $baseUnitCost,
                baseUnitCost: $baseUnitCost,
                inventoryBatchId: $inventoryBatchId,
                idempotencyKey: $idempotencyKey,
                note: $note,
            );

            $this->logAdjustment(
                action: 'inventory.increased',
                inventory: $inventory,
                userId: $userId,
                before: $before,
                after: $after,
                quantity: $quantity,
                reason: $reason,
                referenceType: $referenceType,
                referenceId: $referenceId,
            );

            return $inventory->fresh();
        });
    }

    public function decrease(
        int $locationId,
        int $productId,
        float $quantity,
        MovementReason|string $reason,
        int $userId,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $idempotencyKey = null,
        ?MovementType $movementType = null,
        ?string $note = null,
        ?string $unitCost = null,
        ?int $currencyId = null,
        ?string $exchangeRate = null,
        ?int $inventoryBatchId = null,
    ): Inventory {
        $this->assertPositiveQuantity($quantity);

        $inventory = DB::transaction(function () use (
            $locationId,
            $productId,
            $quantity,
            $reason,
            $userId,
            $referenceType,
            $referenceId,
            $idempotencyKey,
            $movementType,
            $note,
            $unitCost,
            $currencyId,
            $exchangeRate,
            $inventoryBatchId,
        ) {
            $existing = $this->idempotentInventory($idempotencyKey, $locationId, $productId);

            if ($existing) {
                return $existing;
            }

            $inventory = $this->lockInventory($locationId, $productId);
            $before = $this->quantity($inventory->quantity);
            $available = max(0, $before - $this->quantity($inventory->reserved_quantity));

            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => [
                        "المخزون المتاح غير كافٍ. المتاح: {$available}، المطلوب: {$quantity}.",
                    ],
                ]);
            }

            $after = $this->quantity($before - $quantity);
            $baseUnitCost = $this->baseUnitCost($inventory, $unitCost, $exchangeRate);

            $inventory->update([
                'quantity' => $after,
                'last_movement_at' => now(),
            ]);

            $this->recordMovement(
                locationId: $locationId,
                productId: $productId,
                movementType: $movementType ?? MovementType::Out,
                reason: $reason,
                quantity: $quantity,
                balanceBefore: $before,
                balanceAfter: $after,
                userId: $userId,
                referenceType: $referenceType,
                referenceId: $referenceId,
                currencyId: $currencyId,
                exchangeRate: $exchangeRate,
                unitCost: $unitCost ?? $baseUnitCost,
                baseUnitCost: $baseUnitCost,
                inventoryBatchId: $inventoryBatchId,
                idempotencyKey: $idempotencyKey,
                note: $note,
            );

            $this->logAdjustment(
                action: 'inventory.decreased',
                inventory: $inventory,
                userId: $userId,
                before: $before,
                after: $after,
                quantity: -$quantity,
                reason: $reason,
                referenceType: $referenceType,
                referenceId: $referenceId,
            );

            return $inventory->fresh();
        });

        $this->notifyLowStock($inventory);

        return $inventory;
    }

    /**
     * Signed administrative adjustment. It uses the adjustment movement type
     * while retaining the actual business reason in `reason`.
     */
    public function adjust(
        int $locationId,
        int $productId,
        float $quantity,
        MovementReason|string $reason,
        int $userId,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $idempotencyKey = null,
        ?string $note = null,
    ): Inventory {
        if ($quantity === 0.0) {
            throw ValidationException::withMessages([
                'quantity' => 'كمية التعديل لا يمكن أن تكون صفراً.',
            ]);
        }

        if ($quantity > 0) {
            return $this->increase(
                $locationId,
                $productId,
                $quantity,
                $reason,
                $userId,
                $referenceType,
                $referenceId,
                null,
                null,
                null,
                null,
                $idempotencyKey,
                MovementType::Adjustment,
                $note,
            );
        }

        return $this->decrease(
            $locationId,
            $productId,
            abs($quantity),
            $reason,
            $userId,
            $referenceType,
            $referenceId,
            $idempotencyKey,
            MovementType::Adjustment,
            $note,
        );
    }

    /**
     * Used by approved stock counts; the quantity is never changed directly
     * outside the ledger.
     */
    public function setOnHandQuantity(
        int $locationId,
        int $productId,
        float $targetQuantity,
        int $userId,
        string $referenceType,
        int $referenceId,
        ?string $idempotencyKey = null,
        ?string $note = null,
    ): Inventory {
        if ($targetQuantity < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'لا يمكن أن يكون الرصيد النهائي سالباً.',
            ]);
        }

        return DB::transaction(function () use (
            $locationId,
            $productId,
            $targetQuantity,
            $userId,
            $referenceType,
            $referenceId,
            $idempotencyKey,
            $note,
        ) {
            $inventory = $this->lockInventory($locationId, $productId);

            if ($targetQuantity < (float) $inventory->reserved_quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'لا يمكن أن يقل الرصيد النهائي عن الكمية المحجوزة.',
                ]);
            }

            $difference = $this->quantity($targetQuantity - (float) $inventory->quantity);

            if ($difference === 0.0) {
                return $inventory;
            }

            return $this->adjust(
                $locationId,
                $productId,
                $difference,
                MovementReason::StockCountAdjustment,
                $userId,
                $referenceType,
                $referenceId,
                $idempotencyKey,
                $note,
            );
        });
    }

    public function reserve(int $locationId, int $productId, float $quantity, int $userId, string $referenceType, int $referenceId): Inventory
    {
        $this->assertPositiveQuantity($quantity);

        return DB::transaction(function () use ($locationId, $productId, $quantity, $userId, $referenceType, $referenceId) {
            $inventory = $this->lockInventory($locationId, $productId);
            $available = max(0, (float) $inventory->quantity - (float) $inventory->reserved_quantity);
            $reservedBefore = (float) $inventory->reserved_quantity;

            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => "لا يمكن حجز كمية أكبر من المتاح ({$available}).",
                ]);
            }

            $inventory->increment('reserved_quantity', $quantity);

            ActivityLogger::log(
                userId: $userId,
                action: 'inventory.reserved',
                module: 'inventory',
                recordType: 'inventories',
                recordId: $inventory->id,
                oldValues: ['reserved_quantity' => $reservedBefore],
                newValues: ['reserved_quantity' => $reservedBefore + $quantity],
                metadata: compact('locationId', 'productId', 'quantity', 'referenceType', 'referenceId'),
            );

            return $inventory->fresh();
        });
    }

    public function releaseReservation(int $locationId, int $productId, float $quantity, int $userId, string $referenceType, int $referenceId): Inventory
    {
        $this->assertPositiveQuantity($quantity);

        return DB::transaction(function () use ($locationId, $productId, $quantity, $userId, $referenceType, $referenceId) {
            $inventory = $this->lockInventory($locationId, $productId);
            $reserved = (float) $inventory->reserved_quantity;

            if ($quantity > $reserved) {
                throw ValidationException::withMessages([
                    'quantity' => 'الكمية المطلوب تحريرها أكبر من الكمية المحجوزة.',
                ]);
            }

            $inventory->decrement('reserved_quantity', $quantity);

            ActivityLogger::log(
                userId: $userId,
                action: 'inventory.reservation_released',
                module: 'inventory',
                recordType: 'inventories',
                recordId: $inventory->id,
                oldValues: ['reserved_quantity' => $reserved],
                newValues: ['reserved_quantity' => $reserved - $quantity],
                metadata: compact('locationId', 'productId', 'quantity', 'referenceType', 'referenceId'),
            );

            return $inventory->fresh();
        });
    }

    /**
     * Tracks stock physically dispatched from another location but not yet
     * accepted into this location's on-hand balance. This is not a physical
     * inventory movement, so the immutable ledger entry remains at dispatch
     * and receipt while this counter is auditable through activity logs.
     */
    public function addInTransit(int $locationId, int $productId, float $quantity, int $userId, string $referenceType, int $referenceId): Inventory
    {
        $this->assertPositiveQuantity($quantity);

        return DB::transaction(function () use ($locationId, $productId, $quantity, $userId, $referenceType, $referenceId) {
            $inventory = $this->lockInventory($locationId, $productId);
            $before = (float) $inventory->in_transit_quantity;
            $inventory->increment('in_transit_quantity', $quantity);

            ActivityLogger::log(
                userId: $userId,
                action: 'inventory.in_transit_added',
                module: 'inventory',
                recordType: 'inventories',
                recordId: $inventory->id,
                oldValues: ['in_transit_quantity' => $before],
                newValues: ['in_transit_quantity' => $before + $quantity],
                metadata: compact('locationId', 'productId', 'quantity', 'referenceType', 'referenceId'),
            );

            return $inventory->fresh();
        });
    }

    public function clearInTransit(int $locationId, int $productId, float $quantity, int $userId, string $referenceType, int $referenceId): Inventory
    {
        $this->assertPositiveQuantity($quantity);

        return DB::transaction(function () use ($locationId, $productId, $quantity, $userId, $referenceType, $referenceId) {
            $inventory = $this->lockInventory($locationId, $productId);
            $before = (float) $inventory->in_transit_quantity;

            if ($quantity > $before + 0.0005) {
                throw ValidationException::withMessages([
                    'quantity' => 'لا يمكن تصفية كمية عبور أكبر من الكمية المسجلة قيد النقل.',
                ]);
            }

            $inventory->decrement('in_transit_quantity', $quantity);

            ActivityLogger::log(
                userId: $userId,
                action: 'inventory.in_transit_cleared',
                module: 'inventory',
                recordType: 'inventories',
                recordId: $inventory->id,
                oldValues: ['in_transit_quantity' => $before],
                newValues: ['in_transit_quantity' => $before - $quantity],
                metadata: compact('locationId', 'productId', 'quantity', 'referenceType', 'referenceId'),
            );

            return $inventory->fresh();
        });
    }

    /**
     * Consumes stock that was previously reserved by a production order.
     * Quantity and reserved_quantity are reduced atomically and one immutable
     * stock movement is written in the same transaction.
     *
     * @return array{inventory: Inventory, movement: StockMovement, unit_cost: float}
     */
    public function consumeReserved(
        int $locationId,
        int $productId,
        float $quantity,
        int $userId,
        string $referenceType,
        int $referenceId,
        string $idempotencyKey,
        ?string $note = null,
    ): array {
        $this->assertPositiveQuantity($quantity);

        $result = DB::transaction(function () use (
            $locationId,
            $productId,
            $quantity,
            $userId,
            $referenceType,
            $referenceId,
            $idempotencyKey,
            $note,
        ): array {
            $existingMovement = StockMovement::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingMovement) {
                $inventory = Inventory::query()
                    ->where('location_id', $locationId)
                    ->where('product_id', $productId)
                    ->firstOrFail();

                return [
                    'inventory' => $inventory,
                    'movement' => $existingMovement,
                    'unit_cost' => (float) ($existingMovement->base_unit_cost ?? $inventory->unit_cost),
                ];
            }

            $inventory = $this->lockInventory($locationId, $productId);
            $before = $this->quantity($inventory->quantity);
            $reservedBefore = $this->quantity($inventory->reserved_quantity);

            if ($quantity > $reservedBefore + 0.00005) {
                throw ValidationException::withMessages([
                    'quantity' => 'الكمية المطلوب استهلاكها أكبر من الكمية المحجوزة لأمر الإنتاج.',
                ]);
            }

            if ($quantity > $before + 0.00005) {
                throw ValidationException::withMessages([
                    'quantity' => 'الرصيد الفعلي أقل من الكمية المحجوزة ولا يمكن ترحيل استهلاك الإنتاج.',
                ]);
            }

            $after = $this->quantity($before - $quantity);
            $reservedAfter = $this->quantity($reservedBefore - $quantity);
            $baseUnitCost = $this->baseUnitCost($inventory, null, null);

            $inventory->update([
                'quantity' => $after,
                'reserved_quantity' => $reservedAfter,
                'last_movement_at' => now(),
            ]);

            $movement = $this->recordMovement(
                locationId: $locationId,
                productId: $productId,
                movementType: MovementType::Out,
                reason: MovementReason::ProductionConsumption,
                quantity: $quantity,
                balanceBefore: $before,
                balanceAfter: $after,
                userId: $userId,
                referenceType: $referenceType,
                referenceId: $referenceId,
                currencyId: null,
                exchangeRate: null,
                unitCost: $baseUnitCost,
                baseUnitCost: $baseUnitCost,
                inventoryBatchId: null,
                idempotencyKey: $idempotencyKey,
                note: $note,
            );

            ActivityLogger::log(
                userId: $userId,
                action: 'inventory.production_consumed',
                module: 'inventory',
                recordType: 'inventories',
                recordId: $inventory->id,
                oldValues: [
                    'quantity' => $before,
                    'reserved_quantity' => $reservedBefore,
                ],
                newValues: [
                    'quantity' => $after,
                    'reserved_quantity' => $reservedAfter,
                ],
                metadata: [
                    'location_id' => $locationId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                ],
            );

            return [
                'inventory' => $inventory->fresh(),
                'movement' => $movement,
                'unit_cost' => (float) $baseUnitCost,
            ];
        });

        $this->notifyLowStock($result['inventory']);

        return $result;
    }

    public function markDamaged(
        int $locationId,
        int $productId,
        float $quantity,
        int $userId,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $idempotencyKey = null,
        ?string $note = null,
    ): Inventory {
        return DB::transaction(function () use (
            $locationId,
            $productId,
            $quantity,
            $userId,
            $referenceType,
            $referenceId,
            $idempotencyKey,
            $note,
        ) {
            $inventory = $this->decrease(
                $locationId,
                $productId,
                $quantity,
                MovementReason::Damage,
                $userId,
                $referenceType,
                $referenceId,
                $idempotencyKey,
                MovementType::Out,
                $note,
            );

            $locked = Inventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();
            $locked->increment('damaged_quantity', $quantity);

            return $locked->fresh();
        });
    }

    private function lockInventory(int $locationId, int $productId): Inventory
    {
        Inventory::query()->firstOrCreate(
            ['location_id' => $locationId, 'product_id' => $productId],
            [
                'quantity' => 0,
                'reserved_quantity' => 0,
                'damaged_quantity' => 0,
                'in_transit_quantity' => 0,
                'unit_cost' => 0,
            ],
        );

        return Inventory::query()
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function idempotentInventory(?string $idempotencyKey, int $locationId, int $productId): ?Inventory
    {
        if (! $idempotencyKey) {
            return null;
        }

        $exists = StockMovement::query()->where('idempotency_key', $idempotencyKey)->exists();

        if (! $exists) {
            return null;
        }

        return Inventory::query()
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->firstOrFail();
    }

    private function recordMovement(
        int $locationId,
        int $productId,
        MovementType $movementType,
        MovementReason|string $reason,
        float $quantity,
        float $balanceBefore,
        float $balanceAfter,
        int $userId,
        ?string $referenceType,
        ?int $referenceId,
        ?int $currencyId,
        ?string $exchangeRate,
        string $unitCost,
        string $baseUnitCost,
        ?int $inventoryBatchId,
        ?string $idempotencyKey,
        ?string $note,
    ): StockMovement {
        $reasonValue = $reason instanceof MovementReason ? $reason->value : $reason;
        $reasonEnum = MovementReason::tryFrom($reasonValue);

        if (! $reasonEnum) {
            throw ValidationException::withMessages([
                'reason' => "سبب حركة المخزون غير صالح: {$reasonValue}",
            ]);
        }

        return StockMovement::query()->create([
            'location_id' => $locationId,
            'product_id' => $productId,
            'movement_type' => $movementType,
            'reason' => $reasonEnum,
            'quantity' => $this->decimal($quantity, 3),
            'balance_before' => $this->decimal($balanceBefore, 3),
            'balance_after' => $this->decimal($balanceAfter, 3),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $userId,
            'currency_id' => $currencyId,
            'exchange_rate' => $exchangeRate ? $this->decimal($exchangeRate, 8) : null,
            'unit_cost' => $this->decimal($unitCost, 4),
            'base_unit_cost' => $this->decimal($baseUnitCost, 4),
            'total_cost' => $this->decimal($quantity * (float) $unitCost, 2),
            'base_total_cost' => $this->decimal($quantity * (float) $baseUnitCost, 2),
            'inventory_batch_id' => $inventoryBatchId,
            'idempotency_key' => $idempotencyKey,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    private function weightedAverageCost(Inventory $inventory, float $increaseQuantity, string $baseUnitCost, float $afterQuantity): string
    {
        if ($afterQuantity <= 0) {
            return '0.0000';
        }

        $existingValue = (float) $inventory->quantity * (float) $inventory->unit_cost;
        $incomingValue = $increaseQuantity * (float) $baseUnitCost;

        return $this->decimal(($existingValue + $incomingValue) / $afterQuantity, 4);
    }

    private function baseUnitCost(Inventory $inventory, ?string $unitCost, ?string $exchangeRate): string
    {
        if ($unitCost === null) {
            return $this->decimal($inventory->unit_cost, 4);
        }

        return $this->decimal((float) $unitCost * (float) ($exchangeRate ?? 1), 4);
    }

    private function logAdjustment(
        string $action,
        Inventory $inventory,
        int $userId,
        float $before,
        float $after,
        float $quantity,
        MovementReason|string $reason,
        ?string $referenceType,
        ?int $referenceId,
    ): void {
        ActivityLogger::log(
            userId: $userId,
            action: $action,
            module: 'inventory',
            recordType: 'inventories',
            recordId: $inventory->id,
            oldValues: ['quantity' => $before],
            newValues: ['quantity' => $after],
            metadata: [
                'location_id' => $inventory->location_id,
                'product_id' => $inventory->product_id,
                'delta' => $quantity,
                'reason' => $reason instanceof MovementReason ? $reason->value : $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ],
        );
    }

    private function notifyLowStock(Inventory $inventory): void
    {
        $locationProduct = LocationProduct::query()
            ->where('location_id', $inventory->location_id)
            ->where('product_id', $inventory->product_id)
            ->first();

        if (! $locationProduct || (float) $inventory->available_quantity > (float) $locationProduct->minimum_stock_level) {
            return;
        }

        LowStockDetected::dispatch(
            $inventory,
            (float) $inventory->available_quantity,
            (float) $locationProduct->minimum_stock_level,
        );
    }

    private function assertPositiveQuantity(float $quantity): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'الكمية يجب أن تكون أكبر من صفر.',
            ]);
        }
    }

    private function quantity(float|string|int|null $value): float
    {
        return round((float) ($value ?? 0), 3);
    }

    private function decimal(float|string|int|null $value, int $scale): string
    {
        return number_format(round((float) ($value ?? 0), $scale), $scale, '.', '');
    }
}
