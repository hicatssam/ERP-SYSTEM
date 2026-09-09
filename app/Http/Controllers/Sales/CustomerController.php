<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Location;
use App\Models\User;
use App\Services\Customers\CustomerAccountService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Notifications\CustomerCreatedNotification;
use App\Services\Notifications\NotificationDispatcher;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerAccountService $accountService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $this->requirePermission($user, ['customers.view', 'customers.view_all', 'financial.global.view']);

        $canViewAll = $this->canViewAllCustomers($user);
        $primaryLocation = $canViewAll ? null : $this->managedBranch($user);
        $statsLocationId = $primaryLocation?->id;

        $query = Customer::query()
            ->with([
                'location:id,name',
                'locations:id,name',
            ]);

        if (! $canViewAll) {
            $query->accessibleBy($user);
        }

        if ($canViewAll && $request->filled('location_id')) {
            $location = Location::branches()
                ->active()
                ->find($request->integer('location_id'));

            abort_unless($location, 404, 'الفرع المحدد غير موجود أو غير فعال.');

            $statsLocationId = (int) $location->id;
            $query->availableAt($statsLocationId);
        }

        if ($statsLocationId !== null) {
            $query
                ->withCount([
                    'orders' => fn ($orders) => $orders->where('location_id', $statsLocationId),
                ])
                ->withSum([
                    'orders as total_spent' => fn ($orders) => $orders->where('location_id', $statsLocationId),
                ], 'total_amount')
                ->withSum([
                    'invoices as outstanding_balance' => fn ($invoices) => $invoices
                        ->where('location_id', $statsLocationId)
                        ->where('status', 'active'),
                ], 'remaining_amount');
        } else {
            $query
                ->withCount('orders')
                ->withSum('orders as total_spent', 'total_amount')
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
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('tax_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->string('customer_type')->toString());
        }

        if ($request->filled('scope')) {
            $query->where('scope', $request->string('scope')->toString());
        }

        $customers = $query
            ->orderByRaw("CASE WHEN customer_type = 'individual' THEN 1 ELSE 0 END")
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $locations = $canViewAll
            ? Location::branches()->active()->orderBy('name')->get(['id', 'name'])
            : collect();

        $isAdmin = $canViewAll;

        return view('sales.customers.index', compact(
            'customers',
            'locations',
            'isAdmin',
            'primaryLocation'
        ));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $this->requirePermission($user, ['customers.create']);

        $canViewAll = $this->canViewAllCustomers($user);
        $primaryLocation = $canViewAll ? null : $this->managedBranch($user);
        $locations = $canViewAll
            ? Location::branches()->active()->orderBy('name')->get(['id', 'name'])
            : collect();
        $isAdmin = $canViewAll;

        return view('sales.customers.create', compact(
            'locations',
            'isAdmin',
            'primaryLocation'
        ));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->requirePermission($user, ['customers.create']);

        $locationId = $this->resolveLocationId($request);
        $data = $this->validateCustomerData($request, $locationId);

        $data['location_id'] = $locationId;

        if (! $this->canViewAllCustomers($user)) {
            $data = array_merge($data, [
                'customer_type' => Customer::TYPE_INDIVIDUAL,
                'scope' => Customer::SCOPE_BRANCH,
                'allow_credit' => false,
                'credit_limit' => null,
                'billing_cycle' => 'immediate',
                'payment_terms_days' => 0,
                'tax_number' => null,
                'contact_person' => null,
                'address' => null,
            ]);
        }

        $customer = Customer::create($data);
        $this->syncCustomerLocations($customer, $request);

        NotificationDispatcher::notifyByPermissions(
            new CustomerCreatedNotification($customer),
            ['customers.view', 'customers.update', 'orders.create'],
            $locationId,
            ['customers.view_all', 'financial.global.view'],
            $user->id,
        );

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'تم إنشاء العميل بنجاح.');
    }

    public function show(Request $request, Customer $customer)
    {
        $user = $request->user();
        $this->requirePermission($user, ['customers.view', 'customers.view_all', 'financial.global.view']);
        $this->ensureCustomerAccess($customer, $user);

        $requestedLocationId = $request->filled('location_id')
            ? $request->integer('location_id')
            : null;

        $statementLocationId = $this->accountService->resolveLocationId(
            $customer,
            $user,
            $requestedLocationId
        );

        $accountSummary = $this->accountService->summary(
            $customer,
            $user,
            $statementLocationId
        );

        $customer->load([
            'location:id,name',
            'locations:id,name',
            'orders' => fn ($query) => $query
                ->when($statementLocationId !== null, fn ($q) => $q->where('location_id', $statementLocationId))
                ->latest()
                ->limit(8),
            'specialCakeOrders' => fn ($query) => $query
                ->when($statementLocationId !== null, fn ($q) => $q->where('origin_branch_id', $statementLocationId))
                ->latest()
                ->limit(5),
            'invoices' => fn ($query) => $query
                ->with('location:id,name')
                ->when($statementLocationId !== null, fn ($q) => $q->where('location_id', $statementLocationId))
                ->latest('issued_at')
                ->limit(8),
            'customerPayments' => fn ($query) => $query
                ->with(['location:id,name', 'paymentMethod:id,name,name_ar'])
                ->when($statementLocationId !== null, fn ($q) => $q->where('location_id', $statementLocationId))
                ->latest('paid_at')
                ->limit(8),
        ]);

        $locations = $this->accountService->canViewAllTransactions($user)
            ? Location::branches()->active()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('sales.customers.show', compact(
            'customer',
            'accountSummary',
            'statementLocationId',
            'locations'
        ));
    }



    public function statement(Request $request, Customer $customer)
    {
        $user = $request->user();

        $this->requirePermission(
            $user,
            ['customers.view', 'customers.view_all', 'financial.global.view']
        );

        $this->ensureCustomerAccess($customer, $user);

        /*
        |--------------------------------------------------------------------------
        | تحديد الفرع والفترة
        |--------------------------------------------------------------------------
        */

        $requestedLocationId = $request->filled('location_id')
            ? $request->integer('location_id')
            : null;

        $locationId = $this->accountService->resolveLocationId(
            $customer,
            $user,
            $requestedLocationId
        );

        $dateFrom = $request->filled('date_from')
            ? $request->date('date_from')?->startOfDay()
            : null;

        $dateTo = $request->filled('date_to')
            ? $request->date('date_to')?->endOfDay()
            : null;

        if ($dateFrom && $dateTo && $dateFrom->gt($dateTo)) {
            throw ValidationException::withMessages([
                'date_to' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية أو مساويًا له.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | الفروع المتاحة للفلترة
        |--------------------------------------------------------------------------
        */

        $locations = $this->accountService->canViewAllTransactions($user)
            ? Location::branches()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect([
                $this->managedBranch($user),
            ]);

        /*
        |--------------------------------------------------------------------------
        | فواتير العميل
        |--------------------------------------------------------------------------
        */

        $invoiceQuery = $customer
            ->invoices()
            ->with('location:id,name')
            ->when(
                $locationId !== null,
                fn ($query) => $query->where('location_id', $locationId)
            )
            ->when(
                $dateFrom,
                fn ($query) => $query->where('issued_at', '>=', $dateFrom)
            )
            ->when(
                $dateTo,
                fn ($query) => $query->where('issued_at', '<=', $dateTo)
            );

        $invoices = $invoiceQuery
            ->latest('issued_at')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | الطلبات العادية
        |--------------------------------------------------------------------------
        */

        $orderQuery = $customer
            ->orders()
            ->with('location:id,name')
            ->when(
                $locationId !== null,
                fn ($query) => $query->where('location_id', $locationId)
            )
            ->when(
                $dateFrom,
                fn ($query) => $query->where('created_at', '>=', $dateFrom)
            )
            ->when(
                $dateTo,
                fn ($query) => $query->where('created_at', '<=', $dateTo)
            );

        $orders = $orderQuery
            ->latest()
            ->get();

        /*
        |--------------------------------------------------------------------------
        | طلبات الكيك الخاصة
        |--------------------------------------------------------------------------
        */

        $cakeQuery = $customer
            ->specialCakeOrders()
            ->with('originBranch:id,name')
            ->when(
                $locationId !== null,
                fn ($query) => $query->where('origin_branch_id', $locationId)
            )
            ->when(
                $dateFrom,
                fn ($query) => $query->where('created_at', '>=', $dateFrom)
            )
            ->when(
                $dateTo,
                fn ($query) => $query->where('created_at', '<=', $dateTo)
            );

        $cakeOrders = $cakeQuery
            ->latest()
            ->get();

        /*
        |--------------------------------------------------------------------------
        | دفعات العميل
        |--------------------------------------------------------------------------
        */

        $paymentQuery = $customer
            ->customerPayments()
            ->with([
                'location:id,name',
                'paymentMethod:id,name,name_ar',
            ])
            ->when(
                $locationId !== null,
                fn ($query) => $query->where('location_id', $locationId)
            )
            ->when(
                $dateFrom,
                fn ($query) => $query->where('paid_at', '>=', $dateFrom)
            )
            ->when(
                $dateTo,
                fn ($query) => $query->where('paid_at', '<=', $dateTo)
            );

        $customerPayments = $paymentQuery
            ->latest('paid_at')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | الملخص المالي
        |--------------------------------------------------------------------------
        */

        $accountSummary = $this->accountService->summary(
            $customer,
            $user,
            $locationId
        );

        $totalInvoices = (float) $invoices->sum('total_amount');
        $totalPaid = (float) $invoices->sum('paid_amount');
        $totalOutstanding = (float) $invoices->sum('remaining_amount');

        /*
        |--------------------------------------------------------------------------
        | بيانات كشف الحساب المستخدمة في Blade
        |--------------------------------------------------------------------------
        |
        | صفحة statement.blade.php الحالية تعتمد على $statement و $locations.
        | لذلك نرسلها صراحة مع الاحتفاظ بباقي المتغيرات للتوافق.
        |
        */

        $statement = [
            'location_id' => $locationId,
            'date_from' => $dateFrom?->toDateString(),
            'date_to' => $dateTo?->toDateString(),

            'total_invoices' => $totalInvoices,
            'total_paid' => $totalPaid,
            'total_outstanding' => $totalOutstanding,

            'invoices_count' => $invoices->count(),
            'orders_count' => $orders->count(),
            'cake_orders_count' => $cakeOrders->count(),
            'payments_count' => $customerPayments->count(),

            'invoices' => $invoices,
            'orders' => $orders,
            'cake_orders' => $cakeOrders,
            'payments' => $customerPayments,

            'summary' => $accountSummary,
        ];

        return view(
            'sales.customers.statement',
            compact(
                'customer',
                'statement',
                'locations',
                'accountSummary',
                'invoices',
                'orders',
                'cakeOrders',
                'customerPayments',
                'totalInvoices',
                'totalPaid',
                'totalOutstanding'
            )
        );
    }

    public function edit(Request $request, Customer $customer)
    {
        $user = $request->user();
        $this->requirePermission($user, ['customers.update']);
        $this->ensureCustomerAccess($customer, $user);
        $this->ensureCustomerCanBeManaged($customer, $user);

        $canViewAll = $this->canViewAllCustomers($user);
        $primaryLocation = $canViewAll ? null : $this->managedBranch($user);

        $customer->load([
            'location:id,name',
            'locations:id,name',
        ]);

        $locations = $canViewAll
            ? Location::branches()->active()->orderBy('name')->get(['id', 'name'])
            : collect();
        $isAdmin = $canViewAll;

        return view('sales.customers.edit', compact(
            'customer',
            'locations',
            'isAdmin',
            'primaryLocation'
        ));
    }

    public function update(Request $request, Customer $customer)
    {
        $user = $request->user();
        $this->requirePermission($user, ['customers.update']);
        $this->ensureCustomerAccess($customer, $user);
        $this->ensureCustomerCanBeManaged($customer, $user);

        $locationId = $this->resolveLocationId($request);
        $data = $this->validateCustomerData($request, $locationId, $customer);
        $data['location_id'] = $locationId;

        if (! $this->canViewAllCustomers($user)) {
            $data['customer_type'] = Customer::TYPE_INDIVIDUAL;
            $data['scope'] = Customer::SCOPE_BRANCH;
        }

        $this->ensureScopeChangeIsSafe(
            $customer,
            $data['scope'] ?? Customer::SCOPE_BRANCH,
            $locationId
        );

        $customer->update($data);
        $this->syncCustomerLocations($customer, $request);

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'تم تحديث بيانات العميل.');
    }

    public function destroy(Request $request, Customer $customer)
    {
        $user = $request->user();
        $this->requirePermission($user, ['customers.delete']);
        $this->ensureCustomerAccess($customer, $user);

        return back()->with('error', 'لا يمكن حذف سجل العميل.');
    }

    private function resolveLocationId(Request $request): int
    {
        $user = $request->user();

        if (! $this->canViewAllCustomers($user)) {
            return (int) $this->managedBranch($user)->id;
        }

        $location = Location::branches()
            ->active()
            ->find($request->integer('location_id'));

        if (! $location) {
            throw ValidationException::withMessages([
                'location_id' => 'يجب اختيار فرع مرجعي صحيح وفعال.',
            ]);
        }

        return (int) $location->id;
    }

    private function validateCustomerData(
        Request $request,
        int $locationId,
        ?Customer $customer = null
    ): array {
        $canManageScope = $this->canViewAllCustomers($request->user());

        $phoneRule = Rule::unique('customers', 'phone')
            ->where(fn ($query) => $query->where('location_id', $locationId));

        if ($customer) {
            $phoneRule->ignore($customer->id);
        }

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20', $phoneRule],
            'secondary_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ];

        if ($canManageScope) {
            $rules = array_merge($rules, [
                'customer_type' => [
                    'required',
                    Rule::in([
                        Customer::TYPE_INDIVIDUAL,
                        Customer::TYPE_INSTITUTION,
                        Customer::TYPE_COMPANY,
                        Customer::TYPE_GOVERNMENT,
                    ]),
                ],
                'scope' => [
                    'required',
                    Rule::in([
                        Customer::SCOPE_BRANCH,
                        Customer::SCOPE_SELECTED,
                        Customer::SCOPE_GLOBAL,
                    ]),
                ],
                'location_ids' => ['nullable', 'array'],
                'location_ids.*' => [
                    'integer',
                    'distinct',
                    Rule::exists('locations', 'id')->where('is_active', true),
                ],
                'allow_credit' => ['nullable', 'boolean'],
                'credit_limit' => ['nullable', 'numeric', 'min:0'],
                'billing_cycle' => ['required', Rule::in(['immediate', 'weekly', 'monthly'])],
                'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
                'tax_number' => ['nullable', 'string', 'max:100'],
                'contact_person' => ['nullable', 'string', 'max:150'],
                'address' => ['nullable', 'string', 'max:500'],
            ]);
        }

        $data = $request->validate($rules);

        if ($canManageScope) {
            $data['allow_credit'] = $request->boolean('allow_credit');

            if (($data['scope'] ?? Customer::SCOPE_BRANCH) !== Customer::SCOPE_BRANCH
                && ($data['customer_type'] ?? Customer::TYPE_INDIVIDUAL) === Customer::TYPE_INDIVIDUAL) {
                throw ValidationException::withMessages([
                    'customer_type' => 'العميل المتاح لأكثر من فرع يجب أن يكون مؤسسة أو شركة أو جهة.',
                ]);
            }

            if (($data['scope'] ?? null) === Customer::SCOPE_SELECTED
                && empty($data['location_ids'] ?? [])) {
                throw ValidationException::withMessages([
                    'location_ids' => 'اختر فرعًا واحدًا على الأقل للعميل متعدد الفروع.',
                ]);
            }

            if (! $data['allow_credit']) {
                $data['credit_limit'] = null;
                $data['billing_cycle'] = 'immediate';
                $data['payment_terms_days'] = 0;
            }
        }

        unset($data['location_ids']);

        return $data;
    }

    private function syncCustomerLocations(Customer $customer, Request $request): void
    {
        if (! $this->canViewAllCustomers($request->user())) {
            $customer->locations()->detach();
            return;
        }

        if ($customer->scope !== Customer::SCOPE_SELECTED) {
            $customer->locations()->detach();
            return;
        }

        $locationIds = array_values(array_unique(array_map(
            'intval',
            array_merge(
                [$customer->location_id],
                (array) $request->input('location_ids', [])
            )
        )));

        $validIds = Location::branches()
            ->active()
            ->whereIn('id', $locationIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $sync = [];
        foreach ($validIds as $id) {
            $sync[$id] = ['is_active' => true];
        }

        $customer->locations()->sync($sync);
    }

    private function ensureCustomerAccess(Customer $customer, User $user): void
    {
        abort_unless(
            $customer->canBeAccessedBy($user),
            403,
            'لا يمكنك الوصول إلى هذا العميل.'
        );
    }

    private function ensureCustomerCanBeManaged(Customer $customer, User $user): void
    {
        if (! $customer->isCentral()) {
            return;
        }

        abort_unless(
            $this->canViewAllCustomers($user),
            403,
            'بيانات العميل المركزي تُدار من المستخدمين المخولين لجميع الفروع فقط.'
        );
    }

    private function ensureScopeChangeIsSafe(
        Customer $customer,
        string $newScope,
        int $homeLocationId
    ): void {
        if ($newScope !== Customer::SCOPE_BRANCH) {
            return;
        }

        $hasOtherBranchHistory = $customer->orders()
            ->where('location_id', '!=', $homeLocationId)
            ->exists()
            || $customer->invoices()
                ->where('location_id', '!=', $homeLocationId)
                ->exists()
            || $customer->specialCakeOrders()
                ->where('origin_branch_id', '!=', $homeLocationId)
                ->exists();

        if ($hasOtherBranchHistory) {
            throw ValidationException::withMessages([
                'scope' => 'لا يمكن تحويل العميل إلى فرع واحد لوجود تعاملات تاريخية في فروع أخرى.',
            ]);
        }
    }

    private function canViewAllCustomers(User $user): bool
    {
        return $user->isAdmin() || $user->can('customers.view_all') || $user->can('financial.global.view');
    }

    private function managedBranch(User $user): Location
    {
        $location = $user->primaryLocation();

        $validBranch = $location
            && $location->isBranch()
            && Location::branches()->active()->whereKey($location->id)->exists();

        abort_unless(
            $validBranch,
            403,
            'لا يوجد فرع رئيسي فعال مرتبط بحسابك.'
        );

        return $location;
    }

    /** @param array<int,string> $permissions */
    private function requirePermission(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return;
            }
        }

        abort(403, 'ليس لديك صلاحية لتنفيذ هذه العملية.');
    }


    
}
