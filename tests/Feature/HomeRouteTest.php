<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HomeRouteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_home_redirects_to_login(): void
    {
        $this->get('/')
            ->assertRedirect(
                route('login')
            );
    }

    #[Test]
    public function authenticated_home_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(
                route('dashboard')
            );
    }
}
