<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Console\Command;

class BackfillLoyaltyPoints extends Command
{
    protected $signature = 'loyalty:backfill {--from=} {--to=} {--location=}';
    protected $description = 'Backfill loyalty points for completed customer orders. Safe and idempotent.';

    public function handle(LoyaltyService $loyalty): int
    {
        $query = Order::query()
            ->where('status', 'completed')
            ->whereNotNull('customer_id')
            ->orderBy('id');

        // Historical data may predate completed_at. Use created_at as a safe
        // fallback so the CLI date window matches the project's other
        // historical backfill/reporting conventions without mutating orders.
        if ($this->option('from')) {
            $query->whereRaw(
                'DATE(COALESCE(completed_at, created_at)) >= ?',
                [$this->option('from')]
            );
        }

        if ($this->option('to')) {
            $query->whereRaw(
                'DATE(COALESCE(completed_at, created_at)) <= ?',
                [$this->option('to')]
            );
        }

        if ($this->option('location')) {
            $query->where('location_id', (int) $this->option('location'));
        }

        $processed = 0;
        $earned = 0;

        $query->chunkById(200, function ($orders) use (
            $loyalty,
            &$processed,
            &$earned
        ): void {
            foreach ($orders as $order) {
                $processed++;

                if ($loyalty->earnForCompletedOrder($order, null)) {
                    $earned++;
                }
            }
        });

        $this->info(
            "Processed: {$processed}; earning rows present/created: {$earned}"
        );
        return self::SUCCESS;
    }
}
