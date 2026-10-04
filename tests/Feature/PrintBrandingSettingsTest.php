<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PrintThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PrintBrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function print_images_override_general_branding_and_removal_restores_fallback(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $branch = Location::query()->create([
            'name' => 'Print Branch', 'code' => 'PRINT-BRANCH',
            'type' => 'branch', 'is_active' => true,
        ]);
        $admin->employee->locations()->attach($branch->id, ['is_primary' => true]);
        $admin->givePermissionTo(Permission::findOrCreate('settings.manage', 'web'));

        Storage::disk('public')->put('branding/general.png', 'general image');
        Storage::disk('public')->put('branding/print/old.png', 'old image');
        SystemSetting::set('brand_report_logo', 'storage/branding/general.png');
        SystemSetting::set('print_logo', 'storage/branding/print/old.png');
        SystemSetting::set('brand_footer_text', 'Client footer');
        SystemSetting::set('print_footer_text', '');
        $this->assertSame('Client footer', app(PrintThemeService::class)->settings()['footer_text']);

        $input = [
            'print_template' => 'modern', 'print_primary_color' => '#112233',
            'print_secondary_color' => '#223344', 'print_text_color' => '#334455',
            'print_paper_size' => 'A4', 'print_logo_position' => 'right',
            'print_logo_size' => 90,
        ];
        $this->actingAs($admin)->put(route('settings.print-branding.update'), [
            ...$input, 'print_logo_file' => UploadedFile::fake()->image('new.png'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $path = substr(SystemSetting::get('print_logo'), strlen('storage/'));
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertMissing('branding/print/old.png');
        $this->assertStringStartsWith('data:image/png;base64,', app(PrintThemeService::class)->settings()['logo_src']);

        $this->actingAs($admin)->put(route('settings.print-branding.update'), [
            ...$input, 'remove_print_logo' => 1,
        ])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($path);
        $this->assertSame('', SystemSetting::get('print_logo'));
        $this->assertStringStartsWith('data:image/png;base64,', app(PrintThemeService::class)->settings()['logo_src']);
    }
}
