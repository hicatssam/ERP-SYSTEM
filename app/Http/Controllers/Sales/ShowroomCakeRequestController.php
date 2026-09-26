<?php

namespace App\Http\Controllers\Sales;

use App\Enums\ShowroomCakeRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomCakeRequestItem;
use App\Models\User;
use App\Services\Notifications\ShowroomCakeRequestNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ShowroomCakeRequestController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $query = ShowroomCakeRequest::query()
            ->with([
                'requestingLocation',
                'factoryLocation',
                'creator',
                'items',
            ]);

        if (! $user->isAdmin()) {
            $locationIds =
                $this->userLocationIds($user);

            if ($locationIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(
                    function ($query) use (
                        $locationIds
                    ): void {
                        $query
                            ->whereIn(
                                'requesting_location_id',
                                $locationIds
                            )
                            ->orWhereIn(
                                'factory_location_id',
                                $locationIds
                            );
                    }
                );
            }
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')
            );
        }

        if (
            $request->filled('location_id')
            && $user->isAdmin()
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

        $locations = $user->isAdmin()
            ? Location::query()
                ->where('type', 'branch')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        $statusEnum =
            ShowroomCakeRequestStatus::workflowCases();

        return view(
            'sales.showroom-cake-requests.index',
            compact(
                'requests',
                'locations',
                'statusEnum'
            )
        );
    }

    public function create()
    {
        /** @var User $user */
        $user = Auth::user();

        $canChooseBranch =
            $user->isAdmin();

        $branch =
            $user->primaryLocation();

        if (! $canChooseBranch) {
            abort_unless(
                $branch
                && $branch->isBranch()
                && $branch->is_active,
                403,
                'لا يوجد فرع فعال مرتبط بحسابك.'
            );
        }

        $branches = $canChooseBranch
            ? Location::query()
                ->where('type', 'branch')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect([$branch]);

        $factories = Location::query()
            ->where('type', 'factory')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view(
            'sales.showroom-cake-requests.create',
            compact(
                'branch',
                'branches',
                'factories',
                'canChooseBranch'
            )
        );
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

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
                    fn ($query) =>
                        $query
                            ->where('type', 'branch')
                            ->where('is_active', true)
                            ->whereNull('deleted_at')
                ),
            ],
            'factory_location_id' => [
                'required',
                Rule::exists(
                    'locations',
                    'id'
                )->where(
                    fn ($query) =>
                        $query
                            ->where('type', 'factory')
                            ->where('is_active', true)
                            ->whereNull('deleted_at')
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
            'items.*.cake_type' => [
                'required',
                'string',
                'max:100',
            ],
            'items.*.cake_size' => [
                'nullable',
                'string',
                'max:50',
            ],
            'items.*.flavor' => [
                'nullable',
                'string',
                'max:100',
            ],
            'items.*.shape' => [
                'nullable',
                'string',
                'max:50',
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
            'items.*.notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $branch = $canChooseBranch
            ? Location::query()
                ->where('type', 'branch')
                ->where('is_active', true)
                ->findOrFail(
                    (int)
                    $validated[
                        'requesting_location_id'
                    ]
                )
            : $user->primaryLocation();

        abort_unless(
            $branch
            && $branch->isBranch()
            && $branch->is_active,
            403,
            'لا يمكن إنشاء الطلب بدون فرع صحيح.'
        );

        $factory = Location::query()
            ->where('type', 'factory')
            ->where('is_active', true)
            ->findOrFail(
                (int)
                $validated[
                    'factory_location_id'
                ]
            );

        $showroomRequest =
            DB::transaction(
                function () use (
                    $validated,
                    $user,
                    $branch,
                    $factory
                ) {
                    $showroomRequest =
                        ShowroomCakeRequest::query()
                            ->create([
                                'request_number' =>
                                    ShowroomCakeRequest::generateNumber(),
                                'requesting_location_id' =>
                                    $branch->id,
                                'factory_location_id' =>
                                    $factory->id,
                                'status' =>
                                    ShowroomCakeRequestStatus::Pending,
                                'needed_by' =>
                                    $validated['needed_by']
                                    ?? null,
                                'notes' =>
                                    $validated['notes']
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
                        ShowroomCakeRequestItem::query()
                            ->create([
                                'showroom_cake_request_id' =>
                                    $showroomRequest->id,
                                'cake_type' =>
                                    $item['cake_type'],
                                'cake_size' =>
                                    $item['cake_size']
                                    ?? null,
                                'flavor' =>
                                    $item['flavor']
                                    ?? null,
                                'shape' =>
                                    $item['shape']
                                    ?? null,
                                'quantity' =>
                                    $item['quantity'],
                                'notes' =>
                                    $item['notes']
                                    ?? null,
                            ]);
                    }

                    return $showroomRequest;
                }
            );

        ShowroomCakeRequestNotifier::requestCreated(
            $showroomRequest->fresh([
                'requestingLocation',
                'factoryLocation',
                'items',
            ]),
            $user
        );

        return redirect()
            ->route(
                'showroom-cake-requests.show',
                $showroomRequest
            )
            ->with(
                'success',
                'تم إنشاء طلب كيك الفرع ووضعه قيد المراجعة.'
            );
    }

    public function show(
        ShowroomCakeRequest $showroomCakeRequest
    ) {
        /** @var User $user */
        $user = Auth::user();

        $this->ensureCanAccess(
            $user,
            $showroomCakeRequest
        );

        $showroomCakeRequest->load([
            'requestingLocation',
            'factoryLocation',
            'creator',
            'handledBy',
            'items',
        ]);

        $allowedTransitions =
            $this->allowedTransitionsForUser(
                $user,
                $showroomCakeRequest
            );

        return view(
            'sales.showroom-cake-requests.show',
            compact(
                'showroomCakeRequest',
                'allowedTransitions'
            )
        );
    }

    public function updateStatus(
        Request $request,
        ShowroomCakeRequest $showroomCakeRequest
    ) {
        /** @var User $user */
        $user = Auth::user();

        $this->ensureCanAccess(
            $user,
            $showroomCakeRequest
        );

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::enum(
                    ShowroomCakeRequestStatus::class
                ),
            ],
            'factory_notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $newStatus =
            ShowroomCakeRequestStatus::from(
                $validated['status']
            );

        if (
            ! $showroomCakeRequest
                ->canTransitionTo(
                    $newStatus
                )
        ) {
            return back()->with(
                'error',
                'لا يمكن الانتقال إلى هذه الحالة.'
            );
        }

        abort_unless(
            $user->isAdmin()
            || $user->can(
                'showroom_cake_requests.update_status'
            ),
            403,
            'لا تملك صلاحية تحديث حالة الطلب.'
        );

        abort_unless(
            $this->transitionLocationAllowed(
                $user,
                $showroomCakeRequest,
                $newStatus
            ),
            403,
            'هذه المرحلة تخص موقعًا آخر.'
        );

        $oldStatus =
            $showroomCakeRequest
                ->status
                ->workflowValue();

        $data = [
            'status' => $newStatus,
        ];

        if (
            array_key_exists(
                'factory_notes',
                $validated
            )
        ) {
            $data['factory_notes'] =
                $validated['factory_notes'];
        }

        if (
            $newStatus ===
            ShowroomCakeRequestStatus::InProgress
        ) {
            $data['handled_by'] =
                $user->id;
        }

        if (
            $newStatus ===
            ShowroomCakeRequestStatus::Completed
        ) {
            $data['fulfilled_at'] =
                now();

            $data['handled_by'] =
                $user->id;
        }

        $showroomCakeRequest->update(
            $data
        );

        $showroomCakeRequest->refresh();

        ShowroomCakeRequestNotifier::statusChanged(
            $showroomCakeRequest,
            $oldStatus,
            $newStatus->value,
            $user
        );

        return back()->with(
            'success',
            'تم تحديث حالة طلب كيك الفرع.'
        );
    }

    public function destroy(
        ShowroomCakeRequest $showroomCakeRequest
    ) {
        if (
            $showroomCakeRequest->status
            !== ShowroomCakeRequestStatus::Cancelled
        ) {
            return back()->with(
                'error',
                'يمكن حذف الطلب الملغي فقط.'
            );
        }

        $showroomCakeRequest->delete();

        return redirect()
            ->route(
                'showroom-cake-requests.index'
            )
            ->with(
                'success',
                'تم حذف طلب كيك الفرع.'
            );
    }

    /**
     * @return array<int, ShowroomCakeRequestStatus>
     */
    private function allowedTransitionsForUser(
        User $user,
        ShowroomCakeRequest $request
    ): array {
        return collect(
            $request
                ->status
                ->allowedTransitions()
        )
            ->filter(
                fn (
                    ShowroomCakeRequestStatus $status
                ): bool =>
                    (
                        $user->isAdmin()
                        || $user->can(
                            'showroom_cake_requests.update_status'
                        )
                    )
                    && $this->transitionLocationAllowed(
                        $user,
                        $request,
                        $status
                    )
            )
            ->values()
            ->all();
    }

    private function transitionLocationAllowed(
        User $user,
        ShowroomCakeRequest $request,
        ShowroomCakeRequestStatus $status
    ): bool {
        if ($user->isAdmin()) {
            return true;
        }

        $locationIds =
            $this->userLocationIds(
                $user
            );

        if (
            $status ===
            ShowroomCakeRequestStatus::Completed
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
            ShowroomCakeRequestStatus::Cancelled
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

    private function ensureCanAccess(
        User $user,
        ShowroomCakeRequest $request
    ): void {
        if ($user->isAdmin()) {
            return;
        }

        $locationIds =
            $this->userLocationIds(
                $user
            );

        abort_unless(
            in_array(
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
            ),
            403,
            'لا تملك صلاحية الوصول إلى هذا الطلب.'
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
            ->pluck('locations.id')
            ->map(
                fn ($id) => (int) $id
            )
            ->all()
            ?? [];
    }
}
