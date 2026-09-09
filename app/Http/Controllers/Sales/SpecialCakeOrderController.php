<?php

namespace App\Http\Controllers\Sales;

use App\Enums\CakeOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\CakeOrderComment;
use App\Models\Customer;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\SpecialCakeOrder;
use App\Services\SpecialCakes\SpecialCakeOrderService;
use App\Services\SpecialCakes\SpecialCakeStatusTransitionService;
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
            'creator',

            // مهم جداً حتى تجلب الصور مع الطلبات
            'attachments' => function ($query) {
                $query->latest('id');
            },
        ]);

    if (! $user->isAdmin() && ! $user->can('cake_orders.view_all')) {
        $locationIds = $user->employee?->locations()
            ->pluck('locations.id')
            ->map(fn ($id) => (int) $id)
            ->all() ?? [];

        $query->where(function ($q) use ($locationIds) {
            $q->whereIn('origin_branch_id', $locationIds)
                ->orWhereIn('factory_location_id', $locationIds);
        });
    }

    if ($request->filled('status')) {
        $query->where(
            'status',
            $request->input('status')
        );
    }

    if ($request->filled('date_from')) {
        $query->whereDate(
            'required_date',
            '>=',
            $request->input('date_from')
        );
    }

    $orders = $query
        ->latest()
        ->paginate(20)
        ->withQueryString();

    return view(
        'sales.cake-orders.index',
        compact('orders')
    );
}




public function create()
{
    $user = Auth::user();
    $branch = $user->primaryLocation();

    $customers = Customer::query()
        ->orderBy('name')
        ->get();

    $paymentMethods = PaymentMethod::query()
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('name_ar')
        ->get();

    return view('sales.cake-orders.create', compact(
        'customers',
        'branch',
        'paymentMethods'
    ));
}

    public function store(Request $request)
    {
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
        'required_unless:payment_arrangement,pay_on_pickup',
        'nullable',
        'exists:payment_methods,id',
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

        // Payment method required unless paying on pickup
        if ($request->payment_arrangement !== 'pay_on_pickup' && ! $request->payment_method_id) {
            return back()->withErrors(['payment_method_id' => 'طريقة الدفع مطلوبة.'])->withInput();
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

        return redirect()->route('cake-orders.index', $order)->with('success', 'تم إنشاء طلب الكيك بنجاح.');
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
        if ($cakeOrder->status !== CakeOrderStatus::Draft) {
            return back()->with('error', 'يمكن تعديل الطلبات في حالة المسودة فقط.');
        }
        return view('sales.cake-orders.edit', compact('cakeOrder'));
    }

    public function update(Request $request, SpecialCakeOrder $cakeOrder)
    {
        $this->authorize('update', $cakeOrder);
        if ($cakeOrder->status !== CakeOrderStatus::Draft) {
            return back()->with('error', 'يمكن تعديل الطلبات في حالة المسودة فقط.');
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
        $request->validate(['comment' => ['required', 'string', 'max:2000']]);

        CakeOrderComment::create([
            'special_cake_order_id' => $cakeOrder->id,
            'user_id'               => Auth::id(),
            'comment'               => $request->comment,
            'is_internal'           => true,
        ]);

        return back()->with('success', 'تم إضافة الملاحظة.');
    }

    public function addAttachment(Request $request, SpecialCakeOrder $cakeOrder)
    {
        $request->validate([
            'attachment_type' => ['required', 'in:reference_image,customer_design,final_cake_image,other'],
            'file'            => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $path = $request->file('file')->store('cake-attachments', 'public');

        $cakeOrder->attachments()->create([
            'attachment_type' => $request->attachment_type,
            'file_path'       => $path,
            'original_name'   => $request->file('file')->getClientOriginalName(),
            'uploaded_by'     => Auth::id(),
        ]);

        return back()->with('success', 'تم رفع المرفق بنجاح.');
    }
}
