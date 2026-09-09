<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $locations = $user->isAdmin() ? Location::active()->get() : collect([$user->primaryLocation()])->filter();

        $query = StockMovement::with(['location', 'product', 'creator'])
            ->whereIn('location_id', $locations->pluck('id'));

        if ($request->location_id) $query->where('location_id', $request->location_id);
        if ($request->reason) $query->where('reason', $request->reason);
        if ($request->date_from) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to) $query->whereDate('created_at', '<=', $request->date_to);

        $movements = $query->latest('created_at')->paginate(30);

        return view('inventory.movements', compact('movements', 'locations'));
    }
}
