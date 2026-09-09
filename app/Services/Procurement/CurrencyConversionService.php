<?php

namespace App\Services\Procurement;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class CurrencyConversionService
{
    public function baseCurrency(): Currency
    {
        return Currency::query()
            ->where('is_base', true)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * Returns the number of base-currency units for one transaction-currency unit.
     */
    public function resolveRate(
        int $currencyId,
        ?string $providedRate = null,
        ?CarbonInterface $onDate = null
    ): string {
        $currency = Currency::query()->active()->findOrFail($currencyId);

        if ($currency->is_base) {
            return '1.00000000';
        }

        if ($providedRate !== null && (float) $providedRate > 0) {
            return $this->decimal($providedRate, 8);
        }

        $rate = ExchangeRate::query()
            ->where('currency_id', $currency->id)
            ->whereDate('effective_date', '<=', ($onDate ?? now())->toDateString())
            ->latest('effective_date')
            ->value('rate_to_base');

        if ($rate === null || (float) $rate <= 0) {
            throw ValidationException::withMessages([
                'exchange_rate' => "لا يوجد سعر صرف صالح للعملة {$currency->code}.",
            ]);
        }

        return $this->decimal($rate, 8);
    }

    public function toBase(string|float|int $amount, string|float|int $rate): string
    {
        return $this->decimal((float) $amount * (float) $rate, 4);
    }

    public function fromBase(string|float|int $amount, string|float|int $rate): string
    {
        if ((float) $rate <= 0) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'سعر الصرف يجب أن يكون أكبر من صفر.',
            ]);
        }

        return $this->decimal((float) $amount / (float) $rate, 4);
    }

    public function money(string|float|int $amount): string
    {
        return $this->decimal($amount, 2);
    }

    public function decimal(string|float|int $amount, int $scale): string
    {
        return number_format(round((float) $amount, $scale), $scale, '.', '');
    }
}
