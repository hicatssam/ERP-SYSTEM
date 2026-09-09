<?php

namespace Tests\Unit\Procurement;

use App\Services\Procurement\PurchaseUnitConverter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PurchaseUnitConverterTest extends TestCase
{
    #[DataProvider('conversionCases')]
    public function test_it_converts_purchase_packages_to_inventory_units(
        float $quantity,
        float $factor,
        float $expectedBaseQuantity,
        float $purchasePrice,
        float $expectedBasePrice
    ): void {
        $converter = new PurchaseUnitConverter();

        self::assertSame($expectedBaseQuantity, $converter->baseQuantity($quantity, $factor));
        self::assertSame($expectedBasePrice, $converter->pricePerBaseUnit($purchasePrice, $factor));
    }

    public static function conversionCases(): array
    {
        return [
            'legacy direct unit' => [3, 1, 3.0, 30, 30.0],
            'carton of twelve' => [2, 12, 24.0, 240, 20.0],
            'half-kilo package' => [4, 0.5, 2.0, 15, 30.0],
        ];
    }

    public function test_it_rejects_zero_or_negative_conversion_factors(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PurchaseUnitConverter())->baseQuantity(1, 0);
    }
}
