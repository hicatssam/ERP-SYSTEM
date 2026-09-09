<?php

namespace App\Services\Production;

use App\Models\ProductionBatch;
use App\Models\User;
use App\Notifications\ProductionBatchNotification;
use Throwable;

class ProductionNotificationService
{
    public function released(ProductionBatch $batch): void
    {
        $this->notify(
            $batch,
            'released',
            ['production.start', 'production.view']
        );
    }

    public function awaitingQuality(ProductionBatch $batch): void
    {
        $this->notify(
            $batch,
            'awaiting_quality',
            ['quality_control.view', 'quality_control.decide']
        );
    }

    public function completed(ProductionBatch $batch): void
    {
        $this->notify(
            $batch,
            'completed',
            ['production.view', 'production.cost.view']
        );
    }

    public function rejected(ProductionBatch $batch): void
    {
        $this->notify(
            $batch,
            'rejected',
            ['production.view', 'quality_control.view']
        );
    }

    private function notify(
        ProductionBatch $batch,
        string $event,
        array $permissions
    ): void {
        $users = User::query()
            ->where('is_active', true)
            ->with('employee.locations')
            ->get()
            ->filter(
                function (User $user) use (
                    $batch,
                    $permissions
                ): bool {
                    if ($user->isAdmin()) {
                        return true;
                    }

                    $hasPermission =
                        collect($permissions)
                            ->contains(
                                fn (string $permission) =>
                                    $user->can($permission)
                            );

                    if (! $hasPermission) {
                        return false;
                    }

                    if ($user->can('production.view_all_locations')) {
                        return true;
                    }

                    return
                        (int) ($user->primaryLocation()?->id ?? 0)
                        ===
                        (int) $batch->location_id;
                }
            )
            ->unique('id');

        foreach ($users as $user) {
            try {
                $user->notify(
                    new ProductionBatchNotification(
                        $batch,
                        $event
                    )
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
