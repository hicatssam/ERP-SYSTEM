<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Services\ActivityLogger;
use App\Services\Growth\CustomerGrowthAccessService;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoyaltyController extends Controller
{
    public function __construct(
        private readonly LoyaltyService $loyalty,
        private readonly CustomerGrowthAccessService $access
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        // Never expose loyalty accounts for customers outside the same customer
        // scope already enforced by the ERP / CRM layer.
        $visibleCustomers = $this->access
            ->applyCustomerScope(
                Customer::query()->select('customers.id'),
                $user,
                'crm.view_all'
            );

        $accounts = LoyaltyAccount::query()
            ->whereIn('customer_id', $visibleCustomers)
            ->with('customer:id,name,phone,location_id')
            ->orderByDesc('points_balance')
            ->paginate(25)
            ->withQueryString();

        return view('growth.loyalty.index', [
            'accounts' => $accounts,
            'program' => $this->loyalty->activeProgram(),
            'configuredPrograms' => LoyaltyProgram::query()->latest()->get(),
        ]);
    }

    public function saveProgram(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'points_per_currency_unit' => ['required', 'numeric', 'min:0', 'max:100000'],
            'minimum_order_amount' => ['required', 'numeric', 'min:0'],
            'redemption_value_per_point' => ['required', 'numeric', 'min:0'],
            'minimum_redeem_points' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        [$program, $previousActiveIds] = DB::transaction(
            function () use ($data): array {
                $previousActiveIds = LoyaltyProgram::query()
                    ->where('is_active', true)
                    ->pluck('id')
                    ->all();

                if ($data['is_active']) {
                    LoyaltyProgram::query()
                        ->where('is_active', true)
                        ->update(['is_active' => false]);
                }

                $program = LoyaltyProgram::query()->create($data);

                return [$program, $previousActiveIds];
            },
            3
        );

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'loyalty.program.created',
            module: 'loyalty',
            recordType: 'loyalty_programs',
            recordId: $program->id,
            oldValues: ['previous_active_program_ids' => $previousActiveIds],
            newValues: $program->only([
                'name',
                'points_per_currency_unit',
                'minimum_order_amount',
                'redemption_value_per_point',
                'minimum_redeem_points',
                'is_active',
                'starts_at',
                'ends_at',
            ]),
            metadata: null,
        );

        return back()->with('success', 'تم حفظ إعداد برنامج الولاء.');
    }

    public function adjust(Request $request, Customer $customer)
    {
        $this->access->ensureCustomerAccess(
            $customer,
            $request->user(),
            'crm.view_all'
        );

        $data = $request->validate([
            'points' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->loyalty->adjust(
            $customer,
            (int) $data['points'],
            $data['reason'],
            $request->user()
        );

        return back()->with('success', 'تم تعديل رصيد النقاط.');
    }

    public function redeem(Request $request, Customer $customer)
    {
        $this->access->ensureCustomerAccess(
            $customer,
            $request->user(),
            'crm.view_all'
        );

        $data = $request->validate([
            'points' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->loyalty->redeem(
            $customer,
            (int) $data['points'],
            $data['reason'],
            $request->user()
        );

        return back()->with(
            'success',
            'تم تسجيل استبدال النقاط. لا يتم تعديل الفاتورة أو إجمالي الطلب تلقائياً في Sprint 08.'
        );
    }
}
