<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileImageDeliveryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function owner_can_view_profile_image_without_a_public_storage_link(): void
    {
        Storage::fake('public');
        $path = 'users/profile-images/avatar.png';
        Storage::disk('public')->put($path, 'image-content');
        $owner = User::factory()->create(['profile_image' => $path]);
        $this->assignBranch($owner);

        $this->actingAs($owner)->get(route('profile.show'))
            ->assertOk()->assertSee(route('profile.image'), false)
            ->assertDontSee('/storage/'.$path, false);
        $imageResponse = $this->actingAs($owner)->get(route('profile.image'))
            ->assertOk();
        $this->assertStringContainsString('private', (string) $imageResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $imageResponse->headers->get('Cache-Control'));
        $this->assertSame('image-content', $this->actingAs($owner)
            ->get(route('profile.image'))->streamedContent());

        $other = User::factory()->create();
        $this->assignBranch($other);
        $this->actingAs($other)->get(route('profile.image'))->assertNotFound();
    }

    #[Test]
    public function missing_picture_shows_initial_and_never_requests_a_broken_storage_url(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'profile_image' => 'users/profile-images/lost.png',
        ]);
        $this->assignBranch($user);

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('id="profileImagePlaceholder"', false)
            ->assertDontSee('/storage/users/profile-images/lost.png', false);
    }

    private function assignBranch(User $user): void
    {
        $branch = Location::query()->create([
            'name' => 'Profile Branch '.$user->id,
            'code' => 'PROFILE-'.$user->id,
            'type' => 'branch',
            'is_active' => true,
        ]);
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
    }
}
