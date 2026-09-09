<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->select(['id', 'sku', 'barcode'])
            ->orderBy('id')
            ->chunkById(200, function ($products): void {
                foreach ($products as $product) {
                    $updates = [];

                    if (blank($product->sku)) {
                        $updates['sku'] = $this->generateUniqueSku();
                    }

                    if (blank($product->barcode)) {
                        $updates['barcode'] = $this->generateUniqueBarcode();
                    }

                    if ($updates !== []) {
                        DB::table('products')
                            ->where('id', $product->id)
                            ->update($updates);
                    }
                }
            });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('barcode', 100)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('barcode', 100)->nullable()->change();
        });
    }

    private function generateUniqueSku(): string
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $sku = 'PRD-' . Str::upper(Str::random(10));

            if (! DB::table('products')->where('sku', $sku)->exists()) {
                return $sku;
            }
        }

        throw new \RuntimeException('Unable to generate a unique product SKU.');
    }

    private function generateUniqueBarcode(): string
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $body = '200' . str_pad(
                (string) random_int(0, 999999999),
                9,
                '0',
                STR_PAD_LEFT
            );

            $barcode = $body . $this->calculateEan13CheckDigit($body);

            if (! DB::table('products')->where('barcode', $barcode)->exists()) {
                return $barcode;
            }
        }

        throw new \RuntimeException('Unable to generate a unique product barcode.');
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
};