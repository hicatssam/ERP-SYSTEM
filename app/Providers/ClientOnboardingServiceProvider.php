<?php

namespace App\Providers;

use App\Models\SystemSetting;
use App\Services\ClientOnboardingService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class ClientOnboardingServiceProvider extends ServiceProvider
{
    public function boot(ClientOnboardingService $onboarding): void
    {
        $this->loadRoutesFrom(base_path('routes/onboarding.php'));

        try {
            if (! Schema::hasTable('system_settings')) {
                return;
            }

            $timezone = $onboarding->normalizedTimezone(
                (string) SystemSetting::get(
                    'timezone',
                    config('app.timezone', 'UTC')
                )
            );

            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        } catch (\Throwable) {
            // Keep application boot safe before/during installation.
        }
    }
}
