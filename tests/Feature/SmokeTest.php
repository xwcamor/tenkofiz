<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::first();
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_admin_can_open_every_module(): void
    {
        $admin = $this->admin();

        foreach ([
            '/', '/employees', '/attendances', '/reports', '/vacations', '/justifications',
            '/my-attendances', '/calendar', '/account', '/users', '/profiles', '/schedules',
            '/holidays', '/audit-logs', '/settings',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_employee_profile_cannot_open_admin_modules(): void
    {
        $this->seed(DatabaseSeeder::class);
        $profile = Profile::where('name', 'Employee')->first();
        $user = User::create([
            'name' => 'Test Employee',
            'email' => 'employee@test.test',
            'password' => 'secret123',
            'profile_id' => $profile->id,
        ]);

        foreach (['/users', '/profiles', '/settings', '/audit-logs', '/employees', '/attendances', '/reports'] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }

        foreach (['/', '/vacations', '/justifications', '/my-attendances', '/calendar', '/account'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/users/{$admin->id}");

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_vacation_reason_is_required(): void
    {
        $admin = $this->admin();
        $employee = \App\Models\Employee::create([
            'document_number' => '12345678',
            'first_name' => 'JOHN',
            'last_name' => 'DOE',
            'schedule_id' => \App\Models\Schedule::first()->id,
        ]);

        $this->actingAs($admin)
            ->post('/vacations', [
                'employee_id' => $employee->id,
                'start_date' => now()->addDays(5)->toDateString(),
                'end_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_kiosk_requires_token_when_configured(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/kiosk')->assertOk(); // no token configured: open

        \App\Models\Setting::instance()->update(['kiosk_token' => 'secret-token-123']);
        app()->forgetInstance('app.setting');

        $this->get('/kiosk')->assertForbidden();
        $this->get('/kiosk?token=wrong')->assertForbidden();
        $this->get('/kiosk?token=secret-token-123')->assertOk();
        // token remembered in session afterwards
        $this->get('/kiosk/descriptors')->assertOk();
    }

    public function test_enrollment_requires_biometric_consent(): void
    {
        $admin = $this->admin();
        $employee = \App\Models\Employee::create([
            'document_number' => '87654321',
            'first_name' => 'JANE',
            'last_name' => 'DOE',
            'schedule_id' => \App\Models\Schedule::first()->id,
        ]);

        $descriptor = array_fill(0, 128, 0.1);

        $this->actingAs($admin)
            ->postJson("/employees/{$employee->id}/descriptor", ['descriptors' => [$descriptor]])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->postJson("/employees/{$employee->id}/descriptor", ['descriptors' => [$descriptor], 'consent' => true])
            ->assertOk();

        $this->assertNotNull($employee->fresh()->biometric_consent_at);
    }
}
