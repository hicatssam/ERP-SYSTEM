<?php

namespace App\Services\Production;

use App\Enums\ProductionOrderStatus;
use App\Enums\QualityInspectionStatus;
use App\Models\Inventory;
use App\Models\ProductionOrder;
use App\Models\ProductionQualityInspection;
use App\Models\Recipe;
use App\Models\User;
use App\Notifications\ProductionOrderNotification;
use App\Services\ActivityLogger;
use App\Services\Inventory\InventoryService;
use App\Services\ModuleService;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductionOrderService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly RecipeCostService $costs,
        private readonly ModuleService $modules,
    ) {
    }

    public function create(array $data, User $user): ProductionOrder
    {
        $recipe = Recipe::query()
            ->active()
            ->with([
                'product.unitDefinition',
                'items.ingredient.unitDefinition',
            ])
            ->findOrFail((int) $data['recipe_id']);

        $locationId = (int) $data['location_id'];
        $plannedOutput = round((float) $data['planned_output_quantity'], 3);

        if ($plannedOutput <= 0) {
            throw ValidationException::withMessages([
                'planned_output_quantity' => 'كمية الإنتاج المخططة يجب أن تكون أكبر من صفر.',
            ]);
        }

        if ($recipe->items->isEmpty()) {
            throw ValidationException::withMessages([
                'recipe_id' => 'الوصفة الفعالة لا تحتوي على مكونات.',
            ]);
        }

        return DB::transaction(function () use (
            $recipe,
            $locationId,
            $plannedOutput,
            $data,
            $user
        ): ProductionOrder {
            $calculation = $this->costs->calculate(
                $recipe,
                $locationId,
                $plannedOutput
            );

            $order = ProductionOrder::query()->create([
                'production_number' => null,
                'location_id' => $locationId,
                'recipe_id' => $recipe->id,
                'product_id' => $recipe->product_id,
                'status' => ProductionOrderStatus::Draft,
                'quality_required' => $this->modules->isEnabled('quality_control'),
                'planned_output_quantity' => $plannedOutput,
                'planned_material_cost' => $calculation['materials'],
                'planned_labor_cost' => $calculation['labor'],
                'planned_overhead_cost' => $calculation['overhead'],
                'planned_total_cost' => $calculation['total'],
                'planned_unit_cost' => $calculation['unit_cost'],
                'planned_at' => $data['planned_at'] ?? now(),
                'created_by' => $user->id,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ]);

            $order->update([
                'production_number' => sprintf(
                    'PRD-%s-%06d',
                    now()->format('Ymd'),
                    $order->id
                ),
            ]);

            foreach ($calculation['lines'] as $line) {
                $order->items()->create([
                    'product_id' => $line['product_id'],
                    'planned_quantity' => $line['quantity'],
                    'reserved_quantity' => 0,
                    'waste_percent' => $line['waste_percent'],
                    'planned_unit_cost' => $line['unit_cost'],
                    'planned_cost' => $line['total_cost'],
                ]);
            }

            ActivityLogger::log(
                userId: $user->id,
                action: 'production.created',
                module: 'production',
                recordType: 'production_orders',
                recordId: $order->id,
                newValues: [
                    'production_number' => $order->production_number,
                    'location_id' => $order->location_id,
                    'recipe_id' => $order->recipe_id,
                    'product_id' => $order->product_id,
                    'planned_output_quantity' => $order->planned_output_quantity,
                    'planned_material_cost' => $order->planned_material_cost,
                    'planned_total_cost' => $order->planned_total_cost,
                    'planned_unit_cost' => $order->planned_unit_cost,
                    'quality_required' => $order->quality_required,
                ],
            );

            return $this->fresh($order);
        });
    }

    public function release(ProductionOrder $order, User $user): ProductionOrder
    {
        return DB::transaction(function () use ($order, $user): ProductionOrder {
            $locked = $this->lockOrder($order);
            $this->assertStatus($locked, ProductionOrderStatus::Draft);

            /*
             * Reprice at release time so the approved plan reflects the current
             * weighted-average inventory cost. The BOM quantities themselves stay
             * frozen from the recipe snapshot taken when the order was created.
             */
            $locked->loadMissing(['items', 'recipe.items']);
            $recalculated = $this->costs->calculate(
                $locked->recipe,
                $locked->location_id,
                (float) $locked->planned_output_quantity
            );
            $lineByProduct = collect($recalculated['lines'])->keyBy('product_id');

            foreach ($locked->items as $item) {
                $line = $lineByProduct->get($item->product_id);
                $unitCost = (float) ($line['unit_cost'] ?? 0);
                $lineCost = round((float) $item->planned_quantity * $unitCost, 2);

                $item->update([
                    'planned_unit_cost' => $unitCost,
                    'planned_cost' => $lineCost,
                ]);
            }

            $locked->update([
                'status' => ProductionOrderStatus::Released,
                'planned_material_cost' => $recalculated['materials'],
                'planned_labor_cost' => $recalculated['labor'],
                'planned_overhead_cost' => $recalculated['overhead'],
                'planned_total_cost' => $recalculated['total'],
                'planned_unit_cost' => $recalculated['unit_cost'],
                'released_at' => now(),
                'released_by' => $user->id,
            ]);

            ActivityLogger::log(
                userId: $user->id,
                action: 'production.released',
                module: 'production',
                recordType: 'production_orders',
                recordId: $locked->id,
                oldValues: ['status' => ProductionOrderStatus::Draft->value],
                newValues: [
                    'status' => ProductionOrderStatus::Released->value,
                    'planned_material_cost' => $recalculated['materials'],
                    'planned_total_cost' => $recalculated['total'],
                    'planned_unit_cost' => $recalculated['unit_cost'],
                ],
            );

            $fresh = $this->fresh($locked);
            $this->notifyRoles(
                $fresh,
                ['Production Employee', 'Factory Manager'],
                'released',
                'تم اعتماد أمر إنتاج جديد وأصبح جاهزًا للبدء.'
            );

            return $fresh;
        });
    }

    public function start(ProductionOrder $order, User $user): ProductionOrder
    {
        return DB::transaction(function () use ($order, $user): ProductionOrder {
            $locked = $this->lockOrder($order);
            $this->assertStatus($locked, ProductionOrderStatus::Released);
            $locked->loadMissing('items.product');

            foreach ($locked->items->sortBy('product_id') as $item) {
                $quantity = round((float) $item->planned_quantity, 4);

                $this->inventory->reserve(
                    $locked->location_id,
                    $item->product_id,
                    $quantity,
                    $user->id,
                    'production_orders',
                    $locked->id
                );

                $item->update([
                    'reserved_quantity' => $quantity,
                ]);
            }

            $locked->update([
                'status' => ProductionOrderStatus::InProgress,
                'started_at' => now(),
                'started_by' => $user->id,
            ]);

            ActivityLogger::log(
                userId: $user->id,
                action: 'production.started',
                module: 'production',
                recordType: 'production_orders',
                recordId: $locked->id,
                oldValues: ['status' => ProductionOrderStatus::Released->value],
                newValues: ['status' => ProductionOrderStatus::InProgress->value],
                metadata: ['location_id' => $locked->location_id],
            );

            return $this->fresh($locked);
        });
    }

    public function submitCompletion(
        ProductionOrder $order,
        array $data,
        User $user
    ): ProductionOrder {
        return DB::transaction(function () use ($order, $data, $user): ProductionOrder {
            $locked = $this->lockOrder($order);
            $this->assertStatus($locked, ProductionOrderStatus::InProgress);

            if ($locked->materials_consumed_at) {
                throw ValidationException::withMessages([
                    'production' => 'تم ترحيل استهلاك المواد لهذا الأمر مسبقًا.',
                ]);
            }

            $actualOutput = round((float) ($data['actual_output_quantity'] ?? 0), 3);

            if ($actualOutput <= 0) {
                throw ValidationException::withMessages([
                    'actual_output_quantity' => 'كمية الناتج الفعلية يجب أن تكون أكبر من صفر.',
                ]);
            }

            $locked->loadMissing(['items.product', 'recipe']);
            $actualMap = collect($data['items'] ?? [])
                ->mapWithKeys(fn ($row) => [
                    (int) ($row['id'] ?? 0) => round((float) ($row['actual_quantity'] ?? 0), 4),
                ]);

            $actualMaterialCost = 0.0;

            foreach ($locked->items->sortBy('product_id') as $item) {
                $actualQuantity = $actualMap->has($item->id)
                    ? (float) $actualMap->get($item->id)
                    : (float) $item->planned_quantity;

                if ($actualQuantity < 0) {
                    throw ValidationException::withMessages([
                        'items' => 'لا يمكن أن تكون كمية الاستهلاك الفعلية سالبة.',
                    ]);
                }

                $reserved = round((float) $item->reserved_quantity, 4);

                if ($actualQuantity > $reserved + 0.00005) {
                    $extra = round($actualQuantity - $reserved, 4);
                    $this->inventory->reserve(
                        $locked->location_id,
                        $item->product_id,
                        $extra,
                        $user->id,
                        'production_orders',
                        $locked->id
                    );
                    $reserved = round($reserved + $extra, 4);
                }

                if ($actualQuantity < $reserved - 0.00005) {
                    $release = round($reserved - $actualQuantity, 4);
                    $this->inventory->releaseReservation(
                        $locked->location_id,
                        $item->product_id,
                        $release,
                        $user->id,
                        'production_orders',
                        $locked->id
                    );
                    $reserved = $actualQuantity;
                }

                $actualUnitCost = 0.0;
                $actualCost = 0.0;

                if ($actualQuantity > 0) {
                    $consumption = $this->inventory->consumeReserved(
                        $locked->location_id,
                        $item->product_id,
                        $actualQuantity,
                        $user->id,
                        'production_orders',
                        $locked->id,
                        'production:' . $locked->id . ':consume:' . $item->id,
                        'استهلاك مكون ضمن أمر الإنتاج ' . $locked->production_number
                    );

                    $actualUnitCost = round((float) $consumption['unit_cost'], 4);
                    $actualCost = round($actualQuantity * $actualUnitCost, 2);
                    $actualMaterialCost += $actualCost;
                }

                $item->update([
                    'reserved_quantity' => 0,
                    'actual_quantity' => $actualQuantity,
                    'actual_unit_cost' => $actualUnitCost,
                    'actual_cost' => $actualCost,
                    'consumed_at' => now(),
                ]);
            }

            $factor = $actualOutput / max(0.0001, (float) $locked->recipe->yield_quantity);
            $labor = round((float) $locked->recipe->labor_cost_per_batch * $factor, 2);
            $overhead = round(
                ($actualMaterialCost + $labor)
                * ((float) $locked->recipe->overhead_percent / 100),
                2
            );
            $total = round($actualMaterialCost + $labor + $overhead, 2);
            $unitCost = round($total / $actualOutput, 4);
            $nextStatus = $locked->quality_required
                ? ProductionOrderStatus::AwaitingQuality
                : ProductionOrderStatus::Completed;

            $locked->update([
                'actual_output_quantity' => $actualOutput,
                'output_variance_quantity' => round(
                    $actualOutput - (float) $locked->planned_output_quantity,
                    3
                ),
                'actual_material_cost' => round($actualMaterialCost, 2),
                'actual_labor_cost' => $labor,
                'actual_overhead_cost' => $overhead,
                'actual_total_cost' => $total,
                'actual_unit_cost' => $unitCost,
                'materials_consumed_at' => now(),
                'submitted_quality_at' => $locked->quality_required ? now() : null,
                'status' => $nextStatus,
            ]);

            if (! $locked->quality_required) {
                $this->postFinishedOutput($locked->fresh(), $user);
            }

            ActivityLogger::log(
                userId: $user->id,
                action: $locked->quality_required
                    ? 'production.submitted_quality'
                    : 'production.completed',
                module: 'production',
                recordType: 'production_orders',
                recordId: $locked->id,
                oldValues: ['status' => ProductionOrderStatus::InProgress->value],
                newValues: [
                    'status' => $nextStatus->value,
                    'actual_output_quantity' => $actualOutput,
                    'actual_material_cost' => round($actualMaterialCost, 2),
                    'actual_total_cost' => $total,
                    'actual_unit_cost' => $unitCost,
                ],
            );

            $fresh = $this->fresh($locked);

            if ($locked->quality_required) {
                $this->notifyRoles(
                    $fresh,
                    ['Quality Control'],
                    'quality_required',
                    'أمر إنتاج بانتظار فحص الجودة قبل إدخال الناتج إلى المخزون.'
                );
            } else {
                $this->notifyCompletion($fresh);
            }

            return $fresh;
        });
    }

    public function cancel(ProductionOrder $order, User $user, ?string $reason = null): ProductionOrder
    {
        return DB::transaction(function () use ($order, $user, $reason): ProductionOrder {
            $locked = $this->lockOrder($order);
            $status = $locked->statusValue();

            if (! in_array($status, [
                ProductionOrderStatus::Draft->value,
                ProductionOrderStatus::Released->value,
                ProductionOrderStatus::InProgress->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'production' => 'لا يمكن إلغاء أمر الإنتاج في حالته الحالية.',
                ]);
            }

            if ($locked->materials_consumed_at) {
                throw ValidationException::withMessages([
                    'production' => 'لا يمكن الإلغاء بعد ترحيل استهلاك المواد. استخدم إجراء جودة/تصحيح مناسب بدلًا من عكس المخزون بصمت.',
                ]);
            }

            $locked->loadMissing('items');

            foreach ($locked->items->sortBy('product_id') as $item) {
                $reserved = round((float) $item->reserved_quantity, 4);

                if ($reserved > 0) {
                    $this->inventory->releaseReservation(
                        $locked->location_id,
                        $item->product_id,
                        $reserved,
                        $user->id,
                        'production_orders',
                        $locked->id
                    );

                    $item->update(['reserved_quantity' => 0]);
                }
            }

            $locked->update([
                'status' => ProductionOrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'notes' => $reason
                    ? trim(($locked->notes ? $locked->notes . "\n" : '') . 'سبب الإلغاء: ' . $reason)
                    : $locked->notes,
            ]);

            ActivityLogger::log(
                userId: $user->id,
                action: 'production.cancelled',
                module: 'production',
                recordType: 'production_orders',
                recordId: $locked->id,
                oldValues: ['status' => $status],
                newValues: ['status' => ProductionOrderStatus::Cancelled->value],
                metadata: ['reason' => $reason],
            );

            return $this->fresh($locked);
        });
    }

    public function approveQuality(
        ProductionOrder $order,
        array $data,
        User $user
    ): ProductionOrder {
        return DB::transaction(function () use ($order, $data, $user): ProductionOrder {
            $locked = $this->lockOrder($order);
            $this->assertStatus($locked, ProductionOrderStatus::AwaitingQuality);

            if ($locked->qualityInspection()->exists()) {
                throw ValidationException::withMessages([
                    'quality' => 'تم تسجيل قرار الجودة لهذا الأمر مسبقًا.',
                ]);
            }

            ProductionQualityInspection::query()->create([
                'production_order_id' => $locked->id,
                'status' => QualityInspectionStatus::Approved,
                'measurements' => $data['measurements'] ?? null,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'rejection_reason' => null,
                'inspected_by' => $user->id,
                'inspected_at' => now(),
            ]);

            $this->postFinishedOutput($locked, $user);

            ActivityLogger::log(
                userId: $user->id,
                action: 'production.quality_approved',
                module: 'quality_control',
                recordType: 'production_orders',
                recordId: $locked->id,
                oldValues: ['status' => ProductionOrderStatus::AwaitingQuality->value],
                newValues: ['status' => ProductionOrderStatus::Completed->value],
            );

            $fresh = $this->fresh($locked);
            $this->notifyCompletion($fresh);

            return $fresh;
        });
    }

    public function rejectQuality(
        ProductionOrder $order,
        array $data,
        User $user
    ): ProductionOrder {
        return DB::transaction(function () use ($order, $data, $user): ProductionOrder {
            $locked = $this->lockOrder($order);
            $this->assertStatus($locked, ProductionOrderStatus::AwaitingQuality);

            if ($locked->qualityInspection()->exists()) {
                throw ValidationException::withMessages([
                    'quality' => 'تم تسجيل قرار الجودة لهذا الأمر مسبقًا.',
                ]);
            }

            $reason = trim((string) ($data['rejection_reason'] ?? ''));

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'rejection_reason' => 'سبب رفض الجودة مطلوب.',
                ]);
            }

            ProductionQualityInspection::query()->create([
                'production_order_id' => $locked->id,
                'status' => QualityInspectionStatus::Rejected,
                'measurements' => $data['measurements'] ?? null,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'rejection_reason' => $reason,
                'inspected_by' => $user->id,
                'inspected_at' => now(),
            ]);

            /*
             * Materials were already consumed before QC. A rejection therefore
             * does NOT silently return them to inventory and the finished output
             * is not posted. Any later recovery/rework requires an explicit stock
             * adjustment or a dedicated rework workflow.
             */
            $locked->update([
                'status' => ProductionOrderStatus::Rejected,
                'rejected_at' => now(),
            ]);

            ActivityLogger::log(
                userId: $user->id,
                action: 'production.quality_rejected',
                module: 'quality_control',
                recordType: 'production_orders',
                recordId: $locked->id,
                oldValues: ['status' => ProductionOrderStatus::AwaitingQuality->value],
                newValues: ['status' => ProductionOrderStatus::Rejected->value],
                metadata: ['rejection_reason' => $reason],
            );

            $fresh = $this->fresh($locked);
            $this->notifyRoles(
                $fresh,
                ['Factory Manager', 'Production Employee'],
                'quality_rejected',
                'تم رفض ناتج أمر الإنتاج في فحص الجودة. لم تتم إضافة الناتج إلى المخزون.',
                true
            );

            return $fresh;
        });
    }

    private function postFinishedOutput(ProductionOrder $order, User $user): void
    {
        $output = (float) $order->actual_output_quantity;
        $unitCost = (float) $order->actual_unit_cost;

        if ($output <= 0) {
            throw ValidationException::withMessages([
                'actual_output_quantity' => 'لا يمكن ترحيل ناتج إنتاج بكمية صفرية.',
            ]);
        }

        $this->inventory->increase(
            $order->location_id,
            $order->product_id,
            $output,
            'production_in',
            $user->id,
            'production_orders',
            $order->id,
            number_format($unitCost, 4, '.', ''),
            null,
            null,
            null,
            'production:' . $order->id . ':output',
            null,
            'إدخال ناتج أمر الإنتاج ' . $order->production_number
        );

        $order->update([
            'status' => ProductionOrderStatus::Completed,
            'completed_at' => now(),
            'completed_by' => $user->id,
        ]);
    }

    private function lockOrder(ProductionOrder $order): ProductionOrder
    {
        return ProductionOrder::query()
            ->whereKey($order->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertStatus(
        ProductionOrder $order,
        ProductionOrderStatus $expected
    ): void {
        if ($order->statusValue() !== $expected->value) {
            throw ValidationException::withMessages([
                'production' => 'حالة أمر الإنتاج تغيرت ولا تسمح بهذه العملية الآن.',
            ]);
        }
    }

    private function fresh(ProductionOrder $order): ProductionOrder
    {
        return $order->fresh([
            'location',
            'recipe.product.unitDefinition',
            'product.unitDefinition',
            'items.product.unitDefinition',
            'qualityInspection.inspector.employee',
            'creator.employee',
        ]);
    }

    private function notifyCompletion(ProductionOrder $order): void
    {
        $this->notifyRoles(
            $order,
            ['Factory Manager', 'Production Employee', 'Inventory Manager'],
            'completed',
            'اكتمل أمر الإنتاج وتم إدخال الناتج النهائي إلى المخزون.',
            true
        );
    }

    private function notifyRoles(
        ProductionOrder $order,
        array $roles,
        string $event,
        string $message,
        bool $includeCreator = false
    ): void {
        try {
            $permissions = match ($event) {
                'released' => ['production.view', 'production.start'],
                'quality_required' => ['quality_control.view', 'quality_control.decide'],
                'completed' => ['production.view', 'inventory.view'],
                'quality_rejected' => ['production.view', 'production.start', 'quality_control.view'],
                default => ['production.view'],
            };

            NotificationDispatcher::notifyByPermissions(
                new ProductionOrderNotification($order, $event, $message),
                $permissions,
                $order->location_id,
                ['production.view_all_locations'],
                $includeCreator ? (int) $order->created_by : null,
            );

            if ($includeCreator) {
                $creator = User::query()->find($order->created_by);
                if ($creator && $creator->is_active) {
                    NotificationDispatcher::notifyUser(
                        $creator,
                        new ProductionOrderNotification($order, $event, $message)
                    );
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
