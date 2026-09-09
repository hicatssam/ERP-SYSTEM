<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustInventoryRequest;
use App\Models\Inventory;
use App\Models\Location;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {
    }

    public function index(Request $request): View
    {
        $user = Auth::user();

        $this->authorize('viewAny', Inventory::class);

        $locations = $user->isAdmin()
            ? Location::query()
                ->active()
                ->orderBy('name')
                ->get()
            : collect([$user->primaryLocation()])->filter();

        if ($user->isAdmin()) {
            $locationId = $request->filled('location_id')
                ? $request->integer('location_id')
                : null;
        } else {
            $locationId = $user->primaryLocation()?->id;
        }

        abort_unless(
            $user->isAdmin()
            || (
                $locationId
                && $locationId === $user->primaryLocation()?->id
            ),
            403
        );

        $location = $locationId
            ? Location::query()->findOrFail($locationId)
            : null;

        $showAllLocations =
            $user->isAdmin()
            && $location === null;

        $inventoryQuery = Inventory::query()
            ->with([
                'location',
                'product.category',
                'product.locationProducts',
            ])
            ->orderBy('quantity');

        if ($location) {
            $inventoryQuery->where(
                'location_id',
                $location->id
            );
        } elseif (! $showAllLocations) {
            $inventoryQuery->whereRaw('1 = 0');
        }

        $inventories = $inventoryQuery
            ->paginate(
                30,
                ['*'],
                'inventory_page'
            )
            ->withQueryString();

        $expirySummary = [
            'expired' => 0,
            'within_7_days' => 0,
            'within_30_days' => 0,
            'within_60_days' => 0,
        ];

        $today = Carbon::today();
        $date7 = $today->copy()->addDays(7);
        $date30 = $today->copy()->addDays(30);
        $date60 = $today->copy()->addDays(60);

        $availableExpiryQuery = DB::table(
            'inventory_batches as batches'
        )
            ->whereNotNull('batches.expiry_date')
            ->where(
                'batches.available_quantity',
                '>',
                0
            );

        if ($location) {
            $availableExpiryQuery->where(
                'batches.location_id',
                $location->id
            );
        } elseif (! $showAllLocations) {
            $availableExpiryQuery->whereRaw('1 = 0');
        }

        $expirySummary['expired'] =
            (clone $availableExpiryQuery)
                ->whereDate(
                    'batches.expiry_date',
                    '<',
                    $today->toDateString()
                )
                ->count();

        $expirySummary['within_7_days'] =
            (clone $availableExpiryQuery)
                ->whereDate(
                    'batches.expiry_date',
                    '>=',
                    $today->toDateString()
                )
                ->whereDate(
                    'batches.expiry_date',
                    '<=',
                    $date7->toDateString()
                )
                ->count();

        $expirySummary['within_30_days'] =
            (clone $availableExpiryQuery)
                ->whereDate(
                    'batches.expiry_date',
                    '>=',
                    $today->toDateString()
                )
                ->whereDate(
                    'batches.expiry_date',
                    '<=',
                    $date30->toDateString()
                )
                ->count();

        $expirySummary['within_60_days'] =
            (clone $availableExpiryQuery)
                ->whereDate(
                    'batches.expiry_date',
                    '>=',
                    $today->toDateString()
                )
                ->whereDate(
                    'batches.expiry_date',
                    '<=',
                    $date60->toDateString()
                )
                ->count();

        $expiryBatchesQuery = DB::table(
            'inventory_batches as batches'
        )
            ->leftJoin(
                'products',
                'products.id',
                '=',
                'batches.product_id'
            )
            ->leftJoin(
                'locations',
                'locations.id',
                '=',
                'batches.location_id'
            )
            ->select([
                'batches.id',
                'batches.product_id',
                'batches.location_id',
                'batches.batch_number',
                'batches.manufacturing_date',
                'batches.expiry_date',
                'batches.received_quantity',
                'batches.available_quantity',
                'products.name',
                'products.name_ar',
                'products.sku',
                'locations.name as location_name',
                'locations.code as location_code',
            ])
            ->whereNotNull('batches.expiry_date')
            ->where(
                'batches.available_quantity',
                '>',
                0
            )
            ->whereDate(
                'batches.expiry_date',
                '<=',
                $date60->toDateString()
            )
            ->orderBy('batches.expiry_date')
            ->orderBy('locations.name')
            ->orderBy('products.name_ar');

        if ($location) {
            $expiryBatchesQuery->where(
                'batches.location_id',
                $location->id
            );
        } elseif (! $showAllLocations) {
            $expiryBatchesQuery->whereRaw('1 = 0');
        }

        $expiryBatches = $expiryBatchesQuery
            ->paginate(
                20,
                ['*'],
                'expiry_page'
            )
            ->withQueryString();

        return view(
            'inventory.index',
            compact(
                'locations',
                'location',
                'showAllLocations',
                'inventories',
                'expirySummary',
                'expiryBatches'
            )
        );
    }

    public function show(Location $location): View
    {
        $user = Auth::user();

        $this->authorize(
            'viewAny',
            Inventory::class
        );

        abort_unless(
            $user->isAdmin()
            || $location->id === $user->primaryLocation()?->id,
            403
        );

        return view(
            'inventory.show',
            [
                'location' => $location,
                'inventories' =>
                    Inventory::query()
                        ->with([
                            'product.category',
                            'product.locationProducts'
                                => fn ($query) =>
                                    $query->where(
                                        'location_id',
                                        $location->id
                                    ),
                        ])
                        ->where(
                            'location_id',
                            $location->id
                        )
                        ->paginate(30),
            ]
        );
    }

    public function adjust(
        AdjustInventoryRequest $request
    ): RedirectResponse {
        $this->authorize(
            'adjust',
            Inventory::class
        );

        $validated = $request->validated();
        $user = $request->user();

        abort_unless(
            $user->isAdmin()
            || (int) $validated['location_id']
                === $user->primaryLocation()?->id,
            403
        );

        $this->inventoryService->adjust(
            locationId:
                (int) $validated['location_id'],
            productId:
                (int) $validated['product_id'],
            quantity:
                (float) $validated['quantity'],
            reason:
                $validated['reason'],
            userId:
                $user->id,
            referenceType:
                'manual_inventory_adjustments',
            referenceId:
                null,
            note:
                $validated['note'] ?? null,
        );

        return back()->with(
            'success',
            'تم تعديل المخزون وتوثيق الحركة في السجل.'
        );
    }
}