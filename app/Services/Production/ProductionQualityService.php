<?php

namespace App\Services\Production;

use App\Enums\ProductionBatchStatus;
use App\Enums\ProductionQualityStatus;
use App\Enums\QualityCheckResult;
use App\Models\ProductionBatch;
use App\Models\ProductionQualityCheck;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionQualityService
{
    public function __construct(
        private ProductionInventoryService $inventory,
        private ProductionNotificationService $notifications
    ) {
    }

    public function decide(
        ProductionBatch $batch,
        float $acceptedQuantity,
        float $rejectedQuantity,
        array $criteria,
        ?string $notes,
        User $user
    ): ProductionBatch {
        return DB::transaction(
            function () use (
                $batch,
                $acceptedQuantity,
                $rejectedQuantity,
                $criteria,
                $notes,
                $user
            ): ProductionBatch {
                $locked =
                    ProductionBatch::query()
                        ->whereKey(
                            $batch->id
                        )
                        ->lockForUpdate()
                        ->with([
                            'qualityCheck.items',
                            'product',
                        ])
                        ->firstOrFail();

                if (
                    $locked->status
                    !== ProductionBatchStatus::AwaitingQuality
                ) {
                    throw ValidationException::withMessages([
                        'quality' =>
                            'دفعة الإنتاج ليست بانتظار فحص الجودة.',
                    ]);
                }

                $actual =
                    round(
                        (float)
                        $locked->actual_output_quantity,
                        3
                    );

                $accepted =
                    round(
                        $acceptedQuantity,
                        3
                    );

                $rejected =
                    round(
                        $rejectedQuantity,
                        3
                    );

                if (
                    $accepted < 0
                    || $rejected < 0
                ) {
                    throw ValidationException::withMessages([
                        'quality' =>
                            'الكميات المقبولة والمرفوضة لا يمكن أن تكون سالبة.',
                    ]);
                }

                if (
                    abs(
                        ($accepted + $rejected)
                        - $actual
                    )
                    > 0.0005
                ) {
                    throw ValidationException::withMessages([
                        'quality' =>
                            "يجب أن يساوي المقبول + المرفوض الناتج الفعلي ({$actual}).",
                    ]);
                }

                if ($criteria === []) {
                    throw ValidationException::withMessages([
                        'criteria' =>
                            'أضف معيار فحص جودة واحدًا على الأقل.',
                    ]);
                }

                $hasFailedCriterion =
                    collect($criteria)
                        ->contains(
                            fn (array $criterion) =>
                                ($criterion['result'] ?? null)
                                === QualityCheckResult::Fail->value
                        );

                if (
                    $rejected <= 0
                    && $hasFailedCriterion
                ) {
                    throw ValidationException::withMessages([
                        'criteria' =>
                            'يوجد معيار جودة غير ناجح؛ سجّل كمية مرفوضة أو صحّح نتيجة الفحص.',
                    ]);
                }

                $status =
                    $accepted <= 0
                        ? ProductionQualityStatus::Rejected
                        : (
                            $rejected > 0
                                ? ProductionQualityStatus::Partial
                                : ProductionQualityStatus::Approved
                        );

                $check =
                    ProductionQualityCheck::query()
                        ->firstOrCreate([
                            'production_batch_id' =>
                                $locked->id,
                        ]);

                $check->update([
                    'status' =>
                        $status,

                    'accepted_quantity' =>
                        $accepted,

                    'rejected_quantity' =>
                        $rejected,

                    'notes' =>
                        $notes,

                    'checked_by' =>
                        $user->id,

                    'checked_at' =>
                        now(),
                ]);

                $check->items()
                    ->delete();

                foreach (
                    array_values(
                        $criteria
                    ) as $index => $criterion
                ) {
                    $check->items()
                        ->create([
                            'criterion' =>
                                trim(
                                    (string)
                                    $criterion['criterion']
                                ),

                            'result' =>
                                QualityCheckResult::from(
                                    $criterion['result']
                                ),

                            'notes' =>
                                $criterion['notes']
                                ?? null,

                            'sort_order' =>
                                ($index + 1)
                                * 10,
                        ]);
                }

                if ($accepted > 0) {
                    $unitCost =
                        round(
                            (float)
                            $locked->actual_material_cost
                            / $accepted,
                            4
                        );

                    $locked->update([
                        'accepted_output_quantity' =>
                            $accepted,

                        'rejected_output_quantity' =>
                            $rejected,

                        'actual_unit_cost' =>
                            $unitCost,
                    ]);

                    $this->inventory
                        ->postFinishedOutput(
                            $locked,
                            $accepted,
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

                        'rejection_reason' =>
                            $rejected > 0
                                ? $notes
                                : null,
                    ]);

                    ActivityLogger::log(
                        userId:
                            $user->id,

                        action:
                            $status === ProductionQualityStatus::Partial
                                ? 'production.quality_partial'
                                : 'production.quality_approved',

                        module:
                            'quality_control',

                        recordType:
                            'production_batches',

                        recordId:
                            $locked->id,

                        newValues:
                            [
                                'quality_status' =>
                                    $status->value,

                                'accepted_quantity' =>
                                    $accepted,

                                'rejected_quantity' =>
                                    $rejected,

                                'actual_unit_cost' =>
                                    $unitCost,
                            ],
                    );

                    DB::afterCommit(
                        fn () =>
                            $this->notifications
                                ->completed(
                                    $locked->fresh()
                                )
                    );
                } else {
                    $locked->update([
                        'status' =>
                            ProductionBatchStatus::Rejected,

                        'accepted_output_quantity' =>
                            0,

                        'rejected_output_quantity' =>
                            $rejected,

                        'actual_unit_cost' =>
                            0,

                        'rejected_at' =>
                            now(),

                        'rejection_reason' =>
                            $notes,
                    ]);

                    ActivityLogger::log(
                        userId:
                            $user->id,

                        action:
                            'production.quality_rejected',

                        module:
                            'quality_control',

                        recordType:
                            'production_batches',

                        recordId:
                            $locked->id,

                        newValues:
                            [
                                'quality_status' =>
                                    ProductionQualityStatus::Rejected
                                        ->value,

                                'rejected_quantity' =>
                                    $rejected,

                                'reason' =>
                                    $notes,
                            ],
                    );

                    DB::afterCommit(
                        fn () =>
                            $this->notifications
                                ->rejected(
                                    $locked->fresh()
                                )
                    );
                }

                return $locked
                    ->fresh([
                        'qualityCheck.items',
                        'product',
                        'location',
                        'items',
                    ]);
            }
        );
    }
}
