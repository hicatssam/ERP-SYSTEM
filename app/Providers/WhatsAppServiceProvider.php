<?php

namespace App\Providers;

use App\Contracts\WhatsAppGateway;
use App\Services\WhatsApp\MetaWhatsAppGateway;
use Illuminate\Support\ServiceProvider;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            WhatsAppGateway::class,
            MetaWhatsAppGateway::class
        );
    }
}
