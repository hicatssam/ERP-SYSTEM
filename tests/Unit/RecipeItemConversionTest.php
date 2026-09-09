<?php

namespace Tests\Unit;

use App\Models\RecipeItem;
use PHPUnit\Framework\TestCase;

class RecipeItemConversionTest extends TestCase
{
    public function test_stock_quantity_includes_conversion_and_waste(): void
    {
        $item = new RecipeItem([
            'quantity' => 500,
            'expected_waste_percent' => 10,
        ]);

        $this->assertEqualsWithDelta(
            550,
            $item->grossQuantity(),
            0.000001
        );
    }
}
