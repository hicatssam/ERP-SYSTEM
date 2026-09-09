<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Color;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ColorController extends Controller
{
    public function index()
    {
        $colors = Color::query()->withCount('variants')->orderBy('sort_order')->orderBy('name')->get();
        return view('admin.catalog.colors', compact('colors'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request); $data['is_active'] = true;
        $color = Color::create($data);
        ActivityLogger::log($request->user()->id, 'color.created', 'products', Color::class, $color->id, null, $color->toArray());
        return back()->with('success', 'تمت إضافة اللون.');
    }

    public function update(Request $request, Color $color)
    {
        $before = $color->toArray(); $color->update($this->validated($request, $color));
        ActivityLogger::logChange($request->user()->id, 'color.updated', 'products', Color::class, $color->id, $before, $color->fresh()->toArray());
        return back()->with('success', 'تم تحديث اللون.');
    }

    public function toggle(Request $request, Color $color)
    {
        $before = $color->is_active; $color->update(['is_active' => ! $color->is_active]);
        ActivityLogger::log($request->user()->id, 'color.toggled', 'products', Color::class, $color->id, ['is_active'=>$before], ['is_active'=>$color->is_active]);
        return back()->with('success', 'تم تحديث حالة اللون.');
    }

    private function validated(Request $request, ?Color $color = null): array
    {
        return $request->validate([
            'code' => ['required','string','max:60', Rule::unique('colors','code')->ignore($color?->id)],
            'name' => ['required','string','max:100'],
            'name_ar' => ['nullable','string','max:100'],
            'hex_code' => ['nullable','regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable','integer','min:0'],
        ]) + ['sort_order'=>(int)$request->input('sort_order',0)];
    }
}
