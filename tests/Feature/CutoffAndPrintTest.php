<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Justification;
use App\Models\Profile;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vacation;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CutoffAndPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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

    // ---------- Cut-off period ----------

    public function test_current_period_with_cutoff_day_19(): void
    {
        $this->seedBase();
        Setting::instance()->update(['cutoff_day' => 19]);
        app()->forgetInstance('app.setting');

        // July 17 (company time): the period is June 20 .. July 19
        Carbon::setTestNow('2026-07-17 18:00:00'); // UTC; Lima = 13:00 same day
        [$start, $end] = current_period();
        $this->assertSame('2026-06-20', $start->toDateString());
        $this->assertSame('2026-07-19', $end->toDateString());

        // July 25: the period rolls over to July 20 .. August 19
        Carbon::setTestNow('2026-07-25 18:00:00');
        [$start, $end] = current_period();
        $this->assertSame('2026-07-20', $start->toDateString());
        $this->assertSame('2026-08-19', $end->toDateString());
    }

    public function test_current_period_defaults_to_calendar_month(): void
    {
        $this->seedBase();

        Carbon::setTestNow('2026-07-17 18:00:00');
        [$start, $end] = current_period();
        $this->assertSame('2026-07-01', $start->toDateString());
        $this->assertSame('2026-07-31', $end->toDateString());
    }

    public function test_attendance_index_defaults_to_cutoff_period(): void
    {
        $this->seedBase();
        Setting::instance()->update(['cutoff_day' => 19]);
        app()->forgetInstance('app.setting');
        Carbon::setTestNow('2026-07-17 18:00:00');

        $response = $this->actingAs(User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail())->get('/attendances');

        $response->assertOk();
        $this->assertSame('2026-06-20', $response->viewData('from')->toDateString());
        $this->assertSame('2026-07-17', $response->viewData('to')->toDateString()); // capped at today
    }

    // ---------- Printable forms ----------

    public function test_vacation_and_justification_print_views_render(): void
    {
        $employee = $this->seedBase();
        $admin = User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail();

        $vacation = Vacation::create([
            'employee_id' => $employee->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-07',
            'days' => 7,
            'reason' => 'Family trip',
        ]);
        $justification = Justification::create([
            'employee_id' => $employee->id,
            'date' => '2026-07-10',
            'reason' => 'Medical appointment',
        ]);

        $this->actingAs($admin)->get("/vacations/{$vacation->id}/print")
            ->assertOk()->assertSee('Family trip');
        $this->actingAs($admin)->get("/justifications/{$justification->id}/print")
            ->assertOk()->assertSee('Medical appointment');
    }

    public function test_employees_can_only_print_their_own_requests(): void
    {
        $employee = $this->seedBase();

        $vacation = Vacation::create([
            'employee_id' => $employee->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-07',
            'days' => 7,
            'reason' => 'Family trip',
        ]);

        // A different user with the self-service Employee profile
        $profile = Profile::where('name', 'Employee')->first();
        $stranger = User::create([
            'name' => 'Other User',
            'email' => 'other@test.test',
            'password' => 'secret123',
            'profile_id' => $profile->id,
        ]);

        $this->actingAs($stranger)->get("/vacations/{$vacation->id}/print")->assertForbidden();

        // The owner can print it
        $owner = User::create([
            'name' => 'John Doe',
            'email' => 'john@test.test',
            'password' => 'secret123',
            'profile_id' => $profile->id,
        ]);
        $employee->update(['user_id' => $owner->id]);

        $this->actingAs($owner)->get("/vacations/{$vacation->id}/print")->assertOk();
    }
}
