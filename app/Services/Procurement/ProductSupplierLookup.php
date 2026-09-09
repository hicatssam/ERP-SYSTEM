<?php

namespace App\Services\Procurement;

use App\Models\SupplierProduct;
use Illuminate\Support\Collection;

class ProductSupplierLookup
{
    public function forProducts(iterable $productIds): Collection
    {
        $ids = collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return SupplierProduct::query()
            ->with(['supplier', 'currency'])
            ->whereIn('product_id', $ids)
            ->where('is_active', true)
            ->orderByDesc('is_preferred')
            ->orderBy('id')
            ->get()
            ->groupBy('product_id');
    }

    public function forProduct(int $productId): Collection
    {
        return $this->forProducts([$productId])
            ->get($productId, collect());
    }
}
