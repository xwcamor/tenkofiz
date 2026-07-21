<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::where('email', 'admin@test.com')->first();
    }

    private function makeEmployee(): Employee
    {
        return Employee::create([
            'document_number' => '11112222',
            'first_name' => 'JOHN',
            'last_name' => 'DOE',
            'schedule_id' => Schedule::first()->id,
        ]);
    }

    public function test_seeder_creates_the_three_test_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['admin@test.com', 'aprobador@test.com', 'empleado@test.com'] as $email) {
            $this->assertNotNull(User::where('email', $email)->first(), "$email missing");
        }

        $this->assertTrue(User::where('email', 'admin@test.com')->first()->hasModule('settings'));
        $this->assertTrue(User::where('email', 'aprobador@test.com')->first()->hasModule('vacations_manage'));
        $this->assertFalse(User::where('email', 'empleado@test.com')->first()->isManager());
    }

    public function test_deleting_an_employee_requires_a_reason_and_keeps_history(): void
    {
        $admin = $this->admin();
        $employee = $this->makeEmployee();
        Attendance::create(['employee_id' => $employee->id, 'date' => '2026-07-16', 'status' => 'ON_TIME', 'method' => 'MANUAL']);

        // Without a reason: rejected
        $this->actingAs($admin)->delete("/employees/{$employee->getRouteKey()}")
            ->assertSessionHasErrors('delete_reason');
        $this->assertNull($employee->fresh()->deleted_at);

        // With a reason: soft deleted, attendance history intact
        $this->actingAs($admin)->delete("/employees/{$employee->getRouteKey()}", ['delete_reason' => 'Left the company'])
            ->assertSessionHas('ok');

        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
        $this->assertSame(1, Attendance::count());
        $this->assertSame(0, Employee::count()); // hidden from normal queries

        // Same document can be registered again (unique check skips trashed rows)
        $this->actingAs($admin)->post('/employees', [
            'document_type' => 'DNI', 'document_number' => '11112222',
            'first_name' => 'JOHN', 'last_name' => 'AGAIN',
            'schedule_id' => Schedule::first()->id, 'site_id' => \App\Models\Site::first()->id, 'vacation_days_per_year' => 30, 'is_active' => 1,
        ])->assertRedirect('/employees');
    }

    public function test_admin_sees_deleted_and_can_restore(): void
    {
        $admin = $this->admin();
        $employee = $this->makeEmployee();
        $this->actingAs($admin)->delete("/employees/{$employee->getRouteKey()}", ['delete_reason' => 'Mistake']);

        // Trash view lists it
        $deleted = $this->actingAs($admin)->get('/employees?deleted=1')->viewData('employees');
        $this->assertSame(1, $deleted->total());
        $this->assertSame('Mistake', $deleted->first()->delete_reason);

        // Restore brings it back cleanly
        $this->actingAs($admin)->post("/employees/{$employee->getRouteKey()}/restore")->assertSessionHas('ok');
        $this->assertNull($employee->fresh()->deleted_at);
        $this->assertNull($employee->fresh()->delete_reason);
    }

    public function test_non_admin_cannot_see_deleted_nor_restore(): void
    {
        $this->seed(DatabaseSeeder::class);
        $approver = User::where('email', 'aprobador@test.com')->first(); // manager, but no settings module
        $employee = $this->makeEmployee();

        $this->actingAs($approver)->delete("/employees/{$employee->getRouteKey()}", ['delete_reason' => 'Test']);

        // The deleted toggle is silently ignored for non-admins
        $this->assertSame(0, $this->actingAs($approver)->get('/employees?deleted=1')->viewData('employees')->total());

        // Restore is forbidden
        $this->actingAs($approver)->post("/employees/{$employee->getRouteKey()}/restore")->assertForbidden();
    }

    public function test_deleted_user_cannot_sign_in_and_can_be_restored(): void
    {
        $admin = $this->admin();
        $victim = User::where('email', 'empleado@test.com')->first();

        $this->actingAs($admin)->delete("/users/{$victim->getRouteKey()}", ['delete_reason' => 'Duplicated account'])
            ->assertSessionHas('ok');
        $this->assertSoftDeleted('users', ['id' => $victim->id]);

        // The deleted account can no longer authenticate
        $this->post('/logout');
        $this->post('/login', ['email' => 'empleado@test.com', 'password' => '123456'])
            ->assertSessionHasErrors();

        $this->actingAs($admin)->post("/users/{$victim->getRouteKey()}/restore")->assertSessionHas('ok');
        $this->assertNull($victim->fresh()->deleted_at);
    }
}
