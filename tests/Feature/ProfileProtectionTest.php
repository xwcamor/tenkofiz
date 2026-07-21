<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::where('email', 'admin@test.com')->firstOrFail();
    }

    public function test_base_profiles_are_flagged_as_system(): void
    {
        $this->admin();
        foreach (['Administrator', 'Supervisor', 'Employee'] as $name) {
            $this->assertTrue(Profile::where('name', $name)->first()->is_system, "$name should be a system profile");
        }
    }

    public function test_a_system_profile_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $supervisor = Profile::where('name', 'Supervisor')->first();

        $this->actingAs($admin)->delete(route('profiles.destroy', $supervisor))->assertRedirect();
        $this->assertDatabaseHas('profiles', ['id' => $supervisor->id]);
    }

    public function test_a_system_profile_cannot_be_renamed_or_deactivated(): void
    {
        $admin = $this->admin();
        $supervisor = Profile::where('name', 'Supervisor')->first();

        $this->actingAs($admin)->put(route('profiles.update', $supervisor), [
            'name' => 'HACKED',
            'description' => 'x',
            'permissions' => ['reports'],
            'is_active' => false,
        ])->assertRedirect();

        $supervisor->refresh();
        $this->assertSame('Supervisor', $supervisor->name); // name locked
        $this->assertTrue($supervisor->is_active);          // stays active
        $this->assertSame(['reports'], $supervisor->permissions); // permissions still editable
    }

    public function test_the_administrator_role_always_keeps_every_module(): void
    {
        $admin = $this->admin();
        $adminProfile = Profile::where('name', 'Administrator')->first();

        // Try to strip it down to a single module
        $this->actingAs($admin)->put(route('profiles.update', $adminProfile), [
            'name' => 'Administrator',
            'description' => 'x',
            'permissions' => ['reports'],
            'is_active' => true,
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(array_keys(Profile::MODULES), $adminProfile->fresh()->permissions);
    }

    public function test_custom_profiles_remain_fully_editable_and_deletable(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('profiles.store'), [
            'name' => 'Branch Lead',
            'description' => 'Site reports only',
            'permissions' => ['reports', 'attendances'],
        ])->assertRedirect();

        $custom = Profile::where('name', 'Branch Lead')->firstOrFail();
        $this->assertFalse($custom->is_system);

        $this->actingAs($admin)->delete(route('profiles.destroy', $custom))->assertRedirect();
        $this->assertDatabaseMissing('profiles', ['id' => $custom->id]);
    }
}
