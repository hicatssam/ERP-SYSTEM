<?php

namespace App\Providers;

use App\Models\ReportSchedule;
use App\Models\User;
use App\Policies\ReportSchedulePolicy;
use App\Services\Auth\RestaurantOperatorPermissionRepairService;
use App\Services\Notifications\MetaWhatsAppGateway;
use App\Services\Notifications\WhatsAppGateway;
use App\Services\Restaurant\RestaurantContextService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Events\SeederFinished;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RestaurantContextService::class, function ($app) {
            return new RestaurantContextService();
        });

        $this->app->singleton(
            WhatsAppGateway::class,
            MetaWhatsAppGateway::class
        );
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // DatabaseSeeder's legacy prefix rules miss underscore-style restaurant
        // permissions. Re-apply the intended operator matrix after any seeding
        // run so migrate:fresh --seed and db:seed remain operational as well.
        Event::listen(SeederFinished::class, function (): void {
            app(RestaurantOperatorPermissionRepairService::class)->repair();
        });

        // Admin gate — bypasses all permission checks.
        Gate::before(function (User $user, string $ability) {
            if ($user->isAdmin()) {
                return true;
            }
        });

        Gate::policy(ReportSchedule::class, ReportSchedulePolicy::class);
        Paginator::defaultView('pagination.custom');

        Blade::directive('statusArabic', static fn (string $expression): string =>
            "<?php echo e(\\App\\Support\\ArabicDisplay::status({$expression})); ?>"
        );
        Blade::directive('actionArabic', static fn (string $expression): string =>
            "<?php echo e(\\App\\Support\\ArabicDisplay::action({$expression})); ?>"
        );
        Blade::directive('currencyArabic', static fn (string $expression): string =>
            "<?php echo e(\\App\\Support\\ArabicDisplay::currency({$expression})); ?>"
        );
        Blade::directive('roleArabic', static fn (string $expression): string =>
            "<?php echo e(\\App\\Support\\ArabicDisplay::role({$expression})); ?>"
        );
    }
}
