<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\LocationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ReviewStockRequestRequest;
use App\Http\Requests\Inventory\StoreStockRequestRequest;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\ActivityLogger;
use App\Services\Inventory\StockRequestNumberService;
use App\Services\Inventory\StockTransferNumberService;
use App\Notifications\StockRequestCreatedNotification;
use App\Notifications\StockRequestStatusChangedNotification;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockRequestController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $this->authorize(
            'viewAny',
            StockRequest::class
        );

        $query = StockRequest::with([
            'branch',
            'factory',
            'creator',
        ]);

        if (! $user->isAdmin()) {
            $locationId =
                $user->primaryLocation()?->id;

            $query->where(
                function ($q) use ($locationId) {
                    $q
                        ->where(
                            'branch_location_id',
                            $locationId
                        )
                        ->orWhere(
                            'factory_location_id',
                            $locationId
                        );
                }
            );
        }

        $requests =
            $query
                ->latest()
                ->paginate(20);

        return view(
            'inventory.stock-requests.index',
            compact('requests')
        );
    }

    public function create()
    {
        $this->authorize(
            'create',
            StockRequest::class
        );

        $factory =
            Location::query()
                ->where(
                    'type',
                    LocationType::Factory->value
                )
                ->active()
                ->firstOrFail();

        $products =
            Product::active()
                ->with('category')
                ->orderBy('name')
                ->get();

        return view(
            'inventory.stock-requests.create',
            compact(
                'factory',
                'products'
            )
        );
    }

    public function store(
        StoreStockRequestRequest $request
    ) {
        $this->authorize(
            'create',
            StockRequest::class
        );

        $user =
            $request->user();

        $branch =
            $user->isAdmin()
                ? Location::find(
                    $request->branch_id
                )
                : $user->primaryLocation();

        $factory =
            Location::query()
                ->where(
                    'type',
                    LocationType::Factory->value
                )
                ->active()
                ->firstOrFail();

        if (! $branch || ! $factory) {
            return back()
                ->with(
                    'error',
                    'لا يمكن إنشاء الطلب. تحقق من الموقع.'
                );
        }

        $validated =
            $request->validated();

        $stockRequest =
            DB::transaction(
                function () use (
                    $user,
                    $branch,
                    $factory,
                    $validated
                ) {
                    $stockRequest =
                        StockRequest::create([
                            'request_number' =>
                                StockRequestNumberService::generate(),

                            'branch_location_id' =>
                                $branch->id,

                            'factory_location_id' =>
                                $factory->id,

                            'status' =>
                                'pending_factory_review',

                            'notes' =>
                                $validated['notes']
                                ?? null,

                            'created_by' =>
                                $user->id,
                        ]);

                    foreach (
                        $validated['items']
                        as $item
                    ) {
                        StockRequestItem::create([
                            'stock_request_id' =>
                                $stockRequest->id,

                            'product_id' =>
                                $item['product_id'],

                            'requested_quantity' =>
                                $item['quantity'],
                        ]);
                    }

                    return $stockRequest;
                }
            );

        ActivityLogger::log(
            userId: $user->id,
            action: 'stock_request.created',
            module: 'inventory',
            recordType: 'stock_requests',
            recordId: $stockRequest->id,
            oldValues: null,
            newValues: $stockRequest->only([
                'request_number',
                'status',
                'branch_location_id',
                'factory_location_id',
            ]),
            metadata: [
                'items_count' =>
                    count(
                        $validated['items']
                    ),
            ],
        );

        NotificationDispatcher::notifyByPermissions(
            new StockRequestCreatedNotification($stockRequest),
            ['stock_requests.view', 'stock_requests.review'],
            $factory->id,
            ['inventory.view_all_locations'],
            $user->id,
        );

        return redirect()
            ->route(
                'stock-requests.show',
                $stockRequest
            )
            ->with(
                'success',
                'تم إرسال طلب المخزون بنجاح.'
            );
    }

    public function show(
        StockRequest $stockRequest
    ) {
        $this->authorize(
            'view',
            $stockRequest
        );

        $stockRequest->load([
            'branch',
            'factory',
            'creator',
            'reviewer',
            'items.product',
        ]);

        return view(
            'inventory.stock-requests.show',
            compact('stockRequest')
        );
    }

    public function edit(
        StockRequest $stockRequest
    ) {
        $this->authorize(
            'update',
            $stockRequest
        );

        return view(
            'inventory.stock-requests.show',
            compact('stockRequest')
        );
    }

    public function update(
        Request $request,
        StockRequest $stockRequest
    ) {
        $this->authorize(
            'update',
            $stockRequest
        );

        return back()->with(
            'info',
            'استخدم نماذج الإجراءات المتاحة.'
        );
    }

    public function destroy(
        StockRequest $stockRequest
    ) {
        $this->authorize(
            'delete',
            $stockRequest
        );

        return back()->with(
            'error',
            'لا يمكن حذف طلبات المخزون.'
        );
    }

    public function submit(
        Request $request,
        StockRequest $stockRequest
    ) {
        $this->authorize(
            'submit',
            $stockRequest
        );

        $oldStatus =
            $stockRequest->status?->value
            ?? $stockRequest->status;

        $stockRequest->update([
            'status' =>
                'pending_factory_review',
        ]);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'stock_request.submitted',
            module: 'inventory',
            recordType: 'stock_requests',
            recordId: $stockRequest->id,
            oldValues: [
                'status' =>
                    $oldStatus,
            ],
            newValues: [
                'status' =>
                    'pending_factory_review',
            ],
        );

        NotificationDispatcher::notifyByPermissions(
            new StockRequestStatusChangedNotification($stockRequest, 'pending_factory_review'),
            ['stock_requests.view', 'stock_requests.review'],
            $stockRequest->factory_location_id,
            ['inventory.view_all_locations'],
            $request->user()->id,
        );

        return back()->with(
            'success',
            'تم إرسال الطلب إلى المصنع.'
        );
    }

    public function review(
        ReviewStockRequestRequest $request,
        StockRequest $stockRequest
    ) {
        $this->authorize(
            'review',
            $stockRequest
        );

        $validated =
            $request->validated();

        $action =
            $validated['action'];

        $result =
            DB::transaction(
                function () use (
                    $stockRequest,
                    $validated,
                    $action
                ) {
                    /*
                     * قفل سجل الطلب يمنع قبول نفس الطلب مرتين
                     * من مستخدمين في نفس اللحظة.
                     */
                    $lockedRequest =
                        StockRequest::query()
                            ->whereKey(
                                $stockRequest->id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $currentStatus =
                        $lockedRequest
                            ->status?->value
                        ?? $lockedRequest
                            ->status;

                    if (
                        $currentStatus
                        !==
                        'pending_factory_review'
                    ) {
                        throw ValidationException::withMessages([
                            'action' =>
                                'تمت مراجعة هذا الطلب مسبقًا أو تغيرت حالته.',
                        ]);
                    }

                    $lockedRequest->load(
                        'items'
                    );

                    $newStatus =
                        match ($action) {
                            'accept' =>
                                'accepted',

                            'partially_accept' =>
                                'partially_accepted',

                            'reject' =>
                                'rejected',

                            default =>
                                throw ValidationException::withMessages([
                                    'action' =>
                                        'إجراء المراجعة غير صالح.',
                                ]),
                        };

                    /*
                     * قبول كامل:
                     * الكمية المعتمدة = الكمية المطلوبة.
                     */
                    if ($action === 'accept') {
                        foreach (
                            $lockedRequest->items
                            as $item
                        ) {
                            $item->update([
                                'approved_quantity' =>
                                    $item->requested_quantity,
                            ]);
                        }
                    }

                    /*
                     * قبول جزئي:
                     * يجب أن تكون كل كمية بين صفر والكمية المطلوبة.
                     */
                    if (
                        $action
                        ===
                        'partially_accept'
                    ) {
                        $submittedItems =
                            $validated['items']
                            ?? [];

                        $approvedAny =
                            false;

                        foreach (
                            $lockedRequest->items
                            as $item
                        ) {
                            $approved =
                                (float) (
                                    $submittedItems[
                                        $item->id
                                    ][
                                        'approved_quantity'
                                    ]
                                    ?? 0
                                );

                            $requested =
                                (float)
                                $item
                                    ->requested_quantity;

                            if (
                                $approved < 0
                                || $approved > $requested
                            ) {
                                throw ValidationException::withMessages([
                                    "items.{$item->id}.approved_quantity" =>
                                        'الكمية المعتمدة يجب أن تكون بين 0 والكمية المطلوبة.',
                                ]);
                            }

                            if ($approved > 0) {
                                $approvedAny =
                                    true;
                            }

                            $item->update([
                                'approved_quantity' =>
                                    $approved,
                            ]);
                        }

                        if (! $approvedAny) {
                            throw ValidationException::withMessages([
                                'items' =>
                                    'يجب اعتماد كمية أكبر من صفر لصنف واحد على الأقل.',
                            ]);
                        }
                    }

                    /*
                     * الرفض:
                     * لا توجد أي كمية معتمدة.
                     */
                    if ($action === 'reject') {
                        foreach (
                            $lockedRequest->items
                            as $item
                        ) {
                            $item->update([
                                'approved_quantity' =>
                                    0,
                            ]);
                        }
                    }

                    $lockedRequest->update([
                        'status' =>
                            $newStatus,

                        'rejection_reason' =>
                            $validated[
                                'rejection_reason'
                            ]
                            ?? null,

                        'reviewed_by' =>
                            Auth::id(),

                        'reviewed_at' =>
                            now(),
                    ]);

                    /*
                     * عند القبول الكامل أو الجزئي ننشئ تحويلًا واحدًا فقط.
                     * لا يتم خصم المخزون هنا؛ الخصم يحصل عند Dispatch
                     * داخل StockTransferController.
                     */
                    $transfer =
                        null;

                    if (
                        in_array(
                            $action,
                            [
                                'accept',
                                'partially_accept',
                            ],
                            true
                        )
                    ) {
                        $transfer =
                            $this
                                ->createTransferFromAcceptedRequest(
                                    $lockedRequest
                                );
                    }

                    return [
                        'request' =>
                            $lockedRequest,

                        'old_status' =>
                            $currentStatus,

                        'new_status' =>
                            $newStatus,

                        'transfer' =>
                            $transfer,
                    ];
                }
            );

        ActivityLogger::log(
            userId: Auth::id(),
            action: "stock_request.{$action}",
            module: 'inventory',
            recordType: 'stock_requests',
            recordId: $stockRequest->id,
            oldValues: [
                'status' =>
                    $result['old_status'],
            ],
            newValues: [
                'status' =>
                    $result['new_status'],
            ],
            metadata: [
                'rejection_reason' =>
                    $validated[
                        'rejection_reason'
                    ]
                    ?? null,

                'transfer_id' =>
                    $result['transfer']
                        ?->id,
            ],
        );

        NotificationDispatcher::notifyByPermissions(
            new StockRequestStatusChangedNotification($result['request'], $result['new_status']),
            ['stock_requests.view', 'stock_requests.create'],
            $result['request']->branch_location_id,
            ['inventory.view_all_locations'],
            Auth::id(),
        );

        if ($result['transfer']) {
            return redirect()
                ->route(
                    'stock-transfers.show',
                    $result['transfer']
                )
                ->with(
                    'success',
                    'تم اعتماد طلب المخزون وإنشاء تحويل مخزون جاهز للتجهيز والإرسال.'
                );
        }

        return redirect()
            ->route(
                'stock-requests.show',
                $stockRequest
            )
            ->with(
                'success',
                'تم رفض طلب المخزون وتسجيل سبب الرفض.'
            );
    }

    private function createTransferFromAcceptedRequest(
        StockRequest $stockRequest
    ): StockTransfer {
        /*
         * Idempotent:
         * إذا تم إنشاء التحويل سابقًا لا ننشئ نسخة ثانية.
         */
        $existing =
            StockTransfer::query()
                ->where(
                    'stock_request_id',
                    $stockRequest->id
                )
                ->first();

        if ($existing) {
            return $existing;
        }

        $stockRequest->loadMissing(
            'items'
        );

        $approvedItems =
            $stockRequest
                ->items
                ->filter(
                    fn ($item) =>
                        (float)
                        $item->approved_quantity
                        > 0
                );

        if ($approvedItems->isEmpty()) {
            throw ValidationException::withMessages([
                'items' =>
                    'لا توجد كميات معتمدة لإنشاء تحويل المخزون.',
            ]);
        }

        $transfer =
            StockTransfer::create([
                'transfer_number' =>
                    StockTransferNumberService::generate(),

                'stock_request_id' =>
                    $stockRequest->id,

                'from_location_id' =>
                    $stockRequest->factory_location_id,

                'to_location_id' =>
                    $stockRequest->branch_location_id,

                'status' =>
                    'draft',
            ]);

        foreach (
            $approvedItems
            as $requestItem
        ) {
            StockTransferItem::create([
                'stock_transfer_id' =>
                    $transfer->id,

                'product_id' =>
                    $requestItem->product_id,

                'sent_quantity' =>
                    $requestItem->approved_quantity,

                'received_quantity' =>
                    null,

                'damaged_quantity' =>
                    0,
            ]);
        }

        return $transfer;
    }
}
