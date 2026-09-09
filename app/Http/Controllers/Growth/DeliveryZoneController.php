<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\Location;
use App\Services\ActivityLogger;
use App\Services\Growth\CustomerGrowthAccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryZoneController extends Controller
{
    public function __construct(
        private readonly CustomerGrowthAccessService $access
    ) {}

    public function index(Request $request)
    {
        $locations = $this->access->selectableLocations(
            $request->user(),
            'delivery.view_all_locations'
        );

        $zones = DeliveryZone::query()
            ->whereIn('location_id', $locations->pluck('id'))
            ->with('location')
            ->orderBy('location_id')
            ->orderBy('name')
            ->get();

        return view(
            'growth.delivery.zones',
            compact('zones', 'locations')
        );
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $locationId = (int) $data['location_id'];

        $this->ensureBranch($locationId);
        $this->access->ensureLocationAccess(
            $locationId,
            $request->user(),
            'delivery.view_all_locations'
        );

        $data['is_active'] = $request->boolean('is_active', true);
        $zone = DeliveryZone::query()->create($data);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'delivery.zone.created',
            module: 'delivery',
            recordType: 'delivery_zones',
            recordId: $zone->id,
            oldValues: null,
            newValues: $zone->only([
                'location_id',
                'name',
                'code',
                'fee',
                'minimum_order_amount',
                'estimated_minutes',
                'is_active',
            ]),
            metadata: null,
        );

        return back()->with('success', 'تمت إضافة منطقة التوصيل.');
    }

    public function update(
        Request $request,
        DeliveryZone $zone
    ) {
        $this->access->ensureLocationAccess(
            (int) $zone->location_id,
            $request->user(),
            'delivery.view_all_locations'
        );

        $data = $this->validated($request, $zone);
        $locationId = (int) $data['location_id'];

        $this->ensureBranch($locationId);

        abort_unless(
            $locationId === (int) $zone->location_id,
            422,
            'لا يمكن نقل المنطقة لفرع آخر بعد إنشائها.'
        );

        $old = $zone->only([
            'location_id',
            'name',
            'code',
            'fee',
            'minimum_order_amount',
            'estimated_minutes',
            'is_active',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $zone->update($data);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'delivery.zone.updated',
            module: 'delivery',
            recordType: 'delivery_zones',
            recordId: $zone->id,
            oldValues: $old,
            newValues: $zone->fresh()->only(array_keys($old)),
            metadata: null,
        );

        return back()->with('success', 'تم تحديث منطقة التوصيل.');
    }

    private function validated(
        Request $request,
        ?DeliveryZone $zone = null
    ): array {
        return $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('delivery_zones', 'name')
                    ->where(
                        fn ($query) => $query->where(
                            'location_id',
                            $request->integer('location_id')
                        )
                    )
                    ->ignore($zone?->id),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'fee' => ['required', 'numeric', 'min:0'],
            'minimum_order_amount' => ['required', 'numeric', 'min:0'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function ensureBranch(int $locationId): void
    {
        abort_unless(
            Location::query()
                ->branches()
                ->active()
                ->whereKey($locationId)
                ->exists(),
            422,
            'منطقة التوصيل يجب أن تتبع فرعاً فعالاً.'
        );
    }
}
