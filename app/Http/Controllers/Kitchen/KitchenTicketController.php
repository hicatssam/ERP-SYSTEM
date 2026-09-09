<?php

namespace App\Http\Controllers\Kitchen;

use App\Enums\KitchenTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\KitchenStation;
use App\Models\KitchenTicket;
use App\Services\Kitchen\KitchenContextService;
use App\Services\Kitchen\KitchenTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KitchenTicketController extends Controller
{
    public function __construct(
        private readonly KitchenContextService $context,
        private readonly KitchenTicketService $tickets
    ) {
    }

    public function index(Request $request)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $locations = $this->context->selectableLocations(
            $request->user()
        );

        $statuses = KitchenTicketStatus::cases();

        $stations = KitchenStation::query()
            ->forLocation($location->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
                'is_active',
            ]);

        $query = KitchenTicket::query()
            ->forLocation($location->id)
            ->with([
                'station:id,name,code,target_minutes',
                'order:id,order_number,restaurant_service_type,restaurant_table_id,waiter_id,guest_count,status,created_at',
                'order.restaurantTable:id,area_id,code,name',
                'order.restaurantTable.area:id,name',
                'order.waiter:id,employee_id,username',
                'order.waiter.employee:id,full_name',
            ]);

        if ($request->filled('status')) {
            $request->validate([
                'status' => [
                    Rule::in(
                        array_map(
                            fn (KitchenTicketStatus $status) => $status->value,
                            $statuses
                        )
                    ),
                ],
            ]);

            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('station_id')) {
            $query->where(
                'kitchen_station_id',
                $request->integer('station_id')
            );
        }

        if ($request->filled('date')) {
            $request->validate([
                'date' => [
                    'date',
                ],
            ]);

            $query->whereDate(
                'queued_at',
                $request->date('date')
            );
        }

        $tickets = $query
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString();

        return view(
            'kitchen.tickets.index',
            compact(
                'location',
                'locations',
                'statuses',
                'stations',
                'tickets',
            )
        );
    }

    public function show(
        Request $request,
        KitchenTicket $ticket
    ) {
        $this->context->authorizeTicket(
            $request->user(),
            $ticket
        );

        $ticket->load([
            'station',
            'location',
            'items.product',
            'items.orderItem',
            'order.customer',
            'order.restaurantTable.area',
            'order.waiter.employee',
            'dispatchedBy.employee',
            'startedBy.employee',
            'readyBy.employee',
            'servedBy.employee',
            'cancelledBy.employee',
        ]);

        return view(
            'kitchen.tickets.show',
            compact('ticket')
        );
    }

    public function start(
        Request $request,
        KitchenTicket $ticket
    ) {
        $this->context->authorizeTicket(
            $request->user(),
            $ticket
        );

        $ticket = $this->tickets->start(
            $ticket,
            $request->user()
        );

        return $this->transitionResponse(
            $request,
            $ticket,
            'تم بدء تحضير التذكرة.'
        );
    }

    public function ready(
        Request $request,
        KitchenTicket $ticket
    ) {
        $this->context->authorizeTicket(
            $request->user(),
            $ticket
        );

        $ticket = $this->tickets->markReady(
            $ticket,
            $request->user()
        );

        return $this->transitionResponse(
            $request,
            $ticket,
            'تم اعتماد الطلب جاهزًا للتسليم.'
        );
    }

    public function serve(
        Request $request,
        KitchenTicket $ticket
    ) {
        $this->context->authorizeTicket(
            $request->user(),
            $ticket
        );

        $ticket = $this->tickets->serve(
            $ticket,
            $request->user()
        );

        return $this->transitionResponse(
            $request,
            $ticket,
            'تم تسجيل تسليم الطلب.'
        );
    }

    public function priority(
        Request $request,
        KitchenTicket $ticket
    ) {
        $this->context->authorizeTicket(
            $request->user(),
            $ticket
        );

        $data = $request->validate([
            'urgent' => [
                'required',
                'boolean',
            ],
        ]);

        $ticket = $this->tickets->setUrgent(
            $ticket,
            (bool) $data['urgent'],
            $request->user()
        );

        return $this->transitionResponse(
            $request,
            $ticket,
            $ticket->isUrgent()
                ? 'تم وضع التذكرة كطلب عاجل.'
                : 'تم إلغاء أولوية الطلب العاجلة.'
        );
    }

    private function transitionResponse(
        Request $request,
        KitchenTicket $ticket,
        string $message
    ) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'ticket' => [
                    'id' => $ticket->id,
                    'status' => $ticket->statusValue(),
                    'status_label' => $ticket->status?->label(),
                    'priority' => $ticket->priority,
                    'urgent' => $ticket->isUrgent(),
                    'updated_at' => $ticket->updated_at?->toIso8601String(),
                ],
            ]);
        }

        return back()->with(
            'success',
            $message
        );
    }
}
