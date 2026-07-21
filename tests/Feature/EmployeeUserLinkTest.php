<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Profile;
use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmployeeUserLinkTest extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): Employee
    {
        $this->seed(DatabaseSeeder::class);

        return Employee::create([
            'document_number' => '11112222',
            'first_name' => 'JOHN',
            'last_name' => 'DOE',
            'schedule_id' => Schedule::first()->id,
        ]);
    }

    public function test_create_user_with_chosen_profile(): void
    {
        $employee = $this->seedBase();
        $admin = User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail();
        $supervisorProfile = Profile::where('name', 'Supervisor')->first();

        $this->actingAs($admin)
            ->postJson("/employees/{$employee->id}/create-user", [
                'email' => 'super@test.test',
                'profile_id' => $supervisorProfile->id,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $created = User::where('email', 'super@test.test')->first();
        $this->assertSame($supervisorProfile->id, $created->profile_id);
        $this->assertSame($created->id, $employee->fresh()->user_id);
    }

    public function test_create_user_defaults_to_employee_profile(): void
    {
        $employee = $this->seedBase();

        $this->actingAs(User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail())
            ->postJson("/employees/{$employee->id}/create-user", ['email' => 'worker@test.test'])
            ->assertOk();

        $this->assertSame('Employee', User::where('email', 'worker@test.test')->first()->profile->name);
    }

    public function test_link_and_unlink_existing_user(): void
    {
        $employee = $this->seedBase();
        $admin = User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail();

        $free = User::create([
            'name' => 'Free User',
            'email' => 'free@test.test',
            'password' => 'secret123',
            'profile_id' => Profile::where('name', 'Supervisor')->first()->id,
            'company_id' => $admin->company_id, // a linkable user belongs to the same company
        ]);

        $this->actingAs($admin)
            ->post("/employees/{$employee->id}/link-user", ['user_id' => $free->id])
            ->assertRedirect();
        $this->assertSame($free->id, $employee->fresh()->user_id);

        // Linking the same user to a second employee fails
        $other = Employee::create([
            'document_number' => '33334444',
            'first_name' => 'JANE',
            'last_name' => 'ROE',
            'schedule_id' => Schedule::first()->id,
        ]);
        $this->actingAs($admin)
            ->post("/employees/{$other->id}/link-user", ['user_id' => $free->id])
            ->assertSessionHasErrors('user_id');

        $this->actingAs($admin)
            ->post("/employees/{$employee->id}/unlink-user")
            ->assertRedirect();
        $this->assertNull($employee->fresh()->user_id);
        $this->assertNotNull(User::find($free->id)); // the account survives
    }

    public function test_user_photo_upload(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail();

        $this->actingAs($admin)->post('/users', [
            'name' => 'With Photo',
            'email' => 'photo@test.test',
            'password' => 'secret123',
            'profile_id' => Profile::first()->id,
            'photo' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
            'is_active' => 1,
        ])->assertRedirect('/users');

        $user = User::where('email', 'photo@test.test')->first();
        $this->assertNotNull($user->photo);
        $this->assertFileExists(public_path($user->photo));

        @unlink(public_path($user->photo));
    }
}
