<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class PrintBrandingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(
            base_path(
                'routes/print-branding.php'
            )
        );
    }
}
