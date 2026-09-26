<?php

namespace Tests\Feature\Reports;

use App\Exports\ChunkedQueryReportExport;
use App\Models\Employee;
use App\Models\Location;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PrintThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UnifiedReportBrandingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $location = Location::query()->create([
            'name' => 'Report Branch',
            'code' => 'RP-' . Str::upper(
                Str::random(8)
            ),
            'type' => 'branch',
            'is_active' => true,
        ]);

        $employee = Employee::query()->create([
            'employee_number' =>
                'RP-EMP-' . Str::upper(
                    Str::random(8)
                ),
            'full_name' => 'Report Admin',
            'employment_status' => 'active',
        ]);

        $employee
            ->locations()
            ->attach(
                $location->id,
                ['is_primary' => true]
            );

        $this->admin = User::factory()->create([
            'employee_id' => $employee->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $role = Role::findOrCreate(
            'Admin',
            'web'
        );

        $permission =
            Permission::findOrCreate(
                'reports.view',
                'web'
            );

        $role->givePermissionTo(
            $permission
        );

        $this->admin->assignRole(
            $role
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    #[Test]
    public function browser_report_print_uses_central_colors_stamp_signature_and_footer(): void
    {
        $this->setBranding();

        $this->actingAs($this->admin)
            ->get(
                route(
                    'reports.print',
                    [
                        'type' =>
                            'cake-production',
                        'date_from' =>
                            '2026-09-26',
                        'date_to' =>
                            '2026-09-26',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                '#123456',
                false
            )
            ->assertSee(
                '#654321',
                false
            )
            ->assertSee(
                'Unified Report Footer'
            )
            ->assertSee(
                'اعتماد الإدارة'
            )
            ->assertSee(
                'التوقيع المعتمد',
                false
            )
            ->assertSee(
                'الختم الرسمي',
                false
            )
            ->assertSee(
                'data:image/png;base64,U0lHTg==',
                false
            )
            ->assertSee(
                'data:image/png;base64,U1RBTVA=',
                false
            );
    }

    #[Test]
    public function dedicated_print_signature_overrides_legacy_brand_signature(): void
    {
        SystemSetting::set(
            'brand_signature',
            'data:image/png;base64,TEVHQUNZ'
        );

        SystemSetting::set(
            'print_signature',
            'data:image/png;base64,UFJJTlQ='
        );

        SystemSetting::flushCache();

        $theme = app(
            PrintThemeService::class
        )->settings();

        $this->assertSame(
            'data:image/png;base64,UFJJTlQ=',
            $theme['signature_src']
        );
    }

    #[Test]
    public function excel_report_heading_uses_central_secondary_color_and_rtl(): void
    {
        $this->setBranding();

        $export = new ChunkedQueryReportExport(
            DB::table('locations')
                ->whereRaw('1 = 0'),
            ['العمود'],
            'تقرير تجريبي',
            fn ($row) => [
                $row->id ?? null,
            ]
        );

        $worksheet = new Worksheet();

        $styles = $export->styles(
            $worksheet
        );

        $this->assertSame(
            'FF654321',
            data_get(
                $styles,
                '1.fill.startColor.argb'
            )
        );

        $this->assertSame(
            'FFFFFFFF',
            data_get(
                $styles,
                '1.font.color.argb'
            )
        );
    }

    private function setBranding(): void
    {
        foreach ([
            'print_primary_color' =>
                '#123456',
            'print_secondary_color' =>
                '#654321',
            'print_text_color' =>
                '#111111',
            'print_show_signatures' =>
                '1',
            'print_show_stamp' =>
                '1',
            'print_show_footer' =>
                '1',
            'print_show_logo' =>
                '0',
            'print_footer_text' =>
                'Unified Report Footer',
            'business_legal_name' =>
                'Dahab Sweets',
            'print_signature' =>
                'data:image/png;base64,U0lHTg==',
            'print_stamp' =>
                'data:image/png;base64,U1RBTVA=',
        ] as $key => $value) {
            SystemSetting::set(
                $key,
                $value
            );
        }

        SystemSetting::flushCache();
    }
}
