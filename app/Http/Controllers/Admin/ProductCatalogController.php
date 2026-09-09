<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Color;
use App\Models\ProductAttribute;
use App\Models\Size;
use App\Models\Unit;
use App\Services\ModuleService;

class ProductCatalogController extends Controller
{
    public function __invoke(ModuleService $modules)
    {
        $stats = [
            'units' => Unit::query()->count(),
            'brands' => Brand::query()->count(),
            'sizes' => Size::query()->count(),
            'colors' => Color::query()->count(),
            'attributes' => ProductAttribute::query()->count(),
        ];

        $flags = [
            'brands' => $modules->isEnabled('brands'),
            'sizes' => $modules->isEnabled('sizes'),
            'colors' => $modules->isEnabled('colors'),
            'variants' => $modules->isEnabled('product_variants'),
        ];

        return view('admin.catalog.index', compact('stats', 'flags'));
    }
}
