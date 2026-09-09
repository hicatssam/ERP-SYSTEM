<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\KitchenStation;
use App\Models\Product;
use App\Services\ActivityLogger;
use App\Services\Kitchen\KitchenContextService;
use App\Services\Kitchen\KitchenRoutingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KitchenStationController extends Controller
{
    public function __construct(
        private readonly KitchenContextService $context,
        private readonly KitchenRoutingService $routing
    ) {
    }

    public function index(Request $request)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $locations = $this->context->selectableLocations(
            $request->user()
        );

        $stations = KitchenStation::query()
            ->forLocation($location->id)
            ->with([
                'productRoutes:id,location_id,product_id,kitchen_station_id',
                'categoryRoutes:id,location_id,category_id,kitchen_station_id',
            ])
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = Category::query()
            ->active()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'name_ar',
            ]);

        $products = Product::query()
            ->active()
            ->whereHas(
                'locationProducts',
                fn ($query) => $query
                    ->where('location_id', $location->id)
                    ->where('is_available', true)
            )
            ->with('category:id,name,name_ar')
            ->orderBy('name')
            ->get([
                'id',
                'category_id',
                'name',
                'name_ar',
            ]);

        return view(
            'kitchen.stations.index',
            compact(
                'location',
                'locations',
                'stations',
                'categories',
                'products',
            )
        );
    }

    public function store(Request $request)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $data = $this->validateStation(
            $request,
            $location->id
        );

        $station = KitchenStation::query()->create([
            'location_id' => $location->id,
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'target_minutes' => $data['target_minutes'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
            'is_default' => false,
            'created_by' => $request->user()->id,
        ]);

        $this->routing->syncRoutes(
            $station,
            $data['product_ids'] ?? [],
            $data['category_ids'] ?? []
        );

        if ($request->boolean('is_default')) {
            $this->routing->makeDefault($station);
        }

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'kitchen.station.created',
            module: 'kitchen',
            recordType: 'kitchen_stations',
            recordId: $station->id,
            oldValues: null,
            newValues: $station->fresh()->only([
                'location_id',
                'name',
                'code',
                'target_minutes',
                'is_default',
                'is_active',
            ]),
            metadata: [
                'product_routes' => count($data['product_ids'] ?? []),
                'category_routes' => count($data['category_ids'] ?? []),
            ],
        );

        return back()->with(
            'success',
            'تم إنشاء محطة المطبخ بنجاح.'
        );
    }

    public function update(
        Request $request,
        KitchenStation $station
    ) {
        $this->context->resolveLocation(
            $request->user(),
            (int) $station->location_id
        );

        $data = $this->validateStation(
            $request,
            (int) $station->location_id,
            $station->id
        );

        $before = $station->only([
            'name',
            'code',
            'description',
            'target_minutes',
            'sort_order',
            'is_default',
            'is_active',
        ]);

        if (
            $station->is_active
            && ! $request->boolean('is_active')
            && $station->tickets()->open()->exists()
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'is_active' =>
                    'لا يمكن تعطيل محطة عليها تذاكر مطبخ نشطة. '
                    . 'أنهِ أو ألغِ التذاكر أولاً.',
            ]);
        }

        $station->update([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'target_minutes' => $data['target_minutes'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->routing->syncRoutes(
            $station,
            $data['product_ids'] ?? [],
            $data['category_ids'] ?? []
        );

        if ($request->boolean('is_default')) {
            $this->routing->makeDefault($station);
        } elseif ($station->is_default && ! $station->is_active) {
            $station->update([
                'is_default' => false,
            ]);
        }

        ActivityLogger::logChange(
            userId: $request->user()->id,
            action: 'kitchen.station.updated',
            module: 'kitchen',
            recordType: 'kitchen_stations',
            recordId: $station->id,
            before: $before,
            after: $station->fresh()->only(array_keys($before)),
            metadata: [
                'location_id' => $station->location_id,
                'product_routes' => count($data['product_ids'] ?? []),
                'category_routes' => count($data['category_ids'] ?? []),
            ],
        );

        return back()->with(
            'success',
            'تم تحديث محطة المطبخ.'
        );
    }

    private function validateStation(
        Request $request,
        int $locationId,
        ?int $ignoreId = null
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
            ],
            'code' => [
                'required',
                'string',
                'max:60',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique(
                    'kitchen_stations',
                    'code'
                )
                    ->where(
                        fn ($query) => $query
                            ->where(
                                'location_id',
                                $locationId
                            )
                    )
                    ->ignore($ignoreId),
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'target_minutes' => [
                'required',
                'integer',
                'min:1',
                'max:240',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
            'is_default' => [
                'nullable',
                'boolean',
            ],
            'product_ids' => [
                'nullable',
                'array',
            ],
            'product_ids.*' => [
                'integer',
                'exists:products,id',
            ],
            'category_ids' => [
                'nullable',
                'array',
            ],
            'category_ids.*' => [
                'integer',
                'exists:categories,id',
            ],
        ]);
    }
}
