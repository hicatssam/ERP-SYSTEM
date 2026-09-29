<?php

namespace Tests\Feature;

use App\Models\ReportSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportScheduleLegacyMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function migration_repairs_legacy_data_without_replacing_custom_recipients_or_activation(): void
    {
        $demo = ReportSchedule::create([
            'name' => 'تقرير المبيعات الأسبوعي',
            'report_type' => 'sales_summary',
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'hour' => 8,
            'recipients' => '["admin@dahabsweets.com","accountant@dahabsweets.com"]',
            'date_range' => 'last_week',
            'is_active' => true,
        ]);

        $custom = ReportSchedule::create([
            'name' => 'My inventory report',
            'report_type' => 'inventory_levels',
            'frequency' => 'daily',
            'hour' => 7,
            'recipients' => '["owner@example.com"]',
            'date_range' => 'today',
            'is_active' => true,
        ]);

        $branch = ReportSchedule::create([
            'name' => 'Branch report',
            'report_type' => 'branch_performance',
            'frequency' => 'monthly',
            'hour' => 9,
            'recipients' => 'manager@example.com',
            'date_range' => 'last_month',
            'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_09_28_163000_repair_legacy_report_schedules.php');
        $migration->up();
        $migration->up(); // A repeat run must not change repaired user data.

        $this->assertSame('daily-sales', $demo->fresh()->report_type);
        $this->assertSame('last_week', $demo->fresh()->date_range);
        $this->assertSame('admin@dahabsweets.com, accountant@dahabsweets.com', $demo->fresh()->recipients);
        $this->assertFalse($demo->fresh()->is_active);

        $this->assertSame('inventory', $custom->fresh()->report_type);
        $this->assertSame('owner@example.com', $custom->fresh()->recipients);
        $this->assertTrue($custom->fresh()->is_active);

        $this->assertSame('branch-sales', $branch->fresh()->report_type);
        $this->assertSame('manager@example.com', $branch->fresh()->recipients);
        $this->assertTrue($branch->fresh()->is_active);
    }
}
