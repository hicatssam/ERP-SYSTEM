<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class GlobalArabicDatePresentationTest extends TestCase
{
    public function test_blade_directives_use_the_shared_date_formatter(): void
    {
        $html = Blade::render(
            '@dateArabic($date) | @dateTimeArabic($dateTime) | @timeArabic($dateTime)',
            [
                'date' => '2026-09-28',
                'dateTime' => '2026-09-28 16:30:00',
            ]
        );

        $this->assertStringContainsString(
            'الاثنين، 28 سبتمبر 2026',
            $html
        );

        $this->assertStringContainsString(
            '4:30 مساءً',
            $html
        );
    }

    public function test_main_layout_loads_timezone_and_shared_date_formatter(): void
    {
        $layout = file_get_contents(
            resource_path(
                'views/layouts/app.blade.php'
            )
        );

        $this->assertStringContainsString(
            'name="app-timezone"',
            $layout
        );

        $this->assertStringContainsString(
            'assets/js/date-format.js',
            $layout
        );
    }

    public function test_print_layout_uses_the_same_date_policy(): void
    {
        $layout = file_get_contents(
            resource_path(
                'views/layouts/print.blade.php'
            )
        );

        $this->assertStringContainsString(
            'ArabicDate::dateTime',
            $layout
        );

        $this->assertStringContainsString(
            'assets/js/date-format.js',
            $layout
        );
    }

    public function test_browser_formatter_handles_dynamic_content_and_protects_form_values(): void
    {
        $script = file_get_contents(
            public_path(
                'assets/js/date-format.js'
            )
        );

        $this->assertStringContainsString(
            'MutationObserver',
            $script
        );

        $this->assertStringContainsString(
            "'INPUT'",
            $script
        );

        $this->assertStringContainsString(
            "'TEXTAREA'",
            $script
        );

        $this->assertStringContainsString(
            'data-date-format="off"',
            $script
        );

        $this->assertStringContainsString(
            'formatZonedIsoDates',
            $script
        );
    }

    public function test_secondary_layouts_loading_app_js_also_receive_date_formatter(): void
    {
        $script = file_get_contents(
            public_path(
                'assets/js/app.js'
            )
        );

        $this->assertStringContainsString(
            '/assets/js/date-format.js',
            $script
        );

        $this->assertStringContainsString(
            'window.DahabDateFormatter',
            $script
        );
    }
}
