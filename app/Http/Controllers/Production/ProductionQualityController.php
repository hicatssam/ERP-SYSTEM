<?php

namespace App\Http\Controllers\Production;

use App\Enums\ProductionBatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\DecideProductionQualityRequest;
use App\Models\ProductionBatch;
use App\Services\Production\ProductionContextService;
use App\Services\Production\ProductionQualityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionQualityController extends Controller
{
    public function __construct(
        private ProductionQualityService $quality,
        private ProductionContextService $context
    ) {
    }

    public function index(Request $request): View
    {
        $location =
            $this->context
                ->resolveLocation(
                    $request->user(),
                    $request->integer(
                        'location_id'
                    )
                    ?: null
                );

        $batches =
            ProductionBatch::query()
                ->where(
                    'location_id',
                    $location->id
                )
                ->where(
                    'status',
                    ProductionBatchStatus::AwaitingQuality->value
                )
                ->with([
                    'product',
                    'recipe',
                    'location',
                    'qualityCheck',
                ])
                ->oldest(
                    'submitted_for_quality_at'
                )
                ->paginate(25)
                ->withQueryString();

        return view(
            'production.quality.index',
            [
                'batches' =>
                    $batches,

                'locations' =>
                    $this->context
                        ->selectableLocations(
                            $request->user()
                        ),

                'selectedLocation' =>
                    $location,
            ]
        );
    }

    public function decide(
        DecideProductionQualityRequest $request,
        ProductionBatch $batch
    ): RedirectResponse {
        $this->context
            ->authorizeBatch(
                $request->user(),
                $batch
            );

        $data =
            $request->validated();

        $this->quality
            ->decide(
                $batch,
                (float)
                $data[
                    'accepted_quantity'
                ],
                (float)
                $data[
                    'rejected_quantity'
                ],
                $data['criteria'],
                $data['notes']
                ?? null,
                $request->user()
            );

        return redirect()
            ->route(
                'production.batches.show',
                $batch
            )
            ->with(
                'success',
                'تم حفظ قرار فحص الجودة وتحديث المخزون حسب الكمية المقبولة.'
            );
    }
}
