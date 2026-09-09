<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreSystemCurrencyRequest;
use App\Http\Requests\Settings\UpdateSystemCurrencyRequest;
use App\Models\Currency;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SystemCurrencyController extends Controller
{
    public function index(Request $request): View
    {
        $query = Currency::query();

        if ($request->filled('q')) {
            $search = trim($request->string('q')->toString());

            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('symbol', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where(
                'is_active',
                $request->string('status')->toString() === 'active'
            );
        }

        return view('settings.currencies.index', [
            'currencies' => $query
                ->ordered()
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function store(
        StoreSystemCurrencyRequest $request
    ): RedirectResponse {
        $currency = DB::transaction(function () use ($request): Currency {
            $validated = $request->validated();

            $data = [
                'code' => $validated['code'],
                'name' => $validated['name_en']
                    ?: $validated['name_ar'],
                'name_ar' => $validated['name_ar'],
                'symbol' => $validated['symbol'],
                'decimal_places' => $validated['decimal_places'],
                'is_active' => $validated['is_active'],
                'is_base' => false,
                'icon' => $validated['icon'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                'notes' => $validated['notes'] ?? null,
            ];

            if ($request->hasFile('image')) {
                $data['image'] = $request
                    ->file('image')
                    ->store('currencies', 'public');
            }

            /*
             * حماية فقط لقاعدة بيانات فارغة تماماً.
             * في النظام الحالي توجد Base Currency بالفعل، لذلك الإضافة الجديدة
             * لا تغيّر is_base ولا أسعار الصرف.
             */
            if (! Currency::query()->exists()) {
                $data['is_base'] = true;
                $data['is_active'] = true;
            }

            $currency = Currency::query()->create($data);

            ActivityLogger::log(
                userId: $request->user()->id,
                action: 'currency.created',
                module: 'settings',
                recordType: 'currencies',
                recordId: $currency->id,
                oldValues: null,
                newValues: $currency->only([
                    'code',
                    'name',
                    'name_ar',
                    'symbol',
                    'decimal_places',
                    'is_base',
                    'is_active',
                    'icon',
                    'sort_order',
                ]),
            );

            return $currency;
        });

        return redirect()
            ->route('settings.currencies.index')
            ->with(
                'success',
                "تمت إضافة عملة {$currency->code} وأصبحت متاحة مباشرة في وحدة أسعار الصرف."
            );
    }

    public function update(
        UpdateSystemCurrencyRequest $request,
        Currency $currency
    ): RedirectResponse {
        DB::transaction(function () use ($request, $currency): void {
            $before = $currency->only([
                'code',
                'name',
                'name_ar',
                'symbol',
                'decimal_places',
                'is_base',
                'is_active',
                'icon',
                'image',
                'sort_order',
                'notes',
            ]);

            $validated = $request->validated();

            $data = [
                'code' => $validated['code'],
                'name' => $validated['name_en']
                    ?: $validated['name_ar'],
                'name_ar' => $validated['name_ar'],
                'symbol' => $validated['symbol'],
                'decimal_places' => $validated['decimal_places'],
                'is_active' => $validated['is_active'],
                'icon' => $validated['icon'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                'notes' => $validated['notes'] ?? null,
            ];

            /*
             * العملة الأساسية لا يمكن تعطيلها من شاشة الإدارة،
             * لأن أسعار الصرف والمشتريات الحالية تعتمد عليها.
             */
            if ($currency->is_base) {
                $data['is_active'] = true;
            }

            if ($request->hasFile('image')) {
                $oldImage = $currency->image;

                $data['image'] = $request
                    ->file('image')
                    ->store('currencies', 'public');

                if ($oldImage) {
                    Storage::disk('public')->delete($oldImage);
                }
            }

            $currency->update($data);

            ActivityLogger::log(
                userId: $request->user()->id,
                action: 'currency.updated',
                module: 'settings',
                recordType: 'currencies',
                recordId: $currency->id,
                oldValues: $before,
                newValues: $currency->fresh()->only([
                    'code',
                    'name',
                    'name_ar',
                    'symbol',
                    'decimal_places',
                    'is_base',
                    'is_active',
                    'icon',
                    'image',
                    'sort_order',
                    'notes',
                ]),
            );
        });

        return redirect()
            ->route('settings.currencies.index')
            ->with('success', 'تم تحديث العملة بنجاح.');
    }

    public function toggle(
        Request $request,
        Currency $currency
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('system_currencies.manage'),
            403
        );

        if ($currency->is_base && $currency->is_active) {
            return back()->with(
                'error',
                'لا يمكن تعطيل العملة الأساسية لأنها مستخدمة كأساس لأسعار الصرف.'
            );
        }

        $before = $currency->is_active;

        $currency->update([
            'is_active' => ! $before,
        ]);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'currency.status_changed',
            module: 'settings',
            recordType: 'currencies',
            recordId: $currency->id,
            oldValues: ['is_active' => $before],
            newValues: ['is_active' => ! $before],
        );

        return back()->with('success', 'تم تحديث حالة العملة.');
    }

    public function destroy(
        Request $request,
        Currency $currency
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('system_currencies.manage'),
            403
        );

        if ($currency->is_base) {
            return back()->with(
                'error',
                'لا يمكن حذف العملة الأساسية.'
            );
        }

        $usage = $this->currencyUsage($currency->id);

        if ($usage !== []) {
            return back()->with(
                'error',
                'لا يمكن حذف هذه العملة لأنها مستخدمة في بيانات مالية أو مشتريات. يمكنك تعطيلها بدلًا من حذفها.'
            );
        }

        $before = $currency->toArray();

        DB::transaction(function () use (
            $request,
            $currency,
            $before
        ): void {
            $image = $currency->image;
            $id = $currency->id;

            $currency->delete();

            if ($image) {
                Storage::disk('public')->delete($image);
            }

            ActivityLogger::log(
                userId: $request->user()->id,
                action: 'currency.deleted',
                module: 'settings',
                recordType: 'currencies',
                recordId: $id,
                oldValues: $before,
                newValues: null,
            );
        });

        return redirect()
            ->route('settings.currencies.index')
            ->with('success', 'تم حذف العملة.');
    }

    /**
     * يمنع حذف أي Currency دخلت فعلياً في دورة المشتريات/المخزون/أسعار الصرف.
     *
     * @return array<int, string>
     */
    private function currencyUsage(int $currencyId): array
    {
        $tables = [
            'exchange_rates',
            'suppliers',
            'supplier_products',
            'supplier_product_price_histories',
            'purchase_orders',
            'goods_receipts',
            'inventory_batches',
            'supplier_invoices',
            'supplier_payments',
            'purchase_returns',
            'stock_movements',
            'stock_transfer_items',
        ];

        $usedBy = [];

        foreach ($tables as $table) {
            if (
                ! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'currency_id')
            ) {
                continue;
            }

            if (
                DB::table($table)
                    ->where('currency_id', $currencyId)
                    ->exists()
            ) {
                $usedBy[] = $table;
            }
        }

        return $usedBy;
    }
}
