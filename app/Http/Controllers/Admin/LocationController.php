<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLocationRequest;
use App\Http\Requests\Admin\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index()
    {
        $locations = Location::withCount('employees')->orderBy('type')->orderBy('name')->get();
        return view('admin.locations.index', compact('locations'));
    }

    public function create()
    {
        return view('admin.locations.create');
    }

    public function store(StoreLocationRequest $request)
    {
        Location::create($request->validated());
        return redirect()->route('locations.index')->with('success', 'تم إنشاء الموقع بنجاح.');
    }

    public function show(Location $location)
    {
        $location->load('employees', 'inventories.product');
        return view('admin.locations.show', compact('location'));
    }

    public function edit(Location $location)
    {
        return view('admin.locations.edit', compact('location'));
    }

    public function update(UpdateLocationRequest $request, Location $location)
    {
        $location->update($request->validated());
        return redirect()->route('locations.index')->with('success', 'تم تحديث الموقع بنجاح.');
    }

    public function destroy(Location $location)
    {
        return redirect()->route('locations.index')->with('error', 'لا يمكن حذف الموقع.');
    }

    public function toggleStatus(Location $location)
    {
        $location->update(['is_active' => ! $location->is_active]);
        $msg = $location->is_active ? 'تم تفعيل الموقع.' : 'تم تعطيل الموقع.';
        return back()->with('success', $msg);
    }
}
