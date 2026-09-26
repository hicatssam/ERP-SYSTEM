<?php

namespace App\Http\Controllers\Sales;

use App\Enums\CakeOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\CakeOrderComment;
use App\Models\Customer;
use App\Models\Location;
use App\Models\LocationPaymentAccount;
use App\Models\PaymentMethod;
use App\Models\SpecialCakeOrder;
use App\Notifications\CustomerCreatedNotification;
use App\Notifications\SpecialCakeOrderActivityNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\SpecialCakes\SpecialCakeOrderService;
use App\Services\SpecialCakes\SpecialCakeStatusTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SpecialCakeOrderController extends Controller
{
    public function __construct(
        private SpecialCakeOrderService $cakeOrderService,
        private SpecialCakeStatusTransitionService $transitionService
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();

        $query = SpecialCakeOrder::query()
            ->with([
                'customer',
                'originBranch',
                'factory',
                'creator',
                'attachments' => fn ($query) => $query->latest('id'),
            ]);

        if (! $user->isAdmin() && ! $user->can('cake_orders.view_all')) {
            $locationIds = $user->employee?->locations()
                ->pluck('locations.id')
                ->map(fn ($id) => (int) $id)
                ->all() ?? [];

            $query->where(function ($query) use ($locationIds): void {
                $query->whereIn('origin_branch_id', $locationIds)
                    ->orWhereIn('factory_location_id', $locationIds);
            });
        }

        $activeStatuses = collect(
            CakeOrderStatus::workflowCases()
        )
            ->reject(
                fn (CakeOrderStatus $status) =>
                    $status->isTerminal()
            )
            ->map(
                fn (CakeOrderStatus $status) =>
                    $status->value
            )
            ->values()
            ->all();

        $summaryQuery = clone $query;
        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'overdue' => (clone $summaryQuery)
                ->whereIn('status', $activeStatuses)
                ->whereDate('required_date', '<', today())
                ->count(),
            'due_today' => (clone $summaryQuery)
                ->whereIn('status', $activeStatuses)
                ->whereDate('required_date', today())
                ->count(),
            'due_soon' => (clone $summaryQuery)
                ->whereIn('status', $activeStatuses)
                ->whereDate('required_date', '>=', today()->addDay())
                ->whereDate('required_date', '<=', today()->addDays(3))
                ->count(),
        ];

        if ($request->filled('q')) {
            $search = trim($request->string('q')->toString());

            $query->where(function ($query) use ($search): void {
                $query->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                        $customerQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('date_from')) {
            $query->whereDate('required_date', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('required_date', '<=', $request->date('date_to'));
        }

        $orders = $query
            ->orderByRaw('required_date IS NULL')
            ->orderBy('required_date')
            ->orderBy('required_time')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('sales.cake-orders.index', compact('orders', 'summary'));
    }

    public function create()
    {
        $user = Auth::user();
        $branch = $user->primaryLocation();

        $customers = Customer::query()
            ->accessibleBy($user)
            ->orderBy('name')
            ->get();

        $paymentMethods = collect();
        $paymentAccounts = collect();

        if ($branch) {
            $paymentMethods = PaymentMethod::query()
                ->where('is_active', true)
                ->whereHas('locationPaymentMethods', function ($query) use ($branch): void {
                    $query
                        ->where('location_id', $branch->id)
                        ->where('is_active', true);
                })
                ->orderBy('sort_order')
                ->orderBy('name_ar')
                ->get();

            $paymentAccounts = LocationPaymentAccount::query()
                ->where('location_id', $branch->id)
                ->where('is_active', true)
                ->whereIn('payment_method_id', $paymentMethods->pluck('id'))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->groupBy('payment_method_id');
        }

        return view('sales.cake-orders.create', compact(
            'customers',
            'branch',
            'paymentMethods',
            'paymentAccounts'
        ));
    }

    public function quickStoreCustomer(Request $request): JsonResponse
    {
        $user = $request->user();
        $branch = $user->primaryLocation();

        if (! $branch || ! $branch->isBranch() || ! $branch->is_active) {
            abort(403, 'يجب ربط المستخدم بفرع رئيسي فعال قبل إضافة العميل.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('customers', 'phone')
                    ->where(fn ($query) => $query->where('location_id', $branch->id))
                    ->withoutTrashed(),
            ],
        ]);

        $customer = Customer::query()->create([
            'location_id' => $branch->id,
            'customer_type' => Customer::TYPE_INDIVIDUAL,
            'scope' => Customer::SCOPE_BRANCH,
            'name' => trim($validated['name']),
            'phone' => trim($validated['phone']),
            'allow_credit' => false,
            'credit_limit' => null,
            'billing_cycle' => 'immediate',
            'payment_terms_days' => 0,
        ]);

        NotificationDispatcher::notifyByPermissions(
            new CustomerCreatedNotification($customer),
            ['customers.view', 'customers.update', 'orders.create'],
            (int) $branch->id,
            ['customers.view_all', 'financial.global.view'],
            (int) $user->id,
        );

        return response()->json([
            'ok' => true,
            'message' => 'تمت إضافة العميل واختياره للطلب.',
            'customer' => [
                'id' => (int) $customer->id,
                'name' => (string) $customer->name,
                'phone' => (string) $customer->phone,
            ],
        ], 201);
    }

    public function store(Request $request)
    {
        /*
         * Normalize cash before validation as a server-side safeguard. The UI
         * also selects pay-on-pickup automatically, but the backend must remain
         * correct when JavaScript is unavailable or the request is tampered with.
         */
        $submittedMethod = PaymentMethod::query()
            ->whereKey((int) $request->input('payment_method_id'))
            ->where('is_active', true)
            ->first();

        if (
            $submittedMethod
            && (
                strtolower((string) $submittedMethod->type) === 'cash'
                || strtolower((string) $submittedMethod->code) === 'cash'
            )
        ) {
            $request->merge([
                'payment_arrangement' => 'pay_on_pickup',
                'location_payment_account_id' => null,
                'paid_amount' => null,
                'reference_number' => null,
            ]);
        }

      $request->validate([
    'customer_id' => [
        'required',
        'exists:customers,id',
    ],

    'required_date' => [
        'required',
        'date',
        'after:today',
    ],

    'required_time' => [
        'nullable',
        'date_format:H:i',
    ],

    'cake_type' => [
        'nullable',
        'string',
        'max:100',
    ],

    'cake_size' => [
        'nullable',
        'string',
        'max:50',
    ],

    'total_price' => [
        'required',
        'numeric',
        'min:0',
    ],

    'payment_arrangement' => [
        'required',
        'in:pay_now,deposit,partial_payment,pay_on_pickup,pending_verification',
    ],

    'payment_method_id' => [
        'required',
        'integer',
        'exists:payment_methods,id',
    ],

    'location_payment_account_id' => [
        'nullable',
        'integer',
        'exists:location_payment_accounts,id',
    ],

    'paid_amount' => [
        'required_if:payment_arrangement,pay_now,deposit,partial_payment',
        'nullable',
        'numeric',
        'min:0',
        'lte:total_price',
    ],

    'reference_number' => [
        'required_if:payment_arrangement,pending_verification',
        'nullable',
        'string',
        'max:100',
    ],

    'payment_proof' => [
        'required_if:payment_arrangement,pending_verification',
        'nullable',
        'file',
        'mimes:jpg,jpeg,png,webp',
        'max:10240',
    ],

    'image_cover_type' => [
        'nullable',
        'string',
        'in:none,edible_sugar,removable_cardboard',
    ],

    'discount_type' => [
        'nullable',
        'string',
        'in:none,percentage,fixed',
    ],

    'discount_value' => [
        'nullable',
        'numeric',
        'min:0',
    ],

    'reference_image' => [
    'nullable',
    'image',
    'mimes:jpg,jpeg,png,webp',
    'max:10240',
],

'print_image' => [
    'required_if:image_cover_type,edible_sugar',
    'nullable',
    'image',
    'mimes:jpg,jpeg,png,webp',
    'max:10240',
],
]);

        $branch = Auth::user()->primaryLocation();

        if (! $branch) {
            return back()
                ->withErrors(['location' => 'يجب ربط المستخدم بفرع رئيسي قبل إنشاء طلب الكيك.'])
                ->withInput();
        }

        $customerIsAvailable = Customer::query()
            ->accessibleBy(Auth::user())
            ->whereKey((int) $request->customer_id)
            ->exists();

        if (! $customerIsAvailable) {
            return back()
                ->withErrors(['customer_id' => 'العميل المحدد غير متاح في فرع المستخدم.'])
                ->withInput();
        }

        $paymentMethod = PaymentMethod::query()
            ->whereKey((int) $request->payment_method_id)
            ->where('is_active', true)
            ->whereHas('locationPaymentMethods', function ($query) use ($branch): void {
                $query
                    ->where('location_id', $branch->id)
                    ->where('is_active', true);
            })
            ->first();

        if (! $paymentMethod) {
            return back()
                ->withErrors(['payment_method_id' => 'طريقة الدفع المحددة غير مفعّلة في هذا الفرع.'])
                ->withInput();
        }

        $isCash = strtolower((string) $paymentMethod->type) === 'cash'
            || strtolower((string) $paymentMethod->code) === 'cash';

        if ($isCash) {
            /*
             * Cash is collected when the customer receives the cake. Do not
             * create a paid transaction or request proof/account information.
             */
            $request->merge([
                'payment_arrangement' => 'pay_on_pickup',
                'location_payment_account_id' => null,
                'paid_amount' => null,
                'reference_number' => null,
            ]);
        } elseif ($request->payment_arrangement !== 'pay_on_pickup') {
            $activeAccounts = LocationPaymentAccount::query()
                ->where('location_id', $branch->id)
                ->where('payment_method_id', $paymentMethod->id)
                ->where('is_active', true);

            if ($activeAccounts->exists()) {
                $paymentAccount = (clone $activeAccounts)
                    ->find((int) $request->location_payment_account_id);

                if (! $paymentAccount) {
                    return back()
                        ->withErrors(['location_payment_account_id' => 'اختر حساب الدفع الصحيح لهذا الفرع.'])
                        ->withInput();
                }
            } elseif ($request->filled('location_payment_account_id')) {
                return back()
                    ->withErrors(['location_payment_account_id' => 'حساب الدفع المحدد لا يتبع طريقة الدفع المختارة.'])
                    ->withInput();
            }
        }

        // Calculate discount
        $totalPrice    = (float) $request->total_price;
        $discountType  = $request->input('discount_type', 'none');
        $discountValue = (float) $request->input('discount_value', 0);
        $discountAmount = 0;

        if ($discountType === 'percentage') {
            $discountAmount = $totalPrice * $discountValue / 100;
        } elseif ($discountType === 'fixed') {
            $discountAmount = min($discountValue, $totalPrice);
        }

        $netPrice = max(0, $totalPrice - $discountAmount);

        $order = $this->cakeOrderService->createOrder(
    array_merge($request->all(), [
        'discount_type'   => $discountType,
        'discount_value'  => $discountValue,
        'discount_amount' => $discountAmount,
        'net_price'       => $netPrice,
        'payment_proof'   => $request->file('payment_proof'),
    ]),
    Auth::user()
);

        // Handle image attachments
     // Store the optional cake reference image
if ($request->hasFile('reference_image')) {
    $referenceImage = $request->file('reference_image');

    $referencePath = $referenceImage->store(
        'cake-attachments/reference-images',
        'public'
    );

    $order->attachments()->create([
        'attachment_type' => 'reference_image',
        'file_path'       => $referencePath,
        'original_name'   => $referenceImage->getClientOriginalName(),
        'uploaded_by'     => Auth::id(),
    ]);
}

// Store the sugar print image
if (
    $request->image_cover_type === 'edible_sugar' &&
    $request->hasFile('print_image')
) {
    $printImage = $request->file('print_image');

    $printImagePath = $printImage->store(
        'cake-attachments/print-images',
        'public'
    );

    $order->attachments()->create([
        'attachment_type' => 'customer_design',
        'file_path'       => $printImagePath,
        'original_name'   => $printImage->getClientOriginalName(),
        'uploaded_by'     => Auth::id(),
    ]);
}

        /*
         * A submitted cake-order form is a real request, not an abandoned draft.
         * Moving it to factory review also dispatches the operational alert to
         * eligible staff at the assigned factory and the administration.
         */
        $this->transitionService->transition(
            $order,
            CakeOrderStatus::Pending->value,
            Auth::user(),
            'تم إنشاء الطلب ووضعه قيد المراجعة.'
        );

        return redirect()
            ->route('cake-orders.show', $order)
            ->with('success', 'تم إنشاء طلب الكيك ووضعه قيد المراجعة.');
    }

    public function show(SpecialCakeOrder $cakeOrder)
    {
        $this->authorize('view', $cakeOrder);

        $cakeOrder->load([
            'customer', 'originBranch', 'factory', 'creator',
            'attachments', 'comments.user', 'statusHistories.changedBy',
            'receivingIssues', 'invoice', 'payments.paymentMethod', 'payments.receivedBy',
        ]);

        $allowedTransitions = $this->transitionService
            ->allowedTransitionsForUser($cakeOrder, Auth::user());

        return view('sales.cake-orders.show', compact(
            'cakeOrder',
            'allowedTransitions'
        ));
    }

    public function edit(SpecialCakeOrder $cakeOrder)
    {
        $this->authorize('update', $cakeOrder);
        if (! in_array(
            $cakeOrder->status,
            [
                CakeOrderStatus::Draft,
                CakeOrderStatus::Pending,
            ],
            true
        )) {
            return back()->with(
                'error',
                'يمكن تعديل الطلب ما دام قيد المراجعة فقط.'
            );
        }
        return view('sales.cake-orders.edit', compact('cakeOrder'));
    }

    public function update(Request $request, SpecialCakeOrder $cakeOrder)
    {
        $this->authorize('update', $cakeOrder);
        if (! in_array(
            $cakeOrder->status,
            [
                CakeOrderStatus::Draft,
                CakeOrderStatus::Pending,
            ],
            true
        )) {
            return back()->with(
                'error',
                'يمكن تعديل الطلب ما دام قيد المراجعة فقط.'
            );
        }

        $validated = $request->validate([
            'required_date' => ['required', 'date', 'after_or_equal:today'],
            'required_time' => ['nullable', 'date_format:H:i'],
            'cake_type' => ['nullable', 'string', 'max:100'],
            'cake_size' => ['nullable', 'string', 'max:50'],
            'cake_weight' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'persons_count' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'flavor' => ['nullable', 'string', 'max:255'],
            'filling' => ['nullable', 'string', 'max:255'],
            'shape' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:100'],
            'cake_text' => ['nullable', 'string', 'max:500'],
            'theme' => ['nullable', 'string', 'max:255'],
            'special_instructions' => ['nullable', 'string', 'max:5000'],
            'total_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $cakeOrder->update($validated);

        return redirect()->route('cake-orders.show', $cakeOrder)->with('success', 'تم تحديث الطلب.');
    }

    public function destroy(SpecialCakeOrder $cakeOrder)
    {
        $this->authorize('delete', $cakeOrder);

        if ($cakeOrder->status !== CakeOrderStatus::Draft) {
            return back()->with('error', 'يمكن حذف طلبات المسودة فقط.');
        }

        $cakeOrder->delete();

        return redirect()
            ->route('cake-orders.index')
            ->with('success', 'تم حذف الطلب.');
    }

    public function transition(Request $request, SpecialCakeOrder $cakeOrder)
    {
        $this->authorize('transition', $cakeOrder);

        $validated = $request->validate([
            'to_status' => [
                'required',
                Rule::enum(CakeOrderStatus::class),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->transitionService->transition(
            $cakeOrder,
            $validated['to_status'],
            Auth::user(),
            $validated['note'] ?? null
        );

        return back()->with('success', 'تم تحديث حالة الطلب بنجاح.');
    }

    public function addComment(Request $request, SpecialCakeOrder $cakeOrder)
    {
        $this->authorize('view', $cakeOrder);

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        CakeOrderComment::create([
            'special_cake_order_id' => $cakeOrder->id,
            'user_id'               => Auth::id(),
            'comment'               => $validated['comment'],
            'is_internal'           => true,
        ]);

        $actor = $request->user();

        NotificationDispatcher::notifyByPermissions(
            new SpecialCakeOrderActivityNotification(
                $cakeOrder->fresh(),
                'comment',
                $actor->display_name,
                \Illuminate\Support\Str::limit($validated['comment'], 180)
            ),
            ['cake_orders.view', 'cake_orders.manage'],
            array_filter([
                $cakeOrder->origin_branch_id,
                $cakeOrder->factory_location_id,
            ]),
            ['cake_orders.view_all'],
            $actor->isAdmin()
                ? null
                : (int) $actor->id,
        );

        return back()->with('success', 'تم إضافة الملاحظة.');
    }

    public function addAttachment(Request $request, SpecialCakeOrder $cakeOrder)
    {
        $this->authorize('view', $cakeOrder);

        $validated = $request->validate([
            'attachment_type' => ['required', 'in:reference_image,customer_design,final_cake_image,other'],
            'file'            => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $uploadedFile = $request->file('file');
        $path = $uploadedFile->store('cake-attachments', 'public');

        $cakeOrder->attachments()->create([
            'attachment_type' => $validated['attachment_type'],
            'file_path'       => $path,
            'original_name'   => $uploadedFile->getClientOriginalName(),
            'uploaded_by'     => Auth::id(),
        ]);

        $actor = $request->user();

        NotificationDispatcher::notifyByPermissions(
            new SpecialCakeOrderActivityNotification(
                $cakeOrder->fresh(),
                'attachment',
                $actor->display_name,
                'اسم الملف: ' . $uploadedFile->getClientOriginalName()
            ),
            ['cake_orders.view', 'cake_orders.manage'],
            array_filter([
                $cakeOrder->origin_branch_id,
                $cakeOrder->factory_location_id,
            ]),
            ['cake_orders.view_all'],
            $actor->isAdmin()
                ? null
                : (int) $actor->id,
        );

        return back()->with('success', 'تم رفع المرفق بنجاح.');
    }
}
