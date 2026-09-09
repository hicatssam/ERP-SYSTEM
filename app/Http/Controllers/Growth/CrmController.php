<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Models\CrmTag;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerInteraction;
use App\Models\LoyaltyAccount;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Growth\CustomerGrowthAccessService;
use App\Services\ModuleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrmController extends Controller
{
    public function __construct(
        private readonly CustomerGrowthAccessService $access,
        private readonly ModuleService $modules
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $canViewAll = $this->access->canViewAllCustomers($user, 'crm.view_all');
        $statsLocationId = $this->access->customerStatsLocationId($user, 'crm.view_all');

        $query = $this->access
            ->applyCustomerScope(Customer::query(), $user, 'crm.view_all')
            ->with('location:id,name');

        if ($canViewAll && $request->filled('location_id')) {
            $locationId = $request->integer('location_id');
            $this->access->ensureLocationAccess($locationId, $user, 'crm.view_all');
            $statsLocationId = $locationId;

            // Current Customer architecture exposes availableAt() for global,
            // selected-branch and branch-scoped customers.
            if (method_exists(Customer::class, 'scopeAvailableAt')) {
                $query->availableAt($locationId);
            } else {
                $query->where('location_id', $locationId);
            }
        }

        if ($statsLocationId !== null) {
            $query
                ->withCount([
                    'orders' => fn ($orders) => $orders
                        ->where('location_id', $statsLocationId),
                ])
                ->withSum([
                    'orders as total_spent' => fn ($orders) => $orders
                        ->where('location_id', $statsLocationId)
                        ->where('status', '!=', 'cancelled'),
                ], 'total_amount')
                ->withSum([
                    'invoices as outstanding_balance' => fn ($invoices) => $invoices
                        ->where('location_id', $statsLocationId)
                        ->where('status', 'active'),
                ], 'remaining_amount');
        } else {
            $query
                ->withCount('orders')
                ->withSum([
                    'orders as total_spent' => fn ($orders) => $orders
                        ->where('status', '!=', 'cancelled'),
                ], 'total_amount')
                ->withSum([
                    'invoices as outstanding_balance' => fn ($invoices) => $invoices
                        ->where('status', 'active'),
                ], 'remaining_amount');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($customerQuery) use ($search): void {
                $customerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('secondary_phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('crm_status')) {
            $query->where(
                'crm_status',
                $request->string('crm_status')->toString()
            );
        }

        if ($request->filled('tag_id')) {
            $customerIds = \DB::table('crm_customer_tag')
                ->where('crm_tag_id', $request->integer('tag_id'))
                ->select('customer_id');

            $query->whereIn('customers.id', $customerIds);
        }

        $customers = $query
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $locations = $this->access->selectableLocations($user, 'crm.view_all');
        $tags = CrmTag::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view(
            'growth.crm.index',
            compact('customers', 'locations', 'tags')
        );
    }

    public function show(Request $request, Customer $customer)
    {
        $user = $request->user();
        $this->access->ensureCustomerAccess($customer, $user, 'crm.view_all');

        $canViewAll = $this->access->canViewAllCustomers($user, 'crm.view_all');
        $locationId = $this->access->customerStatsLocationId($user, 'crm.view_all');

        $customer->load(['location:id,name']);

        $ordersQuery = $customer->orders();
        $invoicesQuery = $customer->invoices();
        $interactionsQuery = CustomerInteraction::query()
            ->where('customer_id', $customer->id);

        if (! $canViewAll && $locationId !== null) {
            $ordersQuery->where('location_id', $locationId);
            $invoicesQuery->where('location_id', $locationId);
            $interactionsQuery->where('location_id', $locationId);
        }

        $orders = (clone $ordersQuery)
            ->latest()
            ->limit(12)
            ->get();

        $invoices = (clone $invoicesQuery)
            ->latest('issued_at')
            ->limit(12)
            ->get();

        $addresses = CustomerAddress::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get();

        $interactions = $interactionsQuery
            ->with(['user.employee', 'location'])
            ->latest()
            ->limit(30)
            ->get();

        $tagIds = \DB::table('crm_customer_tag')
            ->where('customer_id', $customer->id)
            ->pluck('crm_tag_id');

        $selectedTags = CrmTag::query()
            ->whereIn('id', $tagIds)
            ->get();

        $allTags = CrmTag::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $loyaltyEnabled = $this->modules->isEnabled('loyalty');
        $loyalty = null;

        if ($loyaltyEnabled) {
            $loyalty = LoyaltyAccount::query()
                ->where('customer_id', $customer->id)
                ->with([
                    'transactions' => function ($transactions) use ($canViewAll, $locationId): void {
                        if (! $canViewAll && $locationId !== null) {
                            $transactions->where(function ($query) use ($locationId): void {
                                $query
                                    ->whereNull('order_id')
                                    ->orWhereHas(
                                        'order',
                                        fn ($order) => $order->where('location_id', $locationId)
                                    );
                            });
                        }

                        $transactions->latest()->limit(20);
                    },
                ])
                ->first();
        }

        $stats = [
            'orders' => (clone $ordersQuery)->count(),
            'sales' => (float) (clone $ordersQuery)
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount'),
            'outstanding' => (float) (clone $invoicesQuery)
                ->where('status', 'active')
                ->sum('remaining_amount'),
        ];

        $crmOwners = collect();

        if ($user->can('crm.manage')) {
            $ownerQuery = User::query()
                ->where('is_active', true)
                ->with('employee');

            if (! $canViewAll && $locationId !== null) {
                $currentOwnerId = (int) ($customer->crm_owner_id ?? 0);

                $ownerQuery->where(function ($query) use ($locationId, $currentOwnerId): void {
                    $query->whereHas(
                        'employee.locations',
                        fn ($locations) => $locations->where('locations.id', $locationId)
                    );

                    if ($currentOwnerId > 0) {
                        $query->orWhereKey($currentOwnerId);
                    }
                });
            }

            $crmOwners = $ownerQuery
                ->orderBy('username')
                ->get();
        }

        $crmOwner = $customer->crm_owner_id
            ? User::query()->with('employee')->find($customer->crm_owner_id)
            : null;

        return view('growth.crm.show', compact(
            'customer',
            'orders',
            'invoices',
            'addresses',
            'interactions',
            'selectedTags',
            'allTags',
            'loyalty',
            'stats',
            'crmOwners',
            'crmOwner',
            'loyaltyEnabled'
        ));
    }

    public function updateProfile(Request $request, Customer $customer)
    {
        $user = $request->user();
        $this->access->ensureCustomerAccess($customer, $user, 'crm.view_all');

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'birthday' => ['nullable', 'date'],
            'preferred_contact_channel' => [
                'nullable',
                Rule::in(['phone', 'whatsapp', 'email', 'sms']),
            ],
            'marketing_opt_in' => ['nullable', 'boolean'],
            'crm_status' => [
                'required',
                Rule::in(['lead', 'active', 'vip', 'inactive']),
            ],
            'crm_owner_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],
        ]);

        if (! empty($data['crm_owner_id'])) {
            $owner = User::query()->findOrFail($data['crm_owner_id']);

            if (! $this->access->canViewAllCustomers($user, 'crm.view_all')) {
                $locationId = $user->primaryLocation()?->id;
                $owner->loadMissing('employee.locations');

                abort_unless(
                    $locationId
                    && ($owner->employee?->locations?->contains('id', (int) $locationId) ?? false),
                    422,
                    'مسؤول CRM المحدد غير مرتبط بفرعك.'
                );
            }
        }

        $trackedKeys = [
            'email',
            'birthday',
            'preferred_contact_channel',
            'marketing_opt_in',
            'marketing_opt_in_at',
            'crm_status',
            'crm_owner_id',
        ];

        $old = $customer->only($trackedKeys);
        $optedIn = $request->boolean('marketing_opt_in');

        $data['marketing_opt_in'] = $optedIn;
        $data['marketing_opt_in_at'] = $optedIn
            ? ($customer->marketing_opt_in_at ?: now())
            : null;

        // Query builder update avoids depending on the current Customer::$fillable
        // list, which predates Sprint 08 in some installations.
        Customer::query()
            ->whereKey($customer->id)
            ->update($data);

        $customer->refresh();

        ActivityLogger::log(
            userId: $user->id,
            action: 'crm.customer_profile.updated',
            module: 'crm',
            recordType: 'customers',
            recordId: $customer->id,
            oldValues: $old,
            newValues: $customer->only($trackedKeys),
            metadata: null,
        );

        return back()->with('success', 'تم تحديث ملف CRM للعميل.');
    }
}
