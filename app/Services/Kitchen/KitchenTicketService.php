<?php

namespace App\Services\Kitchen;

use App\Enums\KitchenTicketStatus;
use App\Models\KitchenTicket;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\KitchenTicketNotification;
use App\Services\ActivityLogger;
use App\Services\ModuleService;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KitchenTicketService
{
    public function __construct(
        private readonly ModuleService $modules,
        private readonly KitchenRoutingService $routing
    ) {
    }

    public function dispatchIfEligible(
        Order $order,
        User $actor
    ): Collection {
        if (
            ! $this->modules->isEnabled('kitchen')
            || ! $order->isRestaurantOrder()
        ) {
            return collect();
        }

        $status = $order->status instanceof \BackedEnum
            ? $order->status->value
            : (string) $order->status;

        if ($status !== 'confirmed') {
            return collect();
        }

        return $this->dispatchConfirmedOrder(
            $order,
            $actor
        );
    }

    public function dispatchConfirmedOrder(
        Order $order,
        User $actor
    ): Collection {
        $order->loadMissing([
            'items.product.category',
            'items.modifiers',
        ]);

        if ($order->items->isEmpty()) {
            return collect();
        }

        $shouldNotify = false;

        $tickets = DB::transaction(function () use (
            $order,
            $actor,
            &$shouldNotify
        ): Collection {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $status = $lockedOrder->status instanceof \BackedEnum
                ? $lockedOrder->status->value
                : (string) $lockedOrder->status;

            if ($status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'kitchen' =>
                        'لا يمكن إرسال الطلب إلى المطبخ قبل تأكيده.',
                ]);
            }

            $lockedOrder->loadMissing([
                'items.product.category',
                'items.modifiers',
                'kitchenTickets.station',
                'kitchenTickets.items',
            ]);

            /*
             * Idempotency guard:
             * once an order has kitchen tickets, never route the same order items
             * again even if station routing is changed later.
             */
            if ($lockedOrder->kitchenTickets->isNotEmpty()) {
                if (! $lockedOrder->kitchen_dispatched_at) {
                    $lockedOrder->update([
                        'kitchen_dispatched_at' => now(),
                    ]);
                }

                return $lockedOrder->kitchenTickets
                    ->load([
                        'station',
                        'items',
                        'order.restaurantTable.area',
                        'order.waiter.employee',
                    ])
                    ->values();
            }

            $groups = [];

            foreach ($lockedOrder->items as $item) {
                if (! $item->product) {
                    throw ValidationException::withMessages([
                        'kitchen' =>
                            'تعذر إرسال أحد بنود الطلب للمطبخ لأن المنتج غير موجود.',
                    ]);
                }

                [$station, $source] = $this->routing->resolve(
                    (int) $lockedOrder->location_id,
                    $item->product
                );

                $groups[$station->id] ??= [
                    'station' => $station,
                    'items' => [],
                ];

                $groups[$station->id]['items'][] = [
                    'order_item' => $item,
                    'routing_source' => $source,
                ];
            }

            $createdTickets = collect();
            $shouldNotify = true;

            foreach ($groups as $group) {
                $station = $group['station'];

                $ticket = KitchenTicket::query()->firstOrCreate(
                    [
                        'order_id' => $lockedOrder->id,
                        'kitchen_station_id' => $station->id,
                    ],
                    [
                        'ticket_number' => $this->ticketNumber(
                            $lockedOrder->id,
                            $station->id
                        ),
                        'location_id' => $lockedOrder->location_id,
                        'status' => KitchenTicketStatus::QUEUED->value,
                        'priority' => 0,
                        'notes' => $lockedOrder->notes,
                        'queued_at' => now(),
                        'dispatched_by' => $actor->id,
                    ]
                );

                foreach ($group['items'] as $routedItem) {
                    $item = $routedItem['order_item'];

                    $ticket->items()->firstOrCreate(
                        [
                            'order_item_id' => $item->id,
                        ],
                        [
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name,
                            'quantity' => $item->quantity,
                            'status' => KitchenTicketStatus::QUEUED->value,
                            'routing_source' => $routedItem['routing_source']->value,
                            'kitchen_notes' => $this->kitchenLineNotes($item),
                        ]
                    );
                }

                $createdTickets->push(
                    $ticket->fresh([
                        'station',
                        'items',
                        'order.restaurantTable.area',
                        'order.waiter.employee',
                    ])
                );
            }

            if (! $lockedOrder->kitchen_dispatched_at) {
                $lockedOrder->update([
                    'kitchen_dispatched_at' => now(),
                ]);
            }

            ActivityLogger::log(
                userId: $actor->id,
                action: 'kitchen.order_dispatched',
                module: 'kitchen',
                recordType: 'orders',
                recordId: $lockedOrder->id,
                oldValues: null,
                newValues: [
                    'tickets_count' => $createdTickets->count(),
                    'station_ids' => $createdTickets
                        ->pluck('kitchen_station_id')
                        ->values()
                        ->all(),
                ],
                metadata: [
                    'location_id' => $lockedOrder->location_id,
                    'order_number' => $lockedOrder->order_number,
                ],
            );

            return $createdTickets;
        });

        if ($shouldNotify) {
            DB::afterCommit(function () use ($tickets): void {
                foreach ($tickets as $ticket) {
                    $this->notifyQueued($ticket);
                }
            });
        }

        return $tickets;
    }

    public function assertOrderItemChangesAllowed(
        Order $order,
        array $incomingItems
    ): void {
        if (! $order->isRestaurantOrder()) {
            return;
        }

        if (! $order->kitchenTickets()->exists()) {
            return;
        }

        $order->loadMissing('items');

        $incomingById = collect($incomingItems)
            ->filter(fn ($row) => isset($row['id']))
            ->keyBy(fn ($row) => (int) $row['id']);

        foreach ($order->items as $item) {
            $incoming = $incomingById->get((int) $item->id);

            if (! $incoming) {
                continue;
            }

            $quantityChanged =
                abs(
                    (float) ($incoming['quantity'] ?? $item->quantity)
                    - (float) $item->quantity
                ) > 0.000001;

            $notesChanged =
                array_key_exists('kitchen_notes', $incoming)
                && trim((string) ($incoming['kitchen_notes'] ?? ''))
                    !== trim((string) ($item->kitchen_notes ?? ''));

            if ($quantityChanged || $notesChanged) {
                throw ValidationException::withMessages([
                    'items' =>
                        'لا يمكن تغيير كميات أو ملاحظات بنود طلب المطعم '
                        . 'بعد إرساله إلى المطبخ. ألغِ الطلب وأنشئ طلبًا جديدًا '
                        . 'حتى تبقى تذاكر KDS والمخزون متطابقة.',
                ]);
            }
        }
    }

    public function assertOrderCanComplete(Order $order): void
    {
        if (! $order->isRestaurantOrder()) {
            return;
        }

        if (! (bool) SystemSetting::get(
            'kitchen_require_served_before_complete',
            true
        )) {
            return;
        }

        if (! $order->kitchenTickets()->exists()) {
            return;
        }

        $blocking = $order->kitchenTickets()
            ->whereIn('status', [
                KitchenTicketStatus::QUEUED->value,
                KitchenTicketStatus::PREPARING->value,
                KitchenTicketStatus::READY->value,
            ])
            ->count();

        if ($blocking > 0) {
            throw ValidationException::withMessages([
                'order' =>
                    'لا يمكن إكمال الطلب قبل إنهاء جميع تذاكر المطبخ وتسليمها.',
            ]);
        }
    }

    public function start(
        KitchenTicket $ticket,
        User $actor
    ): KitchenTicket {
        return $this->transition(
            ticket: $ticket,
            actor: $actor,
            expected: KitchenTicketStatus::QUEUED,
            next: KitchenTicketStatus::PREPARING,
            timestampColumn: 'started_at',
            actorColumn: 'started_by',
            action: 'kitchen.ticket_started'
        );
    }

    public function markReady(
        KitchenTicket $ticket,
        User $actor
    ): KitchenTicket {
        $ticket = $this->transition(
            ticket: $ticket,
            actor: $actor,
            expected: KitchenTicketStatus::PREPARING,
            next: KitchenTicketStatus::READY,
            timestampColumn: 'ready_at',
            actorColumn: 'ready_by',
            action: 'kitchen.ticket_ready'
        );

        DB::afterCommit(
            fn () => $this->notifyReady($ticket)
        );

        return $ticket;
    }

    public function serve(
        KitchenTicket $ticket,
        User $actor
    ): KitchenTicket {
        return $this->transition(
            ticket: $ticket,
            actor: $actor,
            expected: KitchenTicketStatus::READY,
            next: KitchenTicketStatus::SERVED,
            timestampColumn: 'served_at',
            actorColumn: 'served_by',
            action: 'kitchen.ticket_served'
        );
    }

    public function setUrgent(
        KitchenTicket $ticket,
        bool $urgent,
        User $actor
    ): KitchenTicket {
        $ticket = DB::transaction(function () use (
            $ticket,
            $urgent,
            $actor
        ): KitchenTicket {
            $locked = KitchenTicket::query()
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                in_array(
                    $locked->statusValue(),
                    [
                        KitchenTicketStatus::SERVED->value,
                        KitchenTicketStatus::CANCELLED->value,
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'ticket' => 'لا يمكن تغيير أولوية تذكرة منتهية.',
                ]);
            }

            $before = (int) $locked->priority;
            $after = $urgent ? 10 : 0;

            $locked->update([
                'priority' => $after,
            ]);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'kitchen.ticket_priority_changed',
                module: 'kitchen',
                recordType: 'kitchen_tickets',
                recordId: $locked->id,
                oldValues: [
                    'priority' => $before,
                ],
                newValues: [
                    'priority' => $after,
                ],
                metadata: [
                    'order_id' => $locked->order_id,
                    'location_id' => $locked->location_id,
                ],
            );

            return $locked->fresh([
                'station',
                'items',
                'order.restaurantTable.area',
            ]);
        });

        DB::afterCommit(
            fn () => $this->notifyKitchen(
                $ticket,
                'priority'
            )
        );

        return $ticket;
    }

    public function cancelForOrder(
        Order $order,
        User $actor
    ): void {
        $tickets = $order->kitchenTickets()
            ->whereIn('status', [
                KitchenTicketStatus::QUEUED->value,
                KitchenTicketStatus::PREPARING->value,
                KitchenTicketStatus::READY->value,
            ])
            ->get();

        foreach ($tickets as $ticket) {
            DB::transaction(function () use (
                $ticket,
                $actor
            ): void {
                $locked = KitchenTicket::query()
                    ->whereKey($ticket->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $old = $locked->statusValue();

                if (
                    ! in_array(
                        $old,
                        [
                            KitchenTicketStatus::QUEUED->value,
                            KitchenTicketStatus::PREPARING->value,
                            KitchenTicketStatus::READY->value,
                        ],
                        true
                    )
                ) {
                    return;
                }

                $locked->update([
                    'status' => KitchenTicketStatus::CANCELLED->value,
                    'cancelled_at' => now(),
                    'cancelled_by' => $actor->id,
                ]);

                $cancelledAt = now();

                $locked->items()
                    ->whereNotIn('status', [
                        KitchenTicketStatus::SERVED->value,
                        KitchenTicketStatus::CANCELLED->value,
                    ])
                    ->update([
                        'status' => KitchenTicketStatus::CANCELLED->value,
                        'cancelled_at' => $cancelledAt,
                    ]);

                Order::query()
                    ->whereKey($locked->order_id)
                    ->update([
                        'updated_at' => $cancelledAt,
                    ]);

                $this->logTransition(
                    $locked,
                    $actor,
                    'kitchen.ticket_cancelled',
                    $old,
                    KitchenTicketStatus::CANCELLED->value
                );
            });
        }
    }

    private function transition(
        KitchenTicket $ticket,
        User $actor,
        KitchenTicketStatus $expected,
        KitchenTicketStatus $next,
        string $timestampColumn,
        string $actorColumn,
        string $action
    ): KitchenTicket {
        return DB::transaction(function () use (
            $ticket,
            $actor,
            $expected,
            $next,
            $timestampColumn,
            $actorColumn,
            $action
        ): KitchenTicket {
            $locked = KitchenTicket::query()
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            $current = $locked->statusValue();

            if ($current !== $expected->value) {
                throw ValidationException::withMessages([
                    'ticket' =>
                        'لا يمكن تنفيذ العملية لأن حالة التذكرة الحالية هي «'
                        . ($locked->status?->label() ?? $current)
                        . '».',
                ]);
            }

            $now = now();

            $locked->update([
                'status' => $next->value,
                $timestampColumn => $now,
                $actorColumn => $actor->id,
            ]);

            $itemUpdates = [
                'status' => $next->value,
            ];

            if ($next === KitchenTicketStatus::PREPARING) {
                $itemUpdates['started_at'] = $now;
            }

            if ($next === KitchenTicketStatus::READY) {
                $itemUpdates['ready_at'] = $now;
            }

            if ($next === KitchenTicketStatus::SERVED) {
                $itemUpdates['served_at'] = $now;
            }

            $locked->items()
                ->whereNotIn('status', [
                    KitchenTicketStatus::CANCELLED->value,
                ])
                ->update($itemUpdates);

            /*
             * Keep the parent order observable for the public customer tracker.
             *
             * Kitchen/KDS status changes happen on kitchen_tickets, while the
             * customer-facing status endpoint tracks the order plus its fresh
             * kitchen state. Touching orders.updated_at gives the client a
             * monotonic change marker on every:
             *
             * queued -> preparing -> ready -> served
             */
            Order::query()
                ->whereKey($locked->order_id)
                ->update([
                    'updated_at' => $now,
                ]);

            $this->logTransition(
                $locked,
                $actor,
                $action,
                $current,
                $next->value
            );

            return $locked->fresh([
                'station',
                'items',
                'order.restaurantTable.area',
                'order.waiter.employee',
            ]);
        });
    }

    private function kitchenLineNotes(\App\Models\OrderItem $item): ?string
    {
        $parts = [];

        $modifierNames = $item->modifiers
            ->pluck('modifier_name_snapshot')
            ->filter()
            ->values();

        if ($modifierNames->isNotEmpty()) {
            $parts[] = 'الإضافات: ' . $modifierNames->implode('، ');
        }

        if (filled($item->kitchen_notes)) {
            $parts[] = trim((string) $item->kitchen_notes);
        }

        return $parts === [] ? null : implode(' | ', $parts);
    }

    private function ticketNumber(
        int $orderId,
        int $stationId
    ): string {
        return sprintf(
            'KDS-%s-%d-%d',
            now()->format('ymd'),
            $orderId,
            $stationId
        );
    }

    private function notifyQueued(KitchenTicket $ticket): void
    {
        $this->notifyKitchen(
            $ticket,
            'queued'
        );
    }

    private function notifyReady(KitchenTicket $ticket): void
    {
        NotificationDispatcher::notifyLocationAndAdmins(
            locationId: (int) $ticket->location_id,
            notification: new KitchenTicketNotification(
                $ticket,
                'ready'
            ),
            permissions: [
                'kitchen.ticket.serve',
                'restaurant_pos.use',
                'restaurant_tables.view',
            ],
            globalPermissions: [
                'restaurant.view_all_locations',
            ],
        );
    }

    private function notifyKitchen(
        KitchenTicket $ticket,
        string $event
    ): void {
        NotificationDispatcher::notifyLocationAndAdmins(
            locationId: (int) $ticket->location_id,
            notification: new KitchenTicketNotification(
                $ticket,
                $event
            ),
            permissions: [
                'kitchen.ticket.start',
                'kitchen.ticket.ready',
                'kds.view',
            ],
            globalPermissions: [
                'kitchen.view_all_locations',
            ],
        );
    }

    private function logTransition(
        KitchenTicket $ticket,
        User $actor,
        string $action,
        string $oldStatus,
        string $newStatus
    ): void {
        ActivityLogger::log(
            userId: $actor->id,
            action: $action,
            module: 'kitchen',
            recordType: 'kitchen_tickets',
            recordId: $ticket->id,
            oldValues: [
                'status' => $oldStatus,
            ],
            newValues: [
                'status' => $newStatus,
            ],
            metadata: [
                'order_id' => $ticket->order_id,
                'location_id' => $ticket->location_id,
                'kitchen_station_id' => $ticket->kitchen_station_id,
            ],
        );
    }
}
