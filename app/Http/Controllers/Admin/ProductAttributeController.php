<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductAttributeController extends Controller
{
    public function index()
    {
        $attributes = ProductAttribute::query()->with('values')->orderBy('sort_order')->orderBy('name')->get();
        return view('admin.catalog.attributes', compact('attributes'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedAttribute($request); $data['is_active'] = true;
        $attribute = ProductAttribute::create($data);
        ActivityLogger::log($request->user()->id, 'product_attribute.created', 'products', ProductAttribute::class, $attribute->id, null, $attribute->toArray());
        return back()->with('success', 'تمت إضافة الخاصية.');
    }

    public function update(Request $request, ProductAttribute $attribute)
    {
        $before = $attribute->toArray(); $attribute->update($this->validatedAttribute($request, $attribute));
        ActivityLogger::logChange($request->user()->id, 'product_attribute.updated', 'products', ProductAttribute::class, $attribute->id, $before, $attribute->fresh()->toArray());
        return back()->with('success', 'تم تحديث الخاصية.');
    }

    public function toggle(Request $request, ProductAttribute $attribute)
    {
        $before = $attribute->is_active; $attribute->update(['is_active'=>!$attribute->is_active]);
        ActivityLogger::log($request->user()->id, 'product_attribute.toggled', 'products', ProductAttribute::class, $attribute->id, ['is_active'=>$before], ['is_active'=>$attribute->is_active]);
        return back()->with('success', 'تم تحديث حالة الخاصية.');
    }

    public function storeValue(Request $request, ProductAttribute $attribute)
    {
        $data = $request->validate([
            'code' => ['required','string','max:60', Rule::unique('product_attribute_values','code')->where(fn($q)=>$q->where('product_attribute_id',$attribute->id))],
            'name' => ['required','string','max:120'],
            'name_ar' => ['nullable','string','max:120'],
            'sort_order' => ['nullable','integer','min:0'],
        ]);
        $value = $attribute->values()->create($data + ['is_active'=>true,'sort_order'=>(int)$request->input('sort_order',0)]);
        ActivityLogger::log($request->user()->id, 'product_attribute_value.created', 'products', ProductAttributeValue::class, $value->id, null, $value->toArray(), ['attribute_id'=>$attribute->id]);
        return back()->with('success', 'تمت إضافة قيمة الخاصية.');
    }

    public function toggleValue(Request $request, ProductAttributeValue $value)
    {
        $before = $value->is_active; $value->update(['is_active'=>!$value->is_active]);
        ActivityLogger::log($request->user()->id, 'product_attribute_value.toggled', 'products', ProductAttributeValue::class, $value->id, ['is_active'=>$before], ['is_active'=>$value->is_active]);
        return back()->with('success', 'تم تحديث حالة قيمة الخاصية.');
    }

    private function validatedAttribute(Request $request, ?ProductAttribute $attribute = null): array
    {
        return $request->validate([
            'code' => ['required','string','max:60','regex:/^[A-Za-z0-9_\\-]+$/',Rule::unique('product_attributes','code')->ignore($attribute?->id)],
            'name' => ['required','string','max:120'],
            'name_ar' => ['nullable','string','max:120'],
            'sort_order' => ['nullable','integer','min:0'],
        ]) + ['sort_order'=>(int)$request->input('sort_order',0)];
    }
}
