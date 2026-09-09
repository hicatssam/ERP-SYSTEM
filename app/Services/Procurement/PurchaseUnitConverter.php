<?php

namespace App\Services\Procurement;

use InvalidArgumentException;

class PurchaseUnitConverter
{
    public function factor(float|int|string|null $value): float
    {
        $factor = round((float) ($value ?? 1), 6);

        if ($factor <= 0) {
            throw new InvalidArgumentException('Purchase-unit conversion factor must be greater than zero.');
        }

        return $factor;
    }

    public function baseQuantity(float|int|string $purchaseQuantity, float|int|string|null $factor): float
    {
        return round((float) $purchaseQuantity * $this->factor($factor), 3);
    }

    public function pricePerBaseUnit(float|int|string $purchasePrice, float|int|string|null $factor): float
    {
        return round((float) $purchasePrice / $this->factor($factor), 4);
    }
}
