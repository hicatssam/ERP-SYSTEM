<?php

namespace App\Http\Controllers\Production;

use App\Enums\ProductionOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Services\Production\ProductionContextService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionDashboardController extends Controller
{
    public function __construct(
        private readonly ProductionContextService $context
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $locationIds = $this->context->accessibleLocationIds($user);

        $orders = ProductionOrder::query()
            ->whereIn('location_id', $locationIds);

        $stats = [
            'draft' => (clone $orders)
                ->where('status', ProductionOrderStatus::DRAFT->value)
                ->count(),
            'released' => (clone $orders)
                ->where('status', ProductionOrderStatus::RELEASED->value)
                ->count(),
            'in_progress' => (clone $orders)
                ->where('status', ProductionOrderStatus::IN_PROGRESS->value)
                ->count(),
            'completed_today' => (clone $orders)
                ->where('status', ProductionOrderStatus::COMPLETED->value)
                ->whereDate('completed_at', today())
                ->count(),
            'active_recipes' => Recipe::query()->active()->count(),
        ];

        $recent = ProductionOrder::query()
            ->with(['product', 'location', 'recipe'])
            ->whereIn('location_id', $locationIds)
            ->latest('id')
            ->limit(8)
            ->get();

        return view('production.dashboard', [
            'stats' => $stats,
            'recent' => $recent,
        ]);
    }
}
