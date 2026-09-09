<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Size;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SizeController extends Controller
{
    public function index()
    {
        $sizes = Size::query()->withCount('variants')->orderBy('sort_order')->orderBy('name')->get();
        return view('admin.catalog.sizes', compact('sizes'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request); $data['is_active'] = true;
        $size = Size::create($data);
        ActivityLogger::log($request->user()->id, 'size.created', 'products', Size::class, $size->id, null, $size->toArray());
        return back()->with('success', 'تمت إضافة المقاس.');
    }

    public function update(Request $request, Size $size)
    {
        $before = $size->toArray(); $size->update($this->validated($request, $size));
        ActivityLogger::logChange($request->user()->id, 'size.updated', 'products', Size::class, $size->id, $before, $size->fresh()->toArray());
        return back()->with('success', 'تم تحديث المقاس.');
    }

    public function toggle(Request $request, Size $size)
    {
        $before = $size->is_active; $size->update(['is_active' => ! $size->is_active]);
        ActivityLogger::log($request->user()->id, 'size.toggled', 'products', Size::class, $size->id, ['is_active'=>$before], ['is_active'=>$size->is_active]);
        return back()->with('success', 'تم تحديث حالة المقاس.');
    }

    private function validated(Request $request, ?Size $size = null): array
    {
        return $request->validate([
            'code' => ['required','string','max:60', Rule::unique('sizes','code')->ignore($size?->id)],
            'name' => ['required','string','max:100'],
            'name_ar' => ['nullable','string','max:100'],
            'sort_order' => ['nullable','integer','min:0'],
        ]) + ['sort_order'=>(int)$request->input('sort_order',0)];
    }
}
