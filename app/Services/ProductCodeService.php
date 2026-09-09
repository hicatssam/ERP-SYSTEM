<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;
use RuntimeException;

class ProductCodeService
{
    public function generateProductSku(): string
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $sku = 'PRD-' . Str::upper(Str::random(10));

            if ($this->skuAvailable($sku)) {
                return $sku;
            }
        }

        throw new RuntimeException('تعذر إنشاء SKU فريد للمنتج.');
    }

    public function generateVariantSku(Product $product): string
    {
        $prefix = Str::upper(Str::limit((string) $product->sku, 36, ''));
        $prefix = $prefix !== '' ? $prefix : 'VAR';

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $sku = $prefix . '-V' . Str::upper(Str::random(6));

            if ($this->skuAvailable($sku)) {
                return $sku;
            }
        }

        throw new RuntimeException('تعذر إنشاء SKU فريد للمتغير.');
    }

    public function generateEan13(): string
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $body = '200' . str_pad(
                (string) random_int(0, 999999999),
                9,
                '0',
                STR_PAD_LEFT
            );

            $barcode = $body . $this->calculateEan13CheckDigit($body);

            if ($this->barcodeAvailable($barcode)) {
                return $barcode;
            }
        }

        throw new RuntimeException('تعذر إنشاء باركود EAN-13 فريد.');
    }

    public function skuAvailable(string $sku, ?int $ignoreProductId = null, ?int $ignoreVariantId = null): bool
    {
        $productExists = Product::withTrashed()
            ->where('sku', $sku)
            ->when($ignoreProductId, fn ($q) => $q->whereKeyNot($ignoreProductId))
            ->exists();

        if ($productExists) {
            return false;
        }

        return ! ProductVariant::query()
            ->where('sku', $sku)
            ->when($ignoreVariantId, fn ($q) => $q->whereKeyNot($ignoreVariantId))
            ->exists();
    }

    public function barcodeAvailable(string $barcode, ?int $ignoreProductId = null, ?int $ignoreVariantId = null): bool
    {
        $productExists = Product::withTrashed()
            ->where('barcode', $barcode)
            ->when($ignoreProductId, fn ($q) => $q->whereKeyNot($ignoreProductId))
            ->exists();

        if ($productExists) {
            return false;
        }

        return ! ProductVariant::query()
            ->where('barcode', $barcode)
            ->when($ignoreVariantId, fn ($q) => $q->whereKeyNot($ignoreVariantId))
            ->exists();
    }

    public function isValidEan13(string $barcode): bool
    {
        if (! preg_match('/^\\d{13}$/', $barcode)) {
            return false;
        }

        return (int) $barcode[12] === $this->calculateEan13CheckDigit(substr($barcode, 0, 12));
    }

    private function calculateEan13CheckDigit(string $body): int
    {
        $sum = 0;

        for ($index = 0; $index < 12; $index++) {
            $digit = (int) $body[$index];
            $sum += $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }
}
