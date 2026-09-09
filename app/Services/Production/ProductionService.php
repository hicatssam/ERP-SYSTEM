<?php

namespace App\Services\Production;

use App\Enums\ProductionBatchStatus;
use App\Enums\ProductionQualityStatus;
use App\Enums\RecipeStatus;
use App\Models\ProductionBatch;
use App\Models\ProductionQualityCheck;
use App\Models\Recipe;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ModuleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionService
{
    public function __construct(
        private RecipeCostService $costs,
        private ProductionInventoryService $inventory,
        private ModuleService $modules,
        private ProductionNotificationService $notifications
    ) {
    }

    public function create(
        Recipe $recipe,
        int $locationId,
        float $plannedOutputQuantity,
        ?string $plannedDate,
        ?string $notes,
        User $user
    ): ProductionBatch {
        if (
            $recipe->status !== RecipeStatus::Approved
            || ! $recipe->is_active
        ) {
            throw ValidationException::withMessages([
                'recipe_id' =>
                    'يجب اختيار وصفة معتمدة وفعالة للإنتاج.',
            ]);
        }

        if ($plannedOutputQuantity <= 0) {
            throw ValidationException::withMessages([
                'planned_output_quantity' =>
                    'كمية الإنتاج المخططة يجب أن تكون أكبر من صفر.',
            ]);
        }

        $recipe->loadMissing([
            'product',
            'items.ingredient',
        ]);

        $estimate =
            $this->costs->estimate(
                $recipe,
                $locationId,
                $plannedOutputQuantity
            );

        return DB::transaction(
            function () use (
                $recipe,
                $locationId,
                $plannedOutputQuantity,
                $plannedDate,
                $notes,
                $user,
                $estimate
            ): ProductionBatch {
                $batch =
                    ProductionBatch::query()
                        ->create([
                            'batch_number' =>
                                null,

                            'recipe_id' =>
                                $recipe->id,

                            'product_id' =>
                                $recipe->product_id,

                            'location_id' =>
                                $locationId,

                            'recipe_version' =>
                                $recipe->version,

                            'recipe_snapshot' =>
                                [
                                    'recipe_id' =>
                                        $recipe->id,

                                    'recipe_code' =>
                                        $recipe->code,

                                    'recipe_name' =>
                                        $recipe->name,

                                    'recipe_version' =>
                                        $recipe->version,

                                    'yield_quantity' =>
                                        $recipe->yield_quantity,
                                ],

                            'status' =>
                                ProductionBatchStatus::Draft,

                            'planned_output_quantity' =>
                                $plannedOutputQuantity,

                            'standard_material_cost' =>
                                $estimate[
                                    'material_cost'
                                ],

                            'quality_required' =>
                                false,

                            'planned_date' =>
                                $plannedDate,

                            'created_by' =>
                                $user->id,

                            'notes' =>
                                $notes,
                        ]);

                $batch->update([
                    'batch_number' =>
                        sprintf(
                            'PRD-%s-%06d',
                            now()->format('Y'),
                            $batch->id
                        ),
                ]);

                $costLines =
                    collect(
                        $estimate['lines']
                    )
                        ->keyBy(
                            'recipe_item_id'
                        );

                $factor =
                    $plannedOutputQuantity
                    /
                    max(
                        0.001,
                        (float)
                        $recipe->yield_quantity
                    );

                foreach (
                    $recipe->items as $index => $recipeItem
                ) {
                    $line =
                        $costLines->get(
                            $recipeItem->id
                        );

                    $ingredientName =
                        $recipeItem
                            ->ingredient
                            ?->name_ar
                        ?: $recipeItem
                            ->ingredient
                            ?->name
                        ?: '#'
                            . $recipeItem
                                ->ingredient_product_id;

                    $batch->items()
                        ->create([
                            'recipe_item_id' =>
                                $recipeItem->id,

                            'ingredient_product_id' =>
                                $recipeItem
                                    ->ingredient_product_id,

                            'ingredient_name_snapshot' =>
                                $ingredientName,

                            'unit_snapshot' =>
                                $recipeItem
                                    ->unit_snapshot,

                            'stage_snapshot' =>
                                $recipeItem
                                    ->stage,

                            'planned_quantity' =>
                                $recipeItem
                                    ->grossQuantity(
                                        $factor
                                    ),

                            'unit_cost_snapshot' =>
                                $line[
                                    'unit_cost'
                                ]
                                ?? 0,

                            'notes' =>
                                $recipeItem
                                    ->notes,

                            'sort_order' =>
                                ($index + 1)
                                * 10,
                        ]);
                }

                ActivityLogger::log(
                    userId:
                        $user->id,

                    action:
                        'production.batch_created',

                    module:
                        'production',

                    recordType:
                        'production_batches',

                    recordId:
                        $batch->id,

                    newValues:
                        [
                            'batch_number' =>
                                $batch
                                    ->batch_number,

                            'recipe_id' =>
                                $batch
                                    ->recipe_id,

                            'location_id' =>
                                $batch
                                    ->location_id,

                            'planned_output_quantity' =>
                                $batch
                                    ->planned_output_quantity,
                        ],
                );

                return $batch
                    ->fresh([
                        'product',
                        'recipe',
                        'location',
                        'items.ingredient',
                    ]);
            }
        );
    }

    public function release(
        ProductionBatch $batch,
        User $user
    ): ProductionBatch {
        return DB::transaction(
            function () use (
                $batch,
                $user
            ): ProductionBatch {
                $locked =
                    ProductionBatch::query()
                        ->whereKey(
                            $batch->id
                        )
                        ->lockForUpdate()
                        ->with([
                            'recipe',
                            'items',
                        ])
                        ->firstOrFail();

                if (
                    $locked->status
                    !== ProductionBatchStatus::Draft
                ) {
                    throw ValidationException::withMessages([
                        'production' =>
                            'يمكن الإفراج فقط عن دفعة إنتاج مسودة.',
                    ]);
                }

                if (
                    $locked->recipe
                        ->status
                    !== RecipeStatus::Approved
                    || ! $locked
                        ->recipe
                        ->is_active
                ) {
                    throw ValidationException::withMessages([
                        'recipe' =>
                            'الوصفة لم تعد الإصدار المعتمد الفعال. أنشئ دفعة جديدة من الإصدار الحالي.',
                    ]);
                }

                $this->inventory
                    ->reservePlannedMaterials(
                        $locked,
                        $user->id
                    );

                $locked->update([
                    'status' =>
                        ProductionBatchStatus::Released,

                    'quality_required' =>
                        $this->modules
                            ->isEnabled(
                                'quality_control'
                            ),

                    'released_at' =>
                        now(),

                    'released_by' =>
                        $user->id,
                ]);

                ActivityLogger::log(
                    userId:
                        $user->id,

                    action:
                        'production.batch_released',

                    module:
                        'production',

                    recordType:
                        'production_batches',

                    recordId:
                        $locked->id,

                    oldValues:
                        [
                            'status' =>
                                ProductionBatchStatus::Draft
                                    ->value,
                        ],

                    newValues:
                        [
                            'status' =>
                                ProductionBatchStatus::Released
                                    ->value,

                            'quality_required' =>
                                $locked
                                    ->quality_required,
                        ],
                );

                DB::afterCommit(
                    fn () =>
                        $this->notifications
                            ->released(
                                $locked->fresh()
                            )
                );

                return $locked->fresh();
            }
        );
    }

    public function start(
        ProductionBatch $batch,
        User $user
    ): ProductionBatch {
        return DB::transaction(
            function () use (
                $batch,
                $user
            ): ProductionBatch {
                $locked =
                    ProductionBatch::query()
                        ->whereKey(
                            $batch->id
                        )
                        ->lockForUpdate()
                        ->with([
                            'items.ingredient',
                        ])
                        ->firstOrFail();

                if (
                    $locked->status
                    !== ProductionBatchStatus::Released
                ) {
                    throw ValidationException::withMessages([
                        'production' =>
                            'يجب الإفراج عن الدفعة قبل بدء الإنتاج.',
                    ]);
                }

                $this->inventory
                    ->issuePlannedMaterials(
                        $locked,
                        $user->id
                    );

                $locked->update([
                    'status' =>
                        ProductionBatchStatus::InProgress,

                    'started_at' =>
                        now(),

                    'materials_issued_at' =>
                        now(),

                    'started_by' =>
                        $user->id,
                ]);

                ActivityLogger::log(
                    userId:
                        $user->id,

                    action:
                        'production.batch_started',

                    module:
                        'production',

                    recordType:
                        'production_batches',

                    recordId:
                        $locked->id,

                    oldValues:
                        [
                            'status' =>
                                ProductionBatchStatus::Released
                                    ->value,
                        ],

                    newValues:
                        [
                            'status' =>
                                ProductionBatchStatus::InProgress
                                    ->value,
                        ],
                );

                return $locked->fresh();
            }
        );
    }

    public function finish(
        ProductionBatch $batch,
        float $actualOutputQuantity,
        ?string $outputExpiryDate,
        array $actualItems,
        User $user
    ): ProductionBatch {
        if ($actualOutputQuantity <= 0) {
            throw ValidationException::withMessages([
                'actual_output_quantity' =>
                    'كمية الناتج الفعلية يجب أن تكون أكبر من صفر.',
            ]);
        }

        return DB::transaction(
            function () use (
                $batch,
                $actualOutputQuantity,
                $outputExpiryDate,
                $actualItems,
                $user
            ): ProductionBatch {
                $locked =
                    ProductionBatch::query()
                        ->whereKey(
                            $batch->id
                        )
                        ->lockForUpdate()
                        ->with([
                            'product',
                            'items.allocations',
                        ])
                        ->firstOrFail();

                if (
                    $locked->status
                    !== ProductionBatchStatus::InProgress
                ) {
                    throw ValidationException::withMessages([
                        'production' =>
                            'يمكن إنهاء الكميات فقط لدفعة قيد الإنتاج.',
                    ]);
                }

                if (
                    $locked->product
                        ->tracks_expiry
                    && ! $outputExpiryDate
                ) {
                    throw ValidationException::withMessages([
                        'output_expiry_date' =>
                            'تاريخ انتهاء الناتج مطلوب لهذا المنتج.',
                    ]);
                }

                $actualMaterialCost =
                    $this->inventory
                        ->reconcileActualConsumption(
                            $locked,
                            $actualItems,
                            $user->id
                        );

                $locked->update([
                    'actual_output_quantity' =>
                        $actualOutputQuantity,

                    'actual_material_cost' =>
                        $actualMaterialCost,

                    'output_expiry_date' =>
                        $outputExpiryDate,
                ]);

                if ($locked->quality_required) {
                    ProductionQualityCheck::query()
                        ->firstOrCreate(
                            [
                                'production_batch_id' =>
                                    $locked->id,
                            ],
                            [
                                'status' =>
                                    ProductionQualityStatus::Pending,

                                'accepted_quantity' =>
                                    0,

                                'rejected_quantity' =>
                                    0,
                            ]
                        );

                    $locked->update([
                        'status' =>
                            ProductionBatchStatus::AwaitingQuality,

                        'submitted_for_quality_at' =>
                            now(),
                    ]);

                    ActivityLogger::log(
                        userId:
                            $user->id,

                        action:
                            'production.awaiting_quality',

                        module:
                            'production',

                        recordType:
                            'production_batches',

                        recordId:
                            $locked->id,

                        newValues:
                            [
                                'actual_output_quantity' =>
                                    $actualOutputQuantity,

                                'actual_material_cost' =>
                                    $actualMaterialCost,

                                'status' =>
                                    ProductionBatchStatus::AwaitingQuality
                                        ->value,
                            ],
                    );

                    DB::afterCommit(
                        fn () =>
                            $this->notifications
                                ->awaitingQuality(
                                    $locked->fresh()
                                )
                    );

                    return $locked->fresh();
                }

                $locked->update([
                    'accepted_output_quantity' =>
                        $actualOutputQuantity,

                    'rejected_output_quantity' =>
                        0,

                    'actual_unit_cost' =>
                        round(
                            $actualMaterialCost
                            / $actualOutputQuantity,
                            4
                        ),
                ]);

                $this->inventory
                    ->postFinishedOutput(
                        $locked,
                        $actualOutputQuantity,
                        $user->id
                    );

                $locked->update([
                    'status' =>
                        ProductionBatchStatus::Completed,

                    'output_posted_at' =>
                        now(),

                    'completed_at' =>
                        now(),

                    'completed_by' =>
                        $user->id,
                ]);

                ActivityLogger::log(
                    userId:
                        $user->id,

                    action:
                        'production.batch_completed',

                    module:
                        'production',

                    recordType:
                        'production_batches',

                    recordId:
                        $locked->id,

                    newValues:
                        [
                            'status' =>
                                ProductionBatchStatus::Completed
                                    ->value,

                            'accepted_output_quantity' =>
                                $actualOutputQuantity,

                            'actual_material_cost' =>
                                $actualMaterialCost,

                            'actual_unit_cost' =>
                                $locked
                                    ->actual_unit_cost,
                        ],
                );

                DB::afterCommit(
                    fn () =>
                        $this->notifications
                            ->completed(
                                $locked->fresh()
                            )
                );

                return $locked->fresh();
            }
        );
    }

    public function cancel(
        ProductionBatch $batch,
        string $reason,
        User $user
    ): ProductionBatch {
        return DB::transaction(
            function () use (
                $batch,
                $reason,
                $user
            ): ProductionBatch {
                $locked =
                    ProductionBatch::query()
                        ->whereKey(
                            $batch->id
                        )
                        ->lockForUpdate()
                        ->with('items')
                        ->firstOrFail();

                if (
                    ! in_array(
                        $locked->status,
                        [
                            ProductionBatchStatus::Draft,
                            ProductionBatchStatus::Released,
                        ],
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'production' =>
                            'لا يمكن إلغاء دفعة بعد صرف المواد. بعد البدء يجب إنهاء العملية أو معالجة الرفض عبر الجودة.',
                    ]);
                }

                if (
                    $locked->status
                    === ProductionBatchStatus::Released
                ) {
                    $this->inventory
                        ->releasePlannedReservations(
                            $locked,
                            $user->id
                        );
                }

                $oldStatus =
                    $locked->status
                        ->value;

                $locked->update([
                    'status' =>
                        ProductionBatchStatus::Cancelled,

                    'cancelled_at' =>
                        now(),

                    'cancelled_by' =>
                        $user->id,

                    'cancellation_reason' =>
                        $reason,
                ]);

                ActivityLogger::log(
                    userId:
                        $user->id,

                    action:
                        'production.batch_cancelled',

                    module:
                        'production',

                    recordType:
                        'production_batches',

                    recordId:
                        $locked->id,

                    oldValues:
                        [
                            'status' =>
                                $oldStatus,
                        ],

                    newValues:
                        [
                            'status' =>
                                ProductionBatchStatus::Cancelled
                                    ->value,

                            'reason' =>
                                $reason,
                        ],
                );

                return $locked->fresh();
            }
        );
    }
}
