<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\Loyalty\LoyaltyService;
use Throwable;

class LoyaltyOrderStatusListener
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    public function handle(OrderStatusChanged $event): void
    {
        try {
            if ($event->toStatus === 'completed') {
                $this->loyalty->earnForCompletedOrder($event->order, $event->changedBy);
                return;
            }
            if (in_array($event->toStatus, ['cancelled','canceled'], true)) {
                $this->loyalty->reverseCancelledOrder($event->order, $event->changedBy);
            }
        } catch (Throwable $e) {
            // Loyalty is an additive benefit. A loyalty infrastructure problem must
            // never roll back a valid order completion/cancellation. The backfill
            // command can repair a missed earning later.
            report($e);
        }
    }
}
