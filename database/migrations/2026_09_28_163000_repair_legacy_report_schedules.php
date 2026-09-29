<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_schedules')) {
            return;
        }

        $legacyTypes = [
            'sales_summary'      => 'daily-sales',
            'inventory_levels'   => 'inventory',
            'branch_performance' => 'branch-sales',
        ];

        // Only untouched demo schedules are deactivated. User-edited recipients
        // and schedules keep their active state and all existing addresses.
        $demoSchedules = [
            'تقرير المبيعات الأسبوعي' => [
                'type' => 'sales_summary',
                'emails' => ['admin@dahabsweets.com', 'accountant@dahabsweets.com'],
            ],
            'تقرير المخزون اليومي' => [
                'type' => 'inventory_levels',
                'emails' => ['inventory@dahabsweets.com', 'factory.mgr@dahabsweets.com'],
            ],
            'تقرير الفرع الشهري — رام الله' => [
                'type' => 'branch_performance',
                'emails' => ['branch.b01@dahabsweets.com', 'admin@dahabsweets.com'],
            ],
        ];

        DB::table('report_schedules')
            ->select(['id', 'name', 'report_type', 'recipients'])
            ->chunkById(100, function ($schedules) use ($legacyTypes, $demoSchedules): void {
                foreach ($schedules as $schedule) {
                    $updates = [];
                    $rawRecipients = (string) $schedule->recipients;
                    $decoded = json_decode($rawRecipients, true);

                    if (is_array($decoded) && array_is_list($decoded) && $decoded !== []) {
                        $emails = [];
                        foreach ($decoded as $value) {
                            if (! is_string($value) || ! filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
                                $emails = [];
                                break;
                            }
                            $emails[] = trim($value);
                        }

                        if ($emails !== []) {
                            $updates['recipients'] = implode(', ', $emails);
                        }
                    }

                    if (isset($legacyTypes[$schedule->report_type])) {
                        $updates['report_type'] = $legacyTypes[$schedule->report_type];
                    }

                    $demo = $demoSchedules[$schedule->name] ?? null;
                    if ($demo !== null
                        && $schedule->report_type === $demo['type']
                        && $rawRecipients === json_encode($demo['emails'])) {
                        $updates['is_active'] = false;
                    }

                    if ($updates !== []) {
                        DB::table('report_schedules')->where('id', $schedule->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        // Data normalization is intentionally not reversed: the previous JSON
        // storage and unsupported report types would break email delivery again.
    }
};
