<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryExpiryService;
use Illuminate\Console\Command;
use Throwable;

class CheckInventoryExpiry extends Command
{
    protected $signature = 'inventory:check-expiry
                            {--force : Run even if monitoring is disabled}';

    protected $description =
        'Scan available inventory batches and deliver expiry alerts.';

    public function handle(
        InventoryExpiryService $service
    ): int {
        try {
            $summary = $service->scanAndNotify(
                force: (bool) $this->option('force')
            );

            if (($summary['status'] ?? null) === 'disabled') {
                $this->warn($summary['message']);

                return self::SUCCESS;
            }

            $this->info('Inventory expiry scan completed.');

            $this->table(
                ['Metric', 'Value'],
                collect($summary)
                    ->map(
                        fn ($value, $key) => [
                            $key,
                            is_scalar($value)
                                ? (string) $value
                                : json_encode($value),
                        ]
                    )
                    ->values()
                    ->all()
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error(
                'Expiry scan failed: ' . $e->getMessage()
            );

            report($e);

            return self::FAILURE;
        }
    }
}
