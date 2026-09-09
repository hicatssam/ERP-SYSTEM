<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::query()->withCount('products')->orderBy('sort_order')->orderBy('name')->get();
        return view('admin.catalog.units', compact('units'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_active'] = true;
        $data['is_system'] = false;
        $unit = Unit::create($data);
        ActivityLogger::log($request->user()->id, 'unit.created', 'products', Unit::class, $unit->id, null, $unit->toArray());
        return back()->with('success', 'تمت إضافة وحدة القياس.');
    }

    public function update(Request $request, Unit $unit)
    {
        $before = $unit->toArray();
        $data = $this->validated($request, $unit);
        $unit->update($data);
        ActivityLogger::logChange($request->user()->id, 'unit.updated', 'products', Unit::class, $unit->id, $before, $unit->fresh()->toArray());
        return back()->with('success', 'تم تحديث وحدة القياس.');
    }

    public function toggle(Request $request, Unit $unit)
    {
        if ($unit->is_active && $unit->products()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['unit' => 'لا يمكن تعطيل وحدة مستخدمة في منتجات فعالة.']);
        }
        $before = $unit->is_active;
        $unit->update(['is_active' => ! $unit->is_active]);
        ActivityLogger::log($request->user()->id, 'unit.toggled', 'products', Unit::class, $unit->id, ['is_active' => $before], ['is_active' => $unit->is_active]);
        return back()->with('success', 'تم تحديث حالة وحدة القياس.');
    }

    private function validated(Request $request, ?Unit $unit = null): array
    {
        return $request->validate([
            'code' => ['required','string','max:40','regex:/^[A-Za-z0-9_\\-]+$/', Rule::unique('units','code')->ignore($unit?->id)],
            'name' => ['required','string','max:100'],
            'name_ar' => ['nullable','string','max:100'],
            'symbol' => ['nullable','string','max:20'],
            'allow_decimal' => ['nullable','boolean'],
            'precision' => ['required','integer','between:0,6'],
            'sort_order' => ['nullable','integer','min:0'],
        ]) + [
            'allow_decimal' => $request->boolean('allow_decimal'),
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }
}
