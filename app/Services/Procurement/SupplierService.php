<?php

namespace App\Services\Procurement;

use App\Enums\SupplierStatus;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierProductPriceHistory;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly CurrencyConversionService $currencies,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Supplier
    {
        return DB::transaction(function () use ($data, $actor) {
            $this->assertActiveCurrency((int) $data['currency_id']);

            $supplier = Supplier::query()->create([
                ...$this->supplierAttributes($data),
                'supplier_code' => $this->supplierCode($data),
                'created_by' => $actor->id,
            ]);

            $this->syncContacts($supplier, $data['contacts'] ?? []);
            $this->syncProducts($supplier, $data['products'] ?? [], $actor);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'supplier.created',
                module: 'procurement',
                recordType: 'suppliers',
                recordId: $supplier->id,
                oldValues: null,
                newValues: $supplier->only(['supplier_code', 'name', 'currency_id', 'status']),
                metadata: [
                    'contacts_count' => $supplier->contacts()->count(),
                    'products_count' => $supplier->supplierProducts()->count(),
                ],
            );

            return $supplier->load([
                'currency', 'contacts', 'supplierProducts.product',
                'supplierProducts.currency', 'supplierProducts.purchaseUnit',
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Supplier $supplier, array $data, User $actor): Supplier
    {
        return DB::transaction(function () use ($supplier, $data, $actor) {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);
            $this->assertActiveCurrency((int) $data['currency_id']);
            $before = $supplier->only(['name', 'currency_id', 'status', 'credit_limit', 'opening_balance']);

            $supplier->update($this->supplierAttributes($data));
            $this->syncContacts($supplier, $data['contacts'] ?? []);
            $this->syncProducts($supplier, $data['products'] ?? [], $actor);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'supplier.updated',
                module: 'procurement',
                recordType: 'suppliers',
                recordId: $supplier->id,
                oldValues: $before,
                newValues: $supplier->only(['name', 'currency_id', 'status', 'credit_limit', 'opening_balance']),
                metadata: [
                    'contacts_count' => $supplier->contacts()->count(),
                    'products_count' => $supplier->supplierProducts()->count(),
                ],
            );

            return $supplier->fresh([
                'currency', 'contacts', 'supplierProducts.product',
                'supplierProducts.currency', 'supplierProducts.purchaseUnit',
            ]);
        });
    }

    public function archive(Supplier $supplier, User $actor): Supplier
    {
        return DB::transaction(function () use ($supplier, $actor) {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);

            if ($supplier->purchaseOrders()->whereIn('status', ['draft', 'submitted', 'approved', 'partially_received'])->exists()) {
                throw ValidationException::withMessages([
                    'supplier' => 'لا يمكن أرشفة مورد لديه أوامر شراء مفتوحة.',
                ]);
            }

            $oldStatus = $supplier->statusValue();
            $supplier->update(['status' => SupplierStatus::Inactive]);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'supplier.archived',
                module: 'procurement',
                recordType: 'suppliers',
                recordId: $supplier->id,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => SupplierStatus::Inactive->value],
                metadata: [],
            );

            return $supplier->fresh();
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function supplierAttributes(array $data): array
    {
        return [
            'name' => trim((string) $data['name']),
            'company_name' => $data['company_name'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'tax_number' => $data['tax_number'] ?? null,
            'commercial_registration' => $data['commercial_registration'] ?? null,
            'currency_id' => $data['currency_id'],
            'payment_terms' => $data['payment_terms'] ?? null,
            'credit_limit' => $data['credit_limit'] ?? 0,
            'opening_balance' => $data['opening_balance'] ?? 0,
            'status' => $data['status'] ?? SupplierStatus::Active,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function supplierCode(array $data): string
    {
        $code = trim((string) ($data['supplier_code'] ?? ''));

        return $code !== '' ? $code : $this->numbers->next('supplier', 'SUP');
    }

    /** @param array<int, array<string, mixed>> $contacts */
    private function syncContacts(Supplier $supplier, array $contacts): void
    {
        $supplier->contacts()->delete();
        $primaryAssigned = false;

        foreach ($contacts as $contact) {
            if (blank($contact['name'] ?? null)) {
                continue;
            }

            $isPrimary = ! $primaryAssigned && (bool) ($contact['is_primary'] ?? false);
            $supplier->contacts()->create([
                'name' => trim((string) $contact['name']),
                'position' => $contact['position'] ?? null,
                'phone' => $contact['phone'] ?? null,
                'whatsapp' => $contact['whatsapp'] ?? null,
                'email' => $contact['email'] ?? null,
                'is_primary' => $isPrimary,
                'notes' => $contact['notes'] ?? null,
            ]);

            $primaryAssigned = $primaryAssigned || $isPrimary;
        }
    }

    /** @param array<int, array<string, mixed>> $products */
    private function syncProducts(Supplier $supplier, array $products, User $actor): void
    {
        $seenProductIds = [];

        foreach ($products as $row) {
            if (empty($row['product_id'])) {
                continue;
            }

            $productId = (int) $row['product_id'];
            Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
            if (in_array($productId, $seenProductIds, true)) {
                throw ValidationException::withMessages([
                    'products' => 'لا يمكن تكرار المنتج نفسه داخل مورد واحد.',
                ]);
            }

            $seenProductIds[] = $productId;
            $currencyId = (int) ($row['currency_id'] ?? $supplier->currency_id);
            $this->assertActiveCurrency($currencyId);

            $attributes = [
                'supplier_sku' => $row['supplier_sku'] ?? null,
                'supplier_product_name' => $row['supplier_product_name'] ?? null,
                'purchase_price' => $row['purchase_price'] ?? 0,
                'currency_id' => $currencyId,
                'purchase_unit_id' => $row['purchase_unit_id'] ?? null,
                'conversion_factor' => $row['conversion_factor'] ?? 1,
                'package_description' => $row['package_description'] ?? null,
                'minimum_order_quantity' => $row['minimum_order_quantity'] ?? 1,
                'lead_time_days' => $row['lead_time_days'] ?? null,
                'is_preferred' => (bool) ($row['is_preferred'] ?? false),
                'is_active' => array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true,
                'notes' => $row['notes'] ?? null,
            ];

            $supplierProduct = SupplierProduct::query()->firstOrNew([
                'supplier_id' => $supplier->id,
                'product_id' => $productId,
            ]);
            $priceChanged = ! $supplierProduct->exists
                || (float) $supplierProduct->purchase_price !== (float) $attributes['purchase_price']
                || (int) $supplierProduct->currency_id !== $currencyId
                || (int) $supplierProduct->purchase_unit_id !== (int) ($attributes['purchase_unit_id'] ?? 0)
                || (float) $supplierProduct->conversion_factor !== (float) $attributes['conversion_factor'];

            $supplierProduct->fill($attributes);
            $supplierProduct->save();

            if ((bool) $attributes['is_preferred']) {
                SupplierProduct::query()
                    ->where('product_id', $productId)
                    ->where('id', '!=', $supplierProduct->id)
                    ->update(['is_preferred' => false]);
            }

            if ($priceChanged) {
                $rate = $this->currencies->resolveRate($currencyId);
                SupplierProductPriceHistory::query()->create([
                    'supplier_product_id' => $supplierProduct->id,
                    'purchase_price' => $attributes['purchase_price'],
                    'currency_id' => $currencyId,
                    'purchase_unit_id' => $attributes['purchase_unit_id'],
                    'purchase_unit_snapshot' => $supplierProduct->purchaseUnit?->displayName(),
                    'conversion_factor' => $attributes['conversion_factor'],
                    'exchange_rate' => $rate,
                    'base_purchase_price' => $this->currencies->toBase($attributes['purchase_price'], $rate),
                    'reference_type' => 'supplier_products',
                    'reference_id' => $supplierProduct->id,
                    'effective_at' => now(),
                    'created_by' => $actor->id,
                ]);
            }
        }

        // Keep historical supplier-price rows intact, while ensuring a product
        // removed from the edit form cannot still be selected as an active
        // supplier product later.
        $removedProducts = SupplierProduct::query()
            ->where('supplier_id', $supplier->id);

        if ($seenProductIds !== []) {
            $removedProducts->whereNotIn('product_id', $seenProductIds);
        }

        $removedProducts->update([
            'is_active' => false,
            'is_preferred' => false,
        ]);
    }

    private function assertActiveCurrency(int $currencyId): void
    {
        if (! Currency::query()->active()->whereKey($currencyId)->exists()) {
            throw ValidationException::withMessages([
                'currency_id' => 'العملة المختارة غير موجودة أو غير مفعّلة.',
            ]);
        }
    }
}
