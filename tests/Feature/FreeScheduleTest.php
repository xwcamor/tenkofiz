<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceMark;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Free" schedule mode: no schedule enforcement at all. Every kiosk mark is just a
 * logged capture (with its anti-fraud evidence). The system judges no tardiness,
 * no absence and no hours, and allows any number of marks per day — for health,
 * field or irregular work reviewed by a person.
 */
class FreeScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail();
    }

    /** A saved Free-type schedule (no days), plus a markable employee on it. */
    private function freeEmployee(): Employee
    {
        $free = Schedule::create(['name' => 'Marca Libre', 'type' => Schedule::TYPE_FREE, 'tolerance_minutes' => 0]);

        return Employee::create([
            'document_number' => '44445555',
            'first_name' => 'MED', 'last_name' => 'IC',
            'schedule_id' => $free->id,
            'face_descriptor' => json_encode([array_fill(0, 128, 0.1)]), // enrolled → DNI fallback allowed
        ]);
    }

    public function test_free_schedule_has_no_days_and_expects_nothing(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('schedules.store'), [
            'name' => 'Turno Libre Salud', 'type' => 'free',
        ])->assertRedirect(route('schedules.index'));

        $schedule = Schedule::firstWhere('name', 'Turno Libre Salud');
        $this->assertTrue($schedule->isFree());
        $this->assertFalse($schedule->isFixed());
        $this->assertFalse($schedule->isFlexible());
        $this->assertSame(0, $schedule->days()->count());
        $this->assertSame(0, $schedule->expectedMinutesFor(1)); // Monday: nothing expected
    }

    public function test_free_employee_can_mark_many_times_in_the_same_day(): void
    {
        $this->admin();
        $employee = $this->freeEmployee();

        Carbon::setTestNow('2026-07-16 14:00:00'); // any time is fine in free mode

        foreach (range(1, 3) as $i) {
            $this->postJson('/kiosk/mark-dni', ['document_number' => '44445555'])
                ->assertOk()
                ->assertJsonPath('type', 'FREE');
        }

        // Three captures logged, but a single day container (no "already marked out")
        $this->assertSame(3, AttendanceMark::where('employee_id', $employee->id)->count());
        $this->assertSame(1, Attendance::where('employee_id', $employee->id)->count());

        // The day is stored as FREE (not a green "on time"), with no schedule quota,
        // so the report shows "LIBRE" and omits expected/worked hours.
        $day = Attendance::where('employee_id', $employee->id)->first();
        $this->assertSame(Attendance::STATUS_FREE, $day->status);
        $this->assertSame(0, (int) $day->expected_minutes);
        $this->assertTrue($day->isFreeMark());
    }

    public function test_free_days_never_become_absences(): void
    {
        Carbon::setTestNow('2026-07-31 23:00:00');
        $this->admin();
        $employee = $this->freeEmployee();
        $employee->update(['hire_date' => '2026-07-01']);

        // A full month with zero marks on a free schedule → no derived faltas.
        $breakdown = $employee->periodBreakdown(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'));
        $this->assertSame(0, $breakdown->where('status', 'ABSENT')->count());
    }
}
