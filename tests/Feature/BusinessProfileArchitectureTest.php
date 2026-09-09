<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessProfileArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(DatabaseSeeder::class)->seedArchitectureRegistryOnly();
    }

    public function test_business_profiles_are_registered(): void
    {
        foreach (['bakery_sweets','restaurant','cafe','clothing','shoes','retail','general_trading','manufacturing'] as $code) {
            $this->assertDatabaseHas('business_profiles', ['code' => $code]);
        }
    }

    public function test_bakery_profile_contains_existing_dahab_modules(): void
    {
        $profile = BusinessProfile::query()->where('code', 'bakery_sweets')->firstOrFail();
        $codes = $profile->modules()->pluck('modules.code')->all();

        $this->assertContains('cake_orders', $codes);
        $this->assertContains('bakery', $codes);
        $this->assertContains('inventory', $codes);
        $this->assertContains('purchasing', $codes);
        $this->assertContains('finance', $codes);
    }
}
