<?php

namespace App\Http\Controllers\Growth;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\DeliveryTask;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\User;
use App\Services\Delivery\DeliveryService;
use App\Services\Growth\CustomerGrowthAccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryTaskController extends Controller
{
    public function __construct(
        private readonly DeliveryService $delivery,
        private readonly CustomerGrowthAccessService $access
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $locations = $this->access->selectableLocations(
            $user,
            'delivery.view_all_locations'
        );

        $query = DeliveryTask::query()
            ->whereIn('location_id', $locations->pluck('id'))
            ->with([
                'order',
                'customer',
                'location',
                'zone',
                'driver.employee',
            ]);

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('location_id')) {
            $locationId = $request->integer('location_id');
            $this->access->ensureLocationAccess(
                $locationId,
                $user,
                'delivery.view_all_locations'
            );
            $query->where('location_id', $locationId);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($taskQuery) use ($search): void {
                $taskQuery
                    ->where('recipient_name', 'like', "%{$search}%")
                    ->orWhere('recipient_phone', 'like', "%{$search}%")
                    ->orWhereHas(
                        'order',
                        fn ($order) => $order
                            ->where('order_number', 'like', "%{$search}%")
                    )
                    ->orWhereHas(
                        'customer',
                        fn ($customer) => $customer
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                    );
            });
        }

        if (
            $user->hasRole('Delivery Driver')
            && ! $user->can('delivery.assign')
        ) {
            $query->where('assigned_driver_id', $user->id);
        }

        $tasks = $query
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('growth.delivery.index', compact('tasks', 'locations'));
    }

    public function create(Request $request)
    {
        $locations = $this->access->selectableLocations(
            $request->user(),
            'delivery.view_all_locations'
        );

        $locationIds = $locations->pluck('id');

        $query = Order::query()
            ->whereIn('location_id', $locationIds)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->whereNotIn(
                'id',
                DeliveryTask::query()->select('order_id')
            );

        if ($request->filled('order_id')) {
            $query->whereKey($request->integer('order_id'));
        }

        $orders = $query
            ->with('customer')
            ->latest()
            ->limit(100)
            ->get();

        $zones = DeliveryZone::query()
            ->active()
            ->whereIn('location_id', $locationIds)
            ->with('location:id,name')
            ->orderBy('location_id')
            ->orderBy('name')
            ->get();

        $addresses = CustomerAddress::query()
            ->with('customer:id,name')
            ->where('is_active', true)
            ->whereIn(
                'customer_id',
                $orders->pluck('customer_id')->filter()
            )
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->groupBy('customer_id');

        return view(
            'growth.delivery.create',
            compact('orders', 'zones', 'addresses')
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => [
                'required',
                'integer',
                'exists:orders,id',
                'unique:delivery_tasks,order_id',
            ],
            'delivery_zone_id' => [
                'nullable',
                'integer',
                'exists:delivery_zones,id',
            ],
            'customer_address_id' => [
                'nullable',
                'integer',
                'exists:customer_addresses,id',
            ],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:30'],
            'address_snapshot' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = Order::query()
            ->with('customer')
            ->findOrFail($data['order_id']);

        $this->access->ensureLocationAccess(
            (int) $order->location_id,
            $request->user(),
            'delivery.view_all_locations'
        );

        $task = $this->delivery->create(
            $order,
            $data,
            $request->user()
        );

        return redirect()
            ->route('delivery.tasks.show', $task)
            ->with(
                'success',
                'تم إنشاء مهمة التوصيل. رسوم التوصيل Snapshot فقط ولم يتم تعديل الفاتورة.'
            );
    }

    public function show(Request $request, DeliveryTask $task)
    {
        $user = $request->user();

        $this->access->ensureLocationAccess(
            (int) $task->location_id,
            $user,
            'delivery.view_all_locations'
        );

        if (
            $user->hasRole('Delivery Driver')
            && ! $user->can('delivery.assign')
        ) {
            abort_unless(
                (int) $task->assigned_driver_id === (int) $user->id,
                403,
                'هذه المهمة ليست معيّنة لك.'
            );
        }

        $task->load([
            'order',
            'customer',
            'location',
            'zone',
            'driver.employee',
            'histories.changedBy.employee',
        ]);

        $drivers = $user->can('delivery.assign')
            ? User::query()
                ->where('is_active', true)
                ->role('Delivery Driver')
                ->whereHas(
                    'employee.locations',
                    fn ($locations) => $locations
                        ->where('locations.id', $task->location_id)
                )
                ->with('employee')
                ->get()
            : collect();

        return view(
            'growth.delivery.show',
            compact('task', 'drivers')
        );
    }

    public function assign(Request $request, DeliveryTask $task)
    {
        $this->access->ensureLocationAccess(
            (int) $task->location_id,
            $request->user(),
            'delivery.view_all_locations'
        );

        $data = $request->validate([
            'assigned_driver_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        $driver = User::query()->findOrFail($data['assigned_driver_id']);

        $this->delivery->assign(
            $task,
            $driver,
            $request->user()
        );

        return back()->with('success', 'تم تعيين السائق.');
    }

    public function updateStatus(Request $request, DeliveryTask $task)
    {
        $this->access->ensureLocationAccess(
            (int) $task->location_id,
            $request->user(),
            'delivery.view_all_locations'
        );

        $data = $request->validate([
            'status' => ['required', Rule::enum(DeliveryStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->delivery->transition(
            $task,
            DeliveryStatus::from($data['status']),
            $request->user(),
            $data['note'] ?? null
        );

        return back()->with('success', 'تم تحديث حالة التوصيل.');
    }
}
