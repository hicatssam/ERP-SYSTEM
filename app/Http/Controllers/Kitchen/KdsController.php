<?php

namespace App\Http\Controllers\Kitchen;

use App\Enums\KitchenTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\KitchenStation;
use App\Models\KitchenTicket;
use App\Models\SystemSetting;
use App\Services\Kitchen\KitchenContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KdsController extends Controller
{
    public function __construct(
        private readonly KitchenContextService $context
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

        $stations = KitchenStation::query()
            ->forLocation($location->id)
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
                'target_minutes',
                'is_default',
            ]);

        $settings = [
            'poll_seconds' => max(
                2,
                min(
                    15,
                    (int) SystemSetting::get(
                        'kds_poll_seconds',
                        3
                    )
                )
            ),
            'warning_minutes' => max(
                1,
                (int) SystemSetting::get(
                    'kds_warning_minutes',
                    10
                )
            ),
            'critical_minutes' => max(
                2,
                (int) SystemSetting::get(
                    'kds_critical_minutes',
                    20
                )
            ),
            'sound_enabled' => (bool) SystemSetting::get(
                'kds_sound_enabled',
                true
            ),
            'sound_volume' => max(
                0,
                min(
                    100,
                    (int) SystemSetting::get(
                        'kds_sound_volume',
                        70
                    )
                )
            ),
        ];

        if (
            $settings['critical_minutes']
            <= $settings['warning_minutes']
        ) {
            $settings['critical_minutes'] =
                $settings['warning_minutes'] + 1;
        }

        return view(
            'kitchen.kds.index',
            compact(
                'location',
                'locations',
                'stations',
                'settings',
            )
        );
    }

    public function feed(Request $request): JsonResponse
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $stationId = $request->integer('station_id') ?: null;

        if ($stationId) {
            abort_unless(
                KitchenStation::query()
                    ->forLocation($location->id)
                    ->whereKey($stationId)
                    ->exists(),
                403,
                'محطة المطبخ لا تتبع الفرع المحدد.'
            );
        }

        $warningMinutes = max(
            1,
            (int) SystemSetting::get(
                'kds_warning_minutes',
                10
            )
        );

        $criticalMinutes = max(
            $warningMinutes + 1,
            (int) SystemSetting::get(
                'kds_critical_minutes',
                20
            )
        );

        $tickets = KitchenTicket::query()
            ->forLocation($location->id)
            ->open()
            ->when(
                $stationId,
                fn ($query) => $query->where(
                    'kitchen_station_id',
                    $stationId
                )
            )
            ->with([
                'station:id,name,code,target_minutes',
                'order:id,order_number,restaurant_service_type,restaurant_table_id,waiter_id,guest_count,notes,created_at',
                'order.restaurantTable:id,area_id,code,name',
                'order.restaurantTable.area:id,name',
                'order.waiter:id,employee_id,username',
                'order.waiter.employee:id,full_name',
                'items:id,kitchen_ticket_id,product_name,quantity,status,kitchen_notes,routing_source',
            ])
            ->orderByDesc('priority')
            ->orderBy('queued_at')
            ->orderBy('id')
            ->limit(120)
            ->get();

        $payload = $tickets->map(
            function (KitchenTicket $ticket) use (
                $warningMinutes,
                $criticalMinutes
            ): array {
                $ageSeconds = $ticket->ageSeconds();
                $ageMinutes = intdiv($ageSeconds, 60);

                $timing = 'normal';

                if ($ageMinutes >= $criticalMinutes) {
                    $timing = 'critical';
                } elseif ($ageMinutes >= $warningMinutes) {
                    $timing = 'warning';
                }

                $order = $ticket->order;
                $table = $order?->restaurantTable;

                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'status' => $ticket->statusValue(),
                    'status_label' => $ticket->status?->label(),
                    'priority' => $ticket->priority,
                    'urgent' => $ticket->isUrgent(),
                    'station' => [
                        'id' => $ticket->station?->id,
                        'name' => $ticket->station?->name,
                        'target_minutes' => $ticket->station?->target_minutes,
                    ],
                    'order' => [
                        'id' => $order?->id,
                        'number' => $order?->order_number,
                        'service_type' => $order?->restaurant_service_type?->value
                            ?? $order?->restaurant_service_type,
                        'service_type_label' => $order?->restaurant_service_type?->label(),
                        'guest_count' => $order?->guest_count,
                        'waiter' => $order?->waiter?->employee?->full_name
                            ?? $order?->waiter?->username,
                        'notes' => $order?->notes,
                    ],
                    'table' => $table
                        ? [
                            'id' => $table->id,
                            'name' => $table->displayName(),
                            'area' => $table->area?->name,
                        ]
                        : null,
                    'items' => $ticket->items
                        ->map(
                            fn ($item) => [
                                'id' => $item->id,
                                'name' => $item->product_name,
                                'quantity' => (float) $item->quantity,
                                'status' => $item->status instanceof \BackedEnum
                                    ? $item->status->value
                                    : (string) $item->status,
                                'kitchen_notes' => $item->kitchen_notes,
                                'routing_source' => $item->routing_source instanceof \BackedEnum
                                    ? $item->routing_source->value
                                    : $item->routing_source,
                            ]
                        )
                        ->values(),
                    'queued_at' => $ticket->queued_at?->toIso8601String(),
                    'age_seconds' => $ageSeconds,
                    'timing' => $timing,
                    'updated_at' => $ticket->updated_at?->toIso8601String(),
                ];
            }
        )->values();

        return response()->json([
            'location_id' => $location->id,
            'server_time' => now()->toIso8601String(),
            'latest_ticket_id' => (int) ($tickets->max('id') ?? 0),
            'counts' => [
                'queued' => $tickets
                    ->filter(
                        fn (KitchenTicket $ticket) =>
                            $ticket->statusValue() === KitchenTicketStatus::QUEUED->value
                    )
                    ->count(),
                'preparing' => $tickets
                    ->filter(
                        fn (KitchenTicket $ticket) =>
                            $ticket->statusValue() === KitchenTicketStatus::PREPARING->value
                    )
                    ->count(),
                'ready' => $tickets
                    ->filter(
                        fn (KitchenTicket $ticket) =>
                            $ticket->statusValue() === KitchenTicketStatus::READY->value
                    )
                    ->count(),
                'urgent' => $tickets
                    ->filter(fn (KitchenTicket $ticket) => $ticket->isUrgent())
                    ->count(),
            ],
            'tickets' => $payload,
        ]);
    }
}
