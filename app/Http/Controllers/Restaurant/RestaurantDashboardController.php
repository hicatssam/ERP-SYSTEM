<?php

namespace App\Http\Controllers\Restaurant;

use App\Enums\RestaurantTableSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\KitchenTicket;
use App\Services\ModuleService;
use App\Services\Restaurant\RestaurantContextService;
use Illuminate\Http\Request;

class RestaurantDashboardController extends Controller
{
    public function __construct(
        private readonly RestaurantContextService $context,
        private readonly ModuleService $modules
    ) {
    }

    public function index(Request $request)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $locations = $this->context->selectableLocations($request->user());

        $tablesQuery = RestaurantTable::query()
            ->forLocation($location->id)
            ->active();

        $totalTables = (clone $tablesQuery)->count();

        $occupiedTables = (clone $tablesQuery)
            ->whereHas('activeSession', fn ($query) => $query->where(
                'status',
                RestaurantTableSessionStatus::Open->value
            ))
            ->count();

        $todayOrders = Order::query()
            ->where('location_id', $location->id)
            ->whereNotNull('restaurant_service_type')
            ->whereDate('created_at', today());

        $todayOrderCount = (clone $todayOrders)->count();

        $todaySales = (float) (clone $todayOrders)
            ->whereIn('status', ['confirmed', 'completed'])
            ->sum('total_amount');

        $openOrders = (clone $todayOrders)
            ->whereIn('status', ['draft', 'confirmed'])
            ->count();

        $kitchenEnabled = $this->modules->isEnabled('kitchen');
        $kdsEnabled = $this->modules->isEnabled('kds');

        $kitchenCounts = [
            'queued' => 0,
            'preparing' => 0,
            'ready' => 0,
            'urgent' => 0,
        ];

        if ($kitchenEnabled) {
            $baseKitchen = KitchenTicket::query()
                ->where('location_id', $location->id);

            $kitchenCounts['queued'] = (clone $baseKitchen)
                ->where('status', 'queued')
                ->count();

            $kitchenCounts['preparing'] = (clone $baseKitchen)
                ->where('status', 'preparing')
                ->count();

            $kitchenCounts['ready'] = (clone $baseKitchen)
                ->where('status', 'ready')
                ->count();

            $kitchenCounts['urgent'] = (clone $baseKitchen)
                ->whereIn('status', ['queued', 'preparing', 'ready'])
                ->where('priority', '>=', 10)
                ->count();
        }

        $recentOrders = Order::query()
            ->where('location_id', $location->id)
            ->whereNotNull('restaurant_service_type')
            ->with([
                'customer',
                'restaurantTable.area',
                'waiter.employee',
            ])
            ->latest()
            ->limit(10)
            ->get();

        return view('restaurant.dashboard', compact(
            'location',
            'locations',
            'totalTables',
            'occupiedTables',
            'todayOrderCount',
            'todaySales',
            'openOrders',
            'recentOrders',
            'kitchenEnabled',
            'kdsEnabled',
            'kitchenCounts',
        ));
    }
}
