<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreExchangeRateRequest;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExchangeRateController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Currency::class);

        return view('procurement.exchange-rates.index', [
            'currencies' => Currency::query()->active()->orderByDesc('is_base')->orderBy('code')->get(),
            'rates' => ExchangeRate::query()
                ->with(['currency', 'creator.employee'])
                ->latest('effective_date')
                ->latest('id')
                ->paginate(30),
        ]);
    }

    public function store(StoreExchangeRateRequest $request): RedirectResponse
    {
        $this->authorize('manage', Currency::class);
        $validated = $request->validated();
        $currency = Currency::query()->active()->findOrFail($validated['currency_id']);

        $rate = $currency->is_base
            ? '1.00000000'
            : number_format((float) $validated['rate_to_base'], 8, '.', '');

        DB::transaction(function () use ($currency, $validated, $rate, $request): void {
            $exchangeRate = ExchangeRate::query()->updateOrCreate(
                [
                    'currency_id' => $currency->id,
                    'effective_date' => $validated['effective_date'],
                ],
                [
                    'rate_to_base' => $rate,
                    'created_by' => $request->user()->id,
                ],
            );

            ActivityLogger::log(
                userId: $request->user()->id,
                action: 'exchange_rate.saved',
                module: 'procurement',
                recordType: 'exchange_rates',
                recordId: $exchangeRate->id,
                oldValues: null,
                newValues: [
                    'currency_id' => $currency->id,
                    'effective_date' => $validated['effective_date'],
                    'rate_to_base' => $rate,
                ],
                metadata: ['currency_code' => $currency->code],
            );
        });

        return back()->with('success', "تم حفظ سعر صرف {$currency->code}.");
    }
}
