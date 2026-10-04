<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileImageDeliveryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function employee_photo_is_available_in_employee_pages_without_public_link(): void
    {
        Storage::fake('public');
        $manager = User::factory()->create();
        $this->assignBranch($manager);
        $manager->givePermissionTo(Permission::findOrCreate('employees.view', 'web'));
        $employeeUser = User::factory()->create();
        $this->assignBranch($employeeUser);
        $employeeUser->employee->locations()->sync([
            $manager->employee->primaryLocation()->id => ['is_primary' => true],
        ]);
        $photo = 'employees/profile-images/worker.png';
        Storage::disk('public')->put($photo, 'employee-image');
        $employeeUser->employee->update(['profile_image' => $photo]);

        $response = $this->actingAs($manager)->get(route('employees.image', $employeeUser->employee))->assertOk();
        $this->assertSame('employee-image', $response->streamedContent());
        $this->actingAs($employeeUser)->get(route('employees.image', $employeeUser->employee))->assertForbidden();
        Storage::disk('public')->delete($photo);
        $this->actingAs($manager)->get(route('employees.image', $employeeUser->employee))->assertNotFound();
    }

    #[Test]
    public function user_manager_sees_stored_avatars_without_storage_symlink_and_missing_ones_fall_back(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('users/profile-images/admin.png', 'admin-image');
        $manager = User::factory()->create();
        $this->assignBranch($manager);
        $manager->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));
        $pictured = User::factory()->create(['profile_image' => 'users/profile-images/admin.png']);
        $this->assignBranch($pictured);
        $pictured->employee->locations()->sync([
            $manager->employee->primaryLocation()->id => ['is_primary' => true],
        ]);
        $missing = User::factory()->create(['profile_image' => 'users/profile-images/missing.png']);

        $this->actingAs($manager)->get(route('users.index'))
            ->assertOk()->assertSee(route('users.image', $pictured), false)
            ->assertDontSee('/storage/users/profile-images/admin.png', false)
            ->assertDontSee(route('users.image', $missing), false);
        $response = $this->actingAs($manager)->get(route('users.image', $pictured))->assertOk();
        $this->assertSame('admin-image', $response->streamedContent());
        $this->actingAs($manager)->get(route('users.image', $missing))->assertNotFound();
        $this->actingAs($pictured)->get(route('users.image', $pictured))->assertForbidden();
    }

    #[Test]
    public function branch_manager_cannot_see_or_operate_on_another_branches_accounts(): void
    {
        $manager = User::factory()->create();
        $this->assignBranch($manager);
        $manager->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));
        $manager->givePermissionTo(Permission::findOrCreate('employees.view_all', 'web'));
        $manager->assignRole(\Spatie\Permission\Models\Role::findOrCreate('Branch Manager', 'web'));

        $local = User::factory()->create();
        $this->assignBranch($local);
        $local->employee->locations()->sync([
            $manager->employee->primaryLocation()->id => ['is_primary' => true],
        ]);
        $foreign = User::factory()->create();
        $this->assignBranch($foreign);

        $this->actingAs($manager)->get(route('users.index'))->assertOk()
            ->assertSee($local->username)->assertDontSee($foreign->username);
        $this->actingAs($manager)->get(route('users.edit', $foreign))->assertForbidden();
        $this->actingAs($manager)->get(route('users.image', $foreign))->assertForbidden();
        $this->actingAs($manager)->post(route('users.reset-password', $foreign))->assertForbidden();
        $this->actingAs($manager)->post(route('users.assign-role', $local), ['role' => 'Admin'])->assertForbidden();
    }

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
