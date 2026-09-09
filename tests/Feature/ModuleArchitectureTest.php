<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Module;
use App\Models\User;
use App\Services\ModuleService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ModuleArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(DatabaseSeeder::class)->seedArchitectureRegistryOnly();
    }

    public function test_core_and_industry_modules_are_registered_and_codes_are_unique(): void
    {
        $this->assertDatabaseHas('modules', ['code' => 'sales', 'type' => 'core']);
        $this->assertDatabaseHas('modules', ['code' => 'restaurant', 'type' => 'industry']);
        $this->assertSame(Module::count(), Module::distinct('code')->count('code'));
    }

    public function test_module_service_resolves_enabled_state(): void
    {
        $service = app(ModuleService::class);
        $this->assertNotNull($service->get('inventory'));
        $this->assertTrue($service->isEnabled('inventory'));
        $this->assertFalse($service->isEnabled('restaurant'));
    }

    public function test_system_module_cannot_be_disabled(): void
    {
        $this->expectException(ValidationException::class);
        app(ModuleService::class)->setEnabled('settings', false);
    }

    public function test_disabled_module_middleware_blocks_direct_access(): void
    {
        $middleware = app(EnsureModuleEnabled::class);
        $request = Request::create('/safe-module-test', 'GET');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $middleware->handle($request, fn () => response('ok'), 'restaurant');
    }

    public function test_existing_dahab_route_names_remain_registered(): void
    {
        foreach ([
            'dashboard', 'cake-orders.index', 'inventory.index',
            'procurement.dashboard', 'financial.dashboard',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Missing existing route: {$routeName}");
        }
    }
}
