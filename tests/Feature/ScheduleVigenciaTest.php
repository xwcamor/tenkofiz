<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Schedule "vigencias": an employee's schedule can change over time. scheduleOn($date)
 * returns the one in force on each date (falling back to the base schedule), and the
 * derived absences use it, so a working day under one schedule can be a day off under
 * another.
 */
class ScheduleVigenciaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function base(): array
    {
        $this->seed(DatabaseSeeder::class);
        $monToSat = Schedule::first(); // Mon–Sat
        // A Mon-Fri-only schedule (Saturday off)
        $monToFri = Schedule::create(['name' => 'Mon-Fri', 'tolerance_minutes' => 10]);
        foreach (range(1, 5) as $wd) {
            $monToFri->days()->create(['weekday' => $wd, 'start_time' => '09:00:00', 'end_time' => '18:00:00']);
        }

        $employee = Employee::create([
            'document_number' => '11112222', 'first_name' => 'J', 'last_name' => 'D',
            'schedule_id' => $monToSat->id, 'hire_date' => '2026-01-01',
        ]);

        return [$employee, $monToSat, $monToFri];
    }

    public function test_schedule_on_returns_the_one_in_force_or_the_base(): void
    {
        [$employee, $monToSat, $monToFri] = $this->base();

        // Assign Mon-Fri only for July
        $employee->scheduleAssignments()->create([
            'schedule_id' => $monToFri->id, 'effective_from' => '2026-07-01', 'effective_to' => '2026-07-31',
        ]);

        // In July → Mon-Fri schedule; outside July → falls back to the base Mon-Sat
        $this->assertSame($monToFri->id, $employee->scheduleOn('2026-07-10')->id);
        $this->assertSame($monToSat->id, $employee->scheduleOn('2026-06-10')->id);
        $this->assertSame($monToSat->id, $employee->scheduleOn('2026-08-10')->id);
    }

    public function test_saturday_is_a_workday_or_not_depending_on_the_active_schedule(): void
    {
        Carbon::setTestNow('2026-08-01 23:00:00');
        [$employee, , $monToFri] = $this->base();

        // For July the employee is on Mon-Fri (no Saturday)
        $employee->scheduleAssignments()->create([
            'schedule_id' => $monToFri->id, 'effective_from' => '2026-07-01', 'effective_to' => '2026-07-31',
        ]);

        // 2026-07-04 and 2026-07-11 are Saturdays → NOT absences under Mon-Fri
        $july = $employee->periodBreakdown(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'))
            ->keyBy(fn ($d) => $d['date']->toDateString());
        $this->assertArrayNotHasKey('2026-07-04', $july->all());
        $this->assertArrayNotHasKey('2026-07-11', $july->all());

        // 2026-06-06 is a Saturday under the base Mon-Sat schedule → IS an absence
        $june = $employee->periodBreakdown(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'))
            ->keyBy(fn ($d) => $d['date']->toDateString());
        $this->assertSame('ABSENT', $june['2026-06-06']['status']);
    }

    public function test_the_form_saves_and_replaces_the_periods(): void
    {
        [$employee, $monToSat, $monToFri] = $this->base();
        $admin = User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail();
        $site = Site::first();
        $employee->update(['site_id' => $site->id]);

        $payload = [
            'document_type' => 'DNI', 'document_number' => '11112222',
            'first_name' => 'J', 'last_name' => 'D',
            'site_id' => $site->id, 'schedule_id' => $monToSat->id,
            'vacation_days_per_year' => 30, 'is_active' => 1,
            'schedule_periods' => [
                ['schedule_id' => $monToFri->id, 'from' => '2026-07-01', 'to' => '2026-07-31'],
                ['schedule_id' => $monToSat->id, 'from' => '2026-08-01', 'to' => ''], // open-ended
                ['schedule_id' => '', 'from' => '', 'to' => ''],                       // empty: ignored
            ],
        ];

        $this->actingAs($admin)->put("/employees/{$employee->getRouteKey()}", $payload)
            ->assertRedirect(route('employees.index'));

        $saved = $employee->scheduleAssignments()->orderBy('effective_from')->get();
        $this->assertCount(2, $saved); // the empty row was dropped
        $this->assertSame($monToFri->id, $saved[0]->schedule_id);
        $this->assertSame('2026-07-31', $saved[0]->effective_to->toDateString());
        $this->assertNull($saved[1]->effective_to); // open-ended kept as null

        // Saving again REPLACES (no duplicates)
        $this->actingAs($admin)->put("/employees/{$employee->getRouteKey()}", $payload);
        $this->assertCount(2, $employee->scheduleAssignments()->get());
    }

    public function test_a_period_with_a_schedule_but_no_from_date_is_rejected(): void
    {
        [$employee, $monToSat, $monToFri] = $this->base();
        $admin = User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail();
        $site = Site::first();
        $employee->update(['site_id' => $site->id]);

        $payload = [
            'document_type' => 'DNI', 'document_number' => '11112222',
            'first_name' => 'J', 'last_name' => 'D',
            'site_id' => $site->id, 'schedule_id' => $monToSat->id,
            'vacation_days_per_year' => 30, 'is_active' => 1,
            'schedule_periods' => [
                ['schedule_id' => $monToFri->id, 'from' => '', 'to' => ''], // schedule, no "From"
            ],
        ];

        $this->actingAs($admin)->put("/employees/{$employee->getRouteKey()}", $payload)
            ->assertSessionHasErrors('schedule_periods');

        // Nothing was silently saved
        $this->assertCount(0, $employee->scheduleAssignments()->get());
    }
}
