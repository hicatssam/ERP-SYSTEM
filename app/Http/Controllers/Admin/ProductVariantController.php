<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ModuleService;
use App\Services\ProductCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductVariantController extends Controller
{
    public function __construct(
        private readonly ProductCodeService $codes,
        private readonly ModuleService $modules,
    ) {
    }

    public function index(Request $request, Product $product)
    {
        $this->ensureProductAccess($product, $request->user());
        $variants = $product->variants()->with(['size','color','attributeValues.attribute'])->get();
        return view('admin.products.variants.index', compact('product','variants'));
    }

    public function create(Request $request, Product $product)
    {
        $this->ensureProductAccess($product, $request->user());
        return view('admin.products.variants.create', $this->formData($product));
    }

    public function store(Request $request, Product $product)
    {
        $this->ensureProductAccess($product, $request->user());
        $data = $this->validated($request);
        $attributeIds = $data['attribute_value_ids'] ?? [];
        unset($data['attribute_value_ids']);

        $data['sku'] = trim((string)($data['sku'] ?? '')) ?: $this->codes->generateVariantSku($product);
        $data['barcode'] = trim((string)($data['barcode'] ?? '')) ?: $this->codes->generateEan13();
        $this->validateCodes($data['sku'], $data['barcode']);
        $this->validateModuleSelections($data);

        if ($request->hasFile('image')) $data['image'] = $request->file('image')->store('product-variants','public');
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_default'] = $request->boolean('is_default');
        $data['sort_order'] = (int)$request->input('sort_order',0);
        $data['product_id'] = $product->id;

        $variant = DB::transaction(function() use($data,$attributeIds,$product) {
            if ($data['is_default']) $product->variants()->update(['is_default'=>false]);
            $variant = ProductVariant::create($data);
            $variant->attributeValues()->sync($attributeIds);
            if ($product->product_type !== ProductType::VARIANT) $product->update(['product_type'=>ProductType::VARIANT]);
            return $variant;
        });

        ActivityLogger::log($request->user()->id, 'product_variant.created', 'products', ProductVariant::class, $variant->id, null, $variant->toArray(), ['product_id'=>$product->id]);
        return redirect()->route('products.variants.index',$product)->with('success','تم إنشاء متغير المنتج.');
    }

    public function edit(Request $request, Product $product, ProductVariant $variant)
    {
        $this->guardVariant($product,$variant); $this->ensureProductAccess($product,$request->user());
        $variant->load('attributeValues');
        return view('admin.products.variants.edit', array_merge($this->formData($product), compact('variant')));
    }

    public function update(Request $request, Product $product, ProductVariant $variant)
    {
        $this->guardVariant($product,$variant); $this->ensureProductAccess($product,$request->user());
        $before = $variant->toArray();
        $data = $this->validated($request, $variant);
        $attributeIds = $data['attribute_value_ids'] ?? [];
        unset($data['attribute_value_ids']);
        $data['sku'] = trim((string)($data['sku'] ?? '')) ?: $variant->sku;
        $data['barcode'] = trim((string)($data['barcode'] ?? '')) ?: $variant->barcode;
        $this->validateCodes($data['sku'], $data['barcode'], $variant);
        $this->validateModuleSelections($data);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_default'] = $request->boolean('is_default');
        $data['sort_order'] = (int)$request->input('sort_order',0);

        if ($request->hasFile('image')) {
            if ($variant->image && Storage::disk('public')->exists($variant->image)) Storage::disk('public')->delete($variant->image);
            $data['image'] = $request->file('image')->store('product-variants','public');
        } else unset($data['image']);

        DB::transaction(function() use($product,$variant,$data,$attributeIds){
            if ($data['is_default']) $product->variants()->whereKeyNot($variant->id)->update(['is_default'=>false]);
            $variant->update($data); $variant->attributeValues()->sync($attributeIds);
        });
        ActivityLogger::logChange($request->user()->id,'product_variant.updated','products',ProductVariant::class,$variant->id,$before,$variant->fresh()->toArray(),['product_id'=>$product->id]);
        return redirect()->route('products.variants.index',$product)->with('success','تم تحديث المتغير.');
    }

    public function toggle(Request $request, Product $product, ProductVariant $variant)
    {
        $this->guardVariant($product,$variant); $this->ensureProductAccess($product,$request->user());
        $before=$variant->is_active; $variant->update(['is_active'=>!$variant->is_active]);
        ActivityLogger::log($request->user()->id,'product_variant.toggled','products',ProductVariant::class,$variant->id,['is_active'=>$before],['is_active'=>$variant->is_active],['product_id'=>$product->id]);
        return back()->with('success','تم تحديث حالة المتغير.');
    }

    private function formData(Product $product): array
    {
        return [
            'product'=>$product,
            'sizes'=>$this->modules->isEnabled('sizes') ? Size::query()->active()->orderBy('sort_order')->get() : collect(),
            'colors'=>$this->modules->isEnabled('colors') ? Color::query()->active()->orderBy('sort_order')->get() : collect(),
            'attributes'=>ProductAttribute::query()->active()->with(['values'=>fn($q)=>$q->active()->orderBy('sort_order')])->orderBy('sort_order')->get(),
        ];
    }

    private function validated(Request $request, ?ProductVariant $variant = null): array
    {
        return $request->validate([
            'name'=>['nullable','string','max:180'], 'name_ar'=>['nullable','string','max:180'],
            'size_id'=>['nullable','exists:sizes,id'], 'color_id'=>['nullable','exists:colors,id'],
            'sku'=>['nullable','string','max:80'], 'barcode'=>['nullable','digits:13'],
            'selling_price'=>['nullable','numeric','min:0'], 'image'=>['nullable','image','mimes:jpeg,jpg,png,webp','max:2048'],
            'is_default'=>['nullable','boolean'], 'is_active'=>['nullable','boolean'], 'sort_order'=>['nullable','integer','min:0'],
            'attribute_value_ids'=>['nullable','array'], 'attribute_value_ids.*'=>['integer','distinct','exists:product_attribute_values,id'],
        ]);
    }

    private function validateCodes(string $sku, ?string $barcode, ?ProductVariant $variant = null): void
    {
        if (! $this->codes->skuAvailable($sku, null, $variant?->id)) throw ValidationException::withMessages(['sku'=>'SKU مستخدم مسبقًا في منتج أو متغير آخر.']);
        if ($barcode && ! $this->codes->isValidEan13($barcode)) throw ValidationException::withMessages(['barcode'=>'الباركود يجب أن يكون EAN-13 صحيحًا.']);
        if ($barcode && ! $this->codes->barcodeAvailable($barcode, null, $variant?->id)) throw ValidationException::withMessages(['barcode'=>'الباركود مستخدم مسبقًا في منتج أو متغير آخر.']);
    }

    private function validateModuleSelections(array $data): void
    {
        if (!empty($data['size_id']) && !$this->modules->isEnabled('sizes')) throw ValidationException::withMessages(['size_id'=>'وحدة المقاسات غير مفعلة.']);
        if (!empty($data['color_id']) && !$this->modules->isEnabled('colors')) throw ValidationException::withMessages(['color_id'=>'وحدة الألوان غير مفعلة.']);
    }

    private function guardVariant(Product $product, ProductVariant $variant): void
    {
        abort_unless((int)$variant->product_id === (int)$product->id, 404);
    }

    private function ensureProductAccess(Product $product, User $user): void
    {
        if ((method_exists($user,'isAdmin') && $user->isAdmin()) || $user->can('roles.manage')) return;
        $location = $user->primaryLocation();
        abort_unless($location && $product->locationProducts()->where('location_id',$location->id)->where('is_available',true)->exists(),403,'لا يمكنك الوصول إلى منتج لا يخص فرعك.');
    }
}
