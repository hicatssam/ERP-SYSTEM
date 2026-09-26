<?php

namespace App\Http\Controllers\Sales;

use App\Enums\ShowroomSweetsRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\ShowroomSweetsRequest;
use App\Models\ShowroomSweetsRequestItem;
use App\Models\User;
use App\Services\Notifications\ShowroomSweetsRequestNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ShowroomSweetsRequestController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $canViewAll =
            $user->isAdmin() ||
            $user->can('showroom_sweets_requests.view_all');

        $query = ShowroomSweetsRequest::query()
            ->with([
                'requestingLocation',
                'factoryLocation',
                'creator',
                'items.product',
            ]);

        if (! $canViewAll) {
            $locationIds = $this->userLocationIds($user);

            if (empty($locationIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($locationIds) {
                    $q->whereIn(
                        'requesting_location_id',
                        $locationIds
                    )
                    ->orWhereIn(
                        'factory_location_id',
                        $locationIds
                    );
                });
            }
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')
            );
        }

        if (
            $request->filled('location_id') &&
            $canViewAll
        ) {
            $query->where(
                'requesting_location_id',
                $request->integer('location_id')
            );
        }

        $requests = $query
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $locations = $canViewAll
            ? Location::query()
                ->branches()
                ->active()
                ->orderBy('name')
                ->get()
            : collect();

        $statusEnum =
            ShowroomSweetsRequestStatus::workflowCases();

        return view(
            'sales.showroom-sweets-requests.index',
            compact(
                'requests',
                'locations',
                'statusEnum',
                'canViewAll'
            )
        );
    }

    public function create()
    {
        /** @var User $user */
        $user = Auth::user();

        /*
         * فقط Admin يستطيع اختيار فرع آخر.
         *
         * مدير الفرع / موظف الفرع:
         * النظام يفرض فرعه المرتبط بالحساب.
         */
        $canChooseBranch =
            $user->isAdmin();

        $branch =
            $user->primaryLocation();

        if (! $canChooseBranch) {
            abort_unless(
                $branch &&
                $branch->isBranch(),
                403,
                'لا يوجد فرع صالح مرتبط بحسابك.'
            );
        }

        $branches = $canChooseBranch
            ? Location::query()
                ->branches()
                ->active()
                ->orderBy('name')
                ->get()
            : collect([$branch]);

        $factories = Location::query()
            ->factory()
            ->active()
            ->orderBy('name')
            ->get();

        $products = Product::query()
            ->active()
            ->whereHas(
                'category',
                fn ($q) =>
                    $q->whereNotIn(
                        'slug',
                        [
                            'cakes',
                            'drinks',
                            'gifts',
                        ]
                    )
            )
            ->with('category')
            ->orderBy('name_ar')
            ->orderBy('name')
            ->get();

        return view(
            'sales.showroom-sweets-requests.create',
            compact(
                'branch',
                'branches',
                'factories',
                'products',
                'canChooseBranch'
            )
        );
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        /*
         * view_all للعرض فقط.
         *
         * Admin فقط يستطيع إنشاء الطلب
         * باسم فرع مختلف.
         */
        $canChooseBranch =
            $user->isAdmin();

        $validated = $request->validate([

            'requesting_location_id' => [
                Rule::requiredIf(
                    $canChooseBranch
                ),

                'nullable',

                Rule::exists(
                    'locations',
                    'id'
                )->where(
                    fn ($q) =>
                        $q
                            ->where(
                                'type',
                                'branch'
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->whereNull(
                                'deleted_at'
                            )
                ),
            ],

            'factory_location_id' => [
                'required',

                Rule::exists(
                    'locations',
                    'id'
                )->where(
                    fn ($q) =>
                        $q
                            ->where(
                                'type',
                                'factory'
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->whereNull(
                                'deleted_at'
                            )
                ),
            ],

            'needed_by' => [
                'nullable',
                'date',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_id' => [
                'required',
                'integer',

                Rule::exists(
                    'products',
                    'id'
                )->where(
                    fn ($q) =>
                        $q
                            ->where(
                                'is_active',
                                true
                            )
                            ->whereNull(
                                'deleted_at'
                            )
                ),
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'min:0.001',
            ],

            'items.*.requested_unit' => [
                'required',
                'string',
                'max:50',
            ],

            'items.*.notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $branch = $canChooseBranch
            ? Location::query()
                ->branches()
                ->active()
                ->findOrFail(
                    (int)
                    $validated[
                        'requesting_location_id'
                    ]
                )
            : $user->primaryLocation();

        abort_unless(
            $branch &&
            $branch->isBranch(),
            403,
            'لا يمكن إنشاء الطلب بدون فرع صحيح.'
        );

        $productIds = collect(
            $validated['items']
        )
            ->pluck('product_id')
            ->map(
                fn ($id) =>
                    (int) $id
            )
            ->unique()
            ->values();

        $products = Product::query()
            ->active()
            ->whereIn(
                'id',
                $productIds
            )
            ->whereHas(
                'category',
                fn ($q) =>
                    $q->whereNotIn(
                        'slug',
                        [
                            'cakes',
                            'drinks',
                            'gifts',
                        ]
                    )
            )
            ->get()
            ->keyBy('id');

        if (
            $products->count() !==
            $productIds->count()
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'items' =>
                        'يجب اختيار أصناف حلويات فعالة فقط من كتالوج المنتجات.',
                ]);
        }

        $showroomRequest =
            DB::transaction(
                function () use (
                    $validated,
                    $user,
                    $branch,
                    $products
                ) {

                    $showroomRequest =
                        ShowroomSweetsRequest::create([
                            'request_number' =>
                                ShowroomSweetsRequest::generateNumber(),

                            'requesting_location_id' =>
                                $branch->id,

                            'factory_location_id' =>
                                (int)
                                $validated[
                                    'factory_location_id'
                                ],

                            'status' =>
                                ShowroomSweetsRequestStatus::Pending,

                            'needed_by' =>
                                $validated[
                                    'needed_by'
                                ]
                                ?? null,

                            'notes' =>
                                $validated[
                                    'notes'
                                ]
                                ?? null,

                            'created_by' =>
                                $user->id,

                            'submitted_at' =>
                                now(),
                        ]);

                    foreach (
                        $validated['items']
                        as $item
                    ) {
                        $product =
                            $products->get(
                                (int)
                                $item[
                                    'product_id'
                                ]
                            );

                        ShowroomSweetsRequestItem::create([
                            'showroom_sweets_request_id' =>
                                $showroomRequest->id,

                            'product_id' =>
                                $product->id,

                            'product_name_snapshot' =>
                                $product->name_ar
                                ?: $product->name,

                            'quantity' =>
                                $item[
                                    'quantity'
                                ],

                            'requested_unit' =>
                                trim(
                                    $item[
                                        'requested_unit'
                                    ]
                                ),

                            'notes' =>
                                $item[
                                    'notes'
                                ]
                                ?? null,
                        ]);
                    }

                    return $showroomRequest;
                }
            );

        /*
        |--------------------------------------------------------------------------
        | إشعار المصنع
        |--------------------------------------------------------------------------
        |
        | بعد إنشاء الطلب مباشرة،
        | يتم إرسال إشعار للمستخدمين
        | أصحاب المهمة في المصنع.
        |
        */

        ShowroomSweetsRequestNotifier::requestCreated(
            $showroomRequest->fresh([
                'requestingLocation',
                'factoryLocation',
                'items.product',
            ]),
            $user
        );

        return redirect()
            ->route(
                'showroom-sweets-requests.show',
                $showroomRequest
            )
            ->with(
                'success',
                'تم إنشاء طلب الحلويات ووضعه قيد المراجعة.'
            );
    }

    public function show(
        ShowroomSweetsRequest
        $showroomSweetsRequest
    ) {
        /** @var User $user */
        $user = Auth::user();

        $this->ensureCanAccess(
            $user,
            $showroomSweetsRequest
        );

        $showroomSweetsRequest->load([
            'requestingLocation',

            'factoryLocation',

            'creator.employee',

            'handledBy.employee',

            'dispatchedBy.employee',

            'receivedBy.employee',

            'items.product.category',
        ]);

        $allowedTransitions =
            $this->allowedTransitionsForUser(
                $user,
                $showroomSweetsRequest
            );

        $canCancel =
            $this->canCancel(
                $user,
                $showroomSweetsRequest
            );

        return view(
            'sales.showroom-sweets-requests.show',
            compact(
                'showroomSweetsRequest',
                'allowedTransitions',
                'canCancel'
            )
        );
    }

    public function updateStatus(
        Request $request,
        ShowroomSweetsRequest
        $showroomSweetsRequest
    ) {
        /** @var User $user */
        $user = Auth::user();

        $this->ensureCanAccess(
            $user,
            $showroomSweetsRequest
        );

        $validated =
            $request->validate([

                'status' => [
                    'required',

                    Rule::enum(
                        ShowroomSweetsRequestStatus::class
                    ),
                ],

                'factory_notes' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        $newStatus =
            ShowroomSweetsRequestStatus::from(
                $validated['status']
            );

        $oldStatus =
            $showroomSweetsRequest
                ->status
                ->workflowValue();

        if (
            ! $showroomSweetsRequest
                ->canTransitionTo(
                    $newStatus
                )
        ) {
            return back()
                ->with(
                    'error',
                    'لا يمكن الانتقال إلى هذه الحالة.'
                );
        }

        $permission =
            $this->permissionForTransition(
                $newStatus
            );

        abort_unless(
            $user->isAdmin()
            || $user->can(
                'showroom_sweets_requests.update_status'
            )
            || (
                $permission
                && $user->can(
                    $permission
                )
            ),
            403,
            'لا تملك صلاحية تنفيذ هذه المرحلة.'
        );

        abort_unless(
            $this->transitionLocationAllowed(
                $user,
                $showroomSweetsRequest,
                $newStatus
            ),
            403,
            'هذه العملية تخص موقعًا آخر.'
        );

        $data = [
            'status' =>
                $newStatus,
        ];

        if (
            array_key_exists(
                'factory_notes',
                $validated
            )
        ) {
            $data[
                'factory_notes'
            ] =
                $validated[
                    'factory_notes'
                ];
        }

        if (
            $newStatus ===
            ShowroomSweetsRequestStatus::InProgress
        ) {
            $data[
                'handled_by'
            ] =
                $user->id;
        }

        if (
            $newStatus ===
            ShowroomSweetsRequestStatus::Completed
        ) {
            $data['received_by'] =
                $user->id;

            $data['received_at'] =
                now();

            $data['fulfilled_at'] =
                now();
        }

        $showroomSweetsRequest
            ->update(
                $data
            );

        $showroomSweetsRequest
            ->refresh();

        /*
        |--------------------------------------------------------------------------
        | إشعار صاحب المهمة التالية
        |--------------------------------------------------------------------------
        */

        ShowroomSweetsRequestNotifier::statusChanged(
            $showroomSweetsRequest,
            $oldStatus,
            $newStatus->value,
            $user
        );

        return back()
            ->with(
                'success',
                'تم تحديث حالة طلب الحلويات.'
            );
    }

    public function cancel(
        ShowroomSweetsRequest
        $showroomSweetsRequest
    ) {
        /** @var User $user */
        $user = Auth::user();

        $this->ensureCanAccess(
            $user,
            $showroomSweetsRequest
        );

        abort_unless(
            $this->canCancel(
                $user,
                $showroomSweetsRequest
            ),
            403,
            'لا يمكنك إلغاء هذا الطلب في حالته الحالية.'
        );

        $oldStatus =
            $showroomSweetsRequest
                ->status
                ->value;

        $showroomSweetsRequest->update([
            'status' =>
                ShowroomSweetsRequestStatus::Cancelled,
        ]);

        $showroomSweetsRequest
            ->refresh();

        /*
        |--------------------------------------------------------------------------
        | إشعار المصنع بالإلغاء
        |--------------------------------------------------------------------------
        */

        ShowroomSweetsRequestNotifier::statusChanged(
            $showroomSweetsRequest,
            $oldStatus,
            ShowroomSweetsRequestStatus::Cancelled->value,
            $user
        );

        return back()
            ->with(
                'success',
                'تم إلغاء طلب الحلويات.'
            );
    }

    public function destroy(
        ShowroomSweetsRequest
        $showroomSweetsRequest
    ) {
        /** @var User $user */
        $user = Auth::user();

        $this->ensureCanAccess(
            $user,
            $showroomSweetsRequest
        );

        abort_unless(
            $user->isAdmin()
            ||
            $user->can(
                'showroom_sweets_requests.delete'
            ),
            403
        );

        if (
            $showroomSweetsRequest->status
            !==
            ShowroomSweetsRequestStatus::Cancelled
        ) {
            return back()
                ->with(
                    'error',
                    'يمكن حذف الطلب الملغى فقط.'
                );
        }

        $showroomSweetsRequest
            ->delete();

        return redirect()
            ->route(
                'showroom-sweets-requests.index'
            )
            ->with(
                'success',
                'تم حذف طلب الحلويات.'
            );
    }

    /**
     * @return array<int, ShowroomSweetsRequestStatus>
     */
    private function allowedTransitionsForUser(
        User $user,
        ShowroomSweetsRequest $request
    ): array {
        return collect(
            $request
                ->status
                ->allowedTransitions()
        )
            ->filter(
                function (
                    ShowroomSweetsRequestStatus
                    $status
                ) use (
                    $user,
                    $request
                ): bool {

                    if (
                        $status ===
                        ShowroomSweetsRequestStatus::Cancelled
                    ) {
                        return $this->canCancel(
                            $user,
                            $request
                        );
                    }

                    $permission =
                        $this->permissionForTransition(
                            $status
                        );

                    return (
                        $user->isAdmin()
                        || $user->can(
                            'showroom_sweets_requests.update_status'
                        )
                        || (
                            $permission
                            && $user->can(
                                $permission
                            )
                        )
                    )
                    && $this->transitionLocationAllowed(
                        $user,
                        $request,
                        $status
                    );
                }
            )
            ->values()
            ->all();
    }

    private function permissionForTransition(
        ShowroomSweetsRequestStatus $status
    ): ?string {
        return match ($status) {
            ShowroomSweetsRequestStatus::InProgress =>
                'showroom_sweets_requests.start',

            ShowroomSweetsRequestStatus::Ready =>
                'showroom_sweets_requests.ready',

            ShowroomSweetsRequestStatus::Completed =>
                'showroom_sweets_requests.receive',

            ShowroomSweetsRequestStatus::Cancelled =>
                'showroom_sweets_requests.cancel',

            default => null,
        };
    }

    private function transitionLocationAllowed(
        User $user,
        ShowroomSweetsRequest $request,
        ShowroomSweetsRequestStatus $status
    ): bool {
        if (
            $user->isAdmin()
            || $user->can(
                'showroom_sweets_requests.view_all'
            )
        ) {
            return true;
        }

        $locationIds =
            $this->userLocationIds(
                $user
            );

        if (
            $status ===
            ShowroomSweetsRequestStatus::Completed
        ) {
            return in_array(
                (int) $request
                    ->requesting_location_id,
                $locationIds,
                true
            );
        }

        if (
            $status ===
            ShowroomSweetsRequestStatus::Cancelled
        ) {
            return in_array(
                (int) $request
                    ->requesting_location_id,
                $locationIds,
                true
            )
            || in_array(
                (int) $request
                    ->factory_location_id,
                $locationIds,
                true
            );
        }

        return in_array(
            (int) $request
                ->factory_location_id,
            $locationIds,
            true
        );
    }

    /**
     * @return array<int, int>
     */
    private function userLocationIds(
        User $user
    ): array {
        return $user
            ->employee
            ?->locations()
            ->pluck(
                'locations.id'
            )
            ->map(
                fn ($id) =>
                    (int) $id
            )
            ->all()
            ?? [];
    }

    private function ensureCanAccess(
        User $user,
        ShowroomSweetsRequest $request
    ): void {

        if (
            $user->isAdmin()
            ||
            $user->can(
                'showroom_sweets_requests.view_all'
            )
        ) {
            return;
        }

        $locationIds =
            $this->userLocationIds(
                $user
            );

        abort_unless(

            $user->can(
                'showroom_sweets_requests.view'
            )

            &&

            (
                in_array(
                    (int)
                    $request
                        ->requesting_location_id,

                    $locationIds,

                    true
                )

                ||

                in_array(
                    (int)
                    $request
                        ->factory_location_id,

                    $locationIds,

                    true
                )
            ),

            403,

            'لا تملك صلاحية الوصول إلى طلب هذا الفرع.'
        );
    }

    private function canCancel(
        User $user,
        ShowroomSweetsRequest $request
    ): bool {

        if (
            ! (
                $user->isAdmin()
                ||
                $user->can(
                    'showroom_sweets_requests.cancel'
                )
            )
        ) {
            return false;
        }

        if (
            ! $request
                ->status
                ->canBeCancelledByBranch()
        ) {
            return false;
        }

        if (
            $user->isAdmin()
            ||
            $user->can(
                'showroom_sweets_requests.view_all'
            )
        ) {
            return true;
        }

        return in_array(

            (int)
            $request
                ->requesting_location_id,

            $this->userLocationIds(
                $user
            ),

            true
        );
    }
}