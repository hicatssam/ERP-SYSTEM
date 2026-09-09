<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\RestaurantArea;
use App\Models\RestaurantTable;
use App\Services\ActivityLogger;
use App\Services\Restaurant\RestaurantContextService;
use App\Services\Restaurant\RestaurantTableService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;

class RestaurantTableController extends Controller
{
    public function __construct(
        private readonly RestaurantContextService $context,
        private readonly RestaurantTableService $tables
    ) {
    }

    public function index(Request $request)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $locations = $this->context->selectableLocations($request->user());

        $areas = RestaurantArea::query()
            ->forLocation($location->id)
            ->with([
                'tables' => fn ($query) => $query
                    ->with([
                        'activeSession.openedBy.employee',
                        'activeSession.orders',
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->orderBy('code'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $unassignedTables = RestaurantTable::query()
            ->forLocation($location->id)
            ->whereNull('area_id')
            ->with([
                'activeSession.openedBy.employee',
                'activeSession.orders',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('code')
            ->get();

        return view('restaurant.tables.index', compact(
            'location',
            'locations',
            'areas',
            'unassignedTables',
        ));
    }

    public function statusFeed(Request $request): JsonResponse
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $tables = RestaurantTable::query()
            ->forLocation($location->id)
            ->with(['activeSession.orders'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (RestaurantTable $table): array {
                $session = $table->activeSession;
                $openOrders = $session
                    ? $session->orders->filter(fn ($order): bool => ! in_array(
                        $order->status instanceof \BackedEnum ? $order->status->value : (string) $order->status,
                        ['completed', 'cancelled'],
                        true
                    ))->count()
                    : 0;

                return [
                    'id' => $table->id,
                    'active' => (bool) $table->is_active,
                    'occupied' => $session !== null,
                    'guest_count' => $session?->guest_count ?? 0,
                    'open_orders' => $openOrders,
                    'opened_at' => $session?->opened_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'location_id' => $location->id,
            'generated_at' => now()->toIso8601String(),
            'tables' => $tables,
        ]);
    }

    public function storeArea(Request $request)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('restaurant_areas', 'name')
                    ->where('location_id', $location->id)
                    ->whereNull('deleted_at'),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $area = RestaurantArea::query()->create([
            ...$data,
            'location_id' => $location->id,
            'is_active' => true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'restaurant.area.created',
            module: 'restaurant',
            recordType: 'restaurant_areas',
            recordId: $area->id,
            newValues: $area->only([
                'location_id', 'name', 'code', 'sort_order', 'is_active',
            ])
        );

        return back()->with('success', 'تم إنشاء منطقة المطعم.');
    }

    public function storeTable(Request $request)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $data = $request->validate([
            'area_id' => [
                'nullable',
                'integer',
                Rule::exists('restaurant_areas', 'id')
                    ->where('location_id', $location->id)
                    ->whereNull('deleted_at'),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('restaurant_tables', 'code')
                    ->where('location_id', $location->id)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['nullable', 'string', 'max:120'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $table = RestaurantTable::query()->create([
            ...$data,
            'location_id' => $location->id,
            'is_active' => true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'restaurant.table.created',
            module: 'restaurant',
            recordType: 'restaurant_tables',
            recordId: $table->id,
            newValues: $table->only([
                'location_id', 'area_id', 'code', 'name',
                'capacity', 'sort_order', 'is_active',
            ])
        );

        return back()->with('success', 'تم إنشاء الطاولة.');
    }

    public function toggleTable(Request $request, RestaurantTable $restaurantTable)
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        $location = $this->context->resolveLocation(
            $request->user(),
            isset($validated['location_id'])
                ? (int) $validated['location_id']
                : null
        );

        abort_unless(
            (int) $restaurantTable->location_id === (int) $location->id,
            403
        );

        if ($restaurantTable->is_active && $restaurantTable->isOccupied()) {
            return back()->with(
                'error',
                'لا يمكن تعطيل طاولة لديها جلسة مفتوحة.'
            );
        }

        $before = $restaurantTable->is_active;

        $restaurantTable->update([
            'is_active' => ! $before,
        ]);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'restaurant.table.status_changed',
            module: 'restaurant',
            recordType: 'restaurant_tables',
            recordId: $restaurantTable->id,
            oldValues: ['is_active' => $before],
            newValues: ['is_active' => ! $before],
            metadata: ['location_id' => $location->id],
        );

        return back()->with('success', 'تم تحديث حالة الطاولة.');
    }

    public function openSession(Request $request, RestaurantTable $restaurantTable)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        abort_unless(
            (int) $restaurantTable->location_id === (int) $location->id,
            403
        );

        $data = $request->validate([
            'guest_count' => ['required', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $session = $this->tables->open(
            $restaurantTable,
            $request->user(),
            (int) $data['guest_count'],
            $data['notes'] ?? null
        );

        $this->tables->notifyOpened($session);

        return back()->with('success', 'تم فتح جلسة الطاولة.');
    }

    public function closeSession(Request $request, RestaurantTable $restaurantTable)
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        $location = $this->context->resolveLocation(
            $request->user(),
            isset($validated['location_id'])
                ? (int) $validated['location_id']
                : null
        );

        abort_unless(
            (int) $restaurantTable->location_id === (int) $location->id,
            403
        );

        $session = $this->tables->close(
            $restaurantTable,
            $request->user()
        );

        $this->tables->notifyClosed($session);

        return back()->with('success', 'تم إغلاق جلسة الطاولة.');
    }
}
