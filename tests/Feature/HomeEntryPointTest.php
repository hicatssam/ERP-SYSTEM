<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HomeEntryPointTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_root_redirects_to_login(): void
    {
        $this->get('/')
            ->assertRedirect(
                route('login')
            );
    }

    #[Test]
    public function authenticated_root_redirects_to_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(
                route('dashboard')
            );
    }

    #[Test]
    public function shared_public_icon_partial_renders_supported_icons(): void
    {
        foreach ([
            'chevron',
            'search',
            'filter',
            'arrow',
            'heart',
        ] as $icon) {
            $html = view(
                'partials.icons',
                ['name' => $icon]
            )->render();

            $this->assertStringContainsString(
                '<svg',
                $html
            );
        }
    }
}
