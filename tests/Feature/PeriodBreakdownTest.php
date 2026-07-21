<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Schedule;
use App\Models\Vacation;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The report sheet DERIVES absences day by day so it is complete even if the
 * nightly absence job never ran — while respecting vacations, holidays, days off
 * and the hire/termination window (so nobody accrues faltas they shouldn't).
 */
class PeriodBreakdownTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employee(array $attrs = []): Employee
    {
        $this->seed(DatabaseSeeder::class);

        return Employee::create(array_merge([
            'document_number' => '11112222',
            'first_name' => 'JOHN',
            'last_name' => 'DOE',
            'schedule_id' => Schedule::first()->id, // Mon–Sat 08:00–17:00
        ], $attrs));
    }

    public function test_days_without_a_record_become_derived_absences(): void
    {
        Carbon::setTestNow('2026-07-20 23:00:00'); // Monday
        $employee = $this->employee(['hire_date' => '2026-07-13']);

        // One real mark on Wed 15; the rest of that week's working days are empty
        Attendance::create(['employee_id' => $employee->id, 'date' => '2026-07-15', 'check_in' => '08:00', 'check_out' => '17:00', 'status' => 'ON_TIME', 'method' => 'FACIAL']);

        $break = $employee->periodBreakdown(Carbon::parse('2026-07-13'), Carbon::parse('2026-07-19'));

        // Mon–Sat = 6 working days (Sunday 19 is off). 1 real + 5 derived absences.
        $this->assertCount(6, $break);
        $this->assertSame(5, $break->where('status', 'ABSENT')->count());
        $this->assertSame(1, $break->where('status', 'ON_TIME')->count());
        // Sunday is never in the list (day off)
        $this->assertTrue($break->every(fn ($d) => $d['date']->dayOfWeek !== Carbon::SUNDAY));
    }

    public function test_vacations_holidays_and_days_off_are_not_absences(): void
    {
        Carbon::setTestNow('2026-07-31 23:00:00');
        $employee = $this->employee(['hire_date' => '2026-07-01']);

        // Approved vacation Mon 20 – Wed 22
        Vacation::create(['employee_id' => $employee->id, 'start_date' => '2026-07-20', 'end_date' => '2026-07-22', 'days' => 3, 'reason' => 'x', 'status' => 'APPROVED']);
        // A holiday on Thu 23
        Holiday::firstOrCreate(['date' => '2026-07-23'], ['name' => 'Test holiday']);

        $break = $employee->periodBreakdown(Carbon::parse('2026-07-20'), Carbon::parse('2026-07-25'))->keyBy(fn ($d) => $d['date']->toDateString());

        $this->assertSame('VACATION', $break['2026-07-20']['status']);
        $this->assertSame('VACATION', $break['2026-07-22']['status']);
        $this->assertArrayNotHasKey('2026-07-23', $break->all()); // holiday: skipped
        $this->assertArrayNotHasKey('2026-07-19', $break->all()); // Sunday: never present
        $this->assertSame('ABSENT', $break['2026-07-24']['status']); // plain empty working day
    }

    public function test_no_absences_before_hire_or_after_termination_or_in_the_future(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00'); // Wednesday
        $employee = $this->employee(['hire_date' => '2026-07-08', 'termination_date' => '2026-07-10', 'is_active' => false]);

        // Ask for a wide window: only Wed 8, Thu 9, Fri 10 are inside [hire, termination]
        $break = $employee->periodBreakdown(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'));

        $this->assertSame(3, $break->count());
        $this->assertTrue($break->every(fn ($d) => $d['date']->between(Carbon::parse('2026-07-08'), Carbon::parse('2026-07-10'))));
        // Nothing after the termination date, nothing before hire, nothing in the future
        $this->assertTrue($break->every(fn ($d) => $d['date']->lte(Carbon::parse('2026-07-10'))));
    }

    public function test_future_days_are_not_counted_as_absences(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00'); // Wednesday, mid-period
        $employee = $this->employee(['hire_date' => '2026-07-01']);

        $break = $employee->periodBreakdown(Carbon::parse('2026-07-13'), Carbon::parse('2026-07-19'));

        // Only Mon 13 – Wed 15 have passed; Thu 16 onward is the future → excluded
        $this->assertSame(3, $break->count());
        $this->assertTrue($break->every(fn ($d) => $d['date']->lte(Carbon::parse('2026-07-15'))));
    }
}
