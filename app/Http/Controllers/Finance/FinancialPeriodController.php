<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CloseFinancialPeriodRequest;
use App\Http\Requests\Finance\StoreFinancialPeriodRequest;
use App\Models\FinancialPeriod;
use App\Services\Finance\FinancialPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialPeriodController extends Controller
{
    public function __construct(
        private FinancialPeriodService $periodService
    ) {
    }

    public function index(): View
    {
        $periods = FinancialPeriod::query()
            ->with(['openedBy', 'closedBy'])
            ->latest('start_date')
            ->paginate(20);

        return view('finance.periods.index', compact('periods'));
    }

    /**
     * لم تعد هناك شاشة إنشاء مستقلة.
     * أبقينا الـ method للتوافق مع أي رابط قديم.
     */
    public function create(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()?->can('financial.periods.open'),
            403
        );

        return redirect()->route('financial-periods.index');
    }

    public function store(
        StoreFinancialPeriodRequest $request
    ): RedirectResponse {
        $period = $this->periodService->openPeriod(
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('financial-periods.index')
            ->with(
                'success',
                "تم فتح الفترة المالية {$period->year}/{$period->month} بنجاح."
            );
    }

    public function show(
        Request $request,
        FinancialPeriod $financialPeriod
    ): View {
        abort_unless(
            $request->user()?->can('financial.periods.view'),
            403
        );

        $financialPeriod->load([
            'openedBy',
            'closedBy',
            'summaries.location',
            'adjustments',
        ]);

        /*
         * الـ Seeder الحالي ينشئ فترات مغلقة بدون summaries،
         * والفترة المفتوحة لا تُنشئ snapshot إلا عند الإغلاق.
         *
         * لذلك:
         * - إذا كان هناك snapshot محفوظ نعرضه.
         * - إذا لم يوجد، نحسب Preview حي بدون كتابة أي شيء في DB.
         */
        $hasPersistedSummary = $financialPeriod->summaries->isNotEmpty();

        $summaryRows = $hasPersistedSummary
            ? $financialPeriod->summaries
            : $this->periodService->previewSummaries($financialPeriod);

        $isLivePreview = ! $hasPersistedSummary;

        return view(
            'finance.periods.show',
            compact(
                'financialPeriod',
                'summaryRows',
                'isLivePreview'
            )
        );
    }

    public function edit(
        Request $request,
        FinancialPeriod $financialPeriod
    ): RedirectResponse {
        abort_unless(
            $request->user()?->can('financial.periods.view'),
            403
        );

        return redirect()
            ->route('financial-periods.show', $financialPeriod)
            ->with('info', 'إدارة الفترة تتم من خلال أزرار الفتح والإغلاق.');
    }

    public function update(
        Request $request,
        FinancialPeriod $financialPeriod
    ): RedirectResponse {
        abort_unless(
            $request->user()?->can('financial.periods.view'),
            403
        );

        return redirect()
            ->route('financial-periods.show', $financialPeriod)
            ->with('info', 'إدارة الفترة تتم من خلال أزرار الفتح والإغلاق.');
    }

    public function destroy(
        Request $request,
        FinancialPeriod $financialPeriod
    ): RedirectResponse {
        abort_unless(
            $request->user()?->can('financial.periods.view'),
            403
        );

        return back()->with(
            'error',
            'لا يمكن حذف الفترات المالية حفاظاً على السجل المالي.'
        );
    }

    public function open(
        Request $request,
        FinancialPeriod $financialPeriod
    ): RedirectResponse {
        abort_unless(
            $request->user()?->can('financial.periods.open'),
            403
        );

        $this->periodService->reopenPeriod(
            $financialPeriod,
            $request->user()
        );

        return back()->with(
            'success',
            'تم إعادة فتح الفترة المالية بنجاح.'
        );
    }

    public function close(
        CloseFinancialPeriodRequest $request,
        FinancialPeriod $financialPeriod
    ): RedirectResponse {
        $this->periodService->closePeriod(
            $financialPeriod,
            $request->user(),
            $request->validated('notes')
        );

        return back()->with(
            'success',
            'تم إغلاق الفترة المالية وتوليد ملخص الفروع بنجاح.'
        );
    }
}
