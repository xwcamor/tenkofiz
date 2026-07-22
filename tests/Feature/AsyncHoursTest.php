<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Async/credited hours (education vertical, opt-in). With the flag OFF nothing
 * changes; with it ON the async minutes are added to worked & expected (never a
 * deficit), so a 4h presencial day + 1h async reports as 5h.
 */
class AsyncHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function setup4hPlus1hAsync(): array
    {
        Carbon::setTestNow('2026-03-20 12:00:00'); // after the marked days
        $this->seed(DatabaseSeeder::class);
        $admin = User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail();

        // Mon/Tue/Wed 09:00-13:00 (4h presencial) + 60 async min/day
        $sch = Schedule::create(['name' => 'Docente', 'type' => 'fixed', 'tolerance_minutes' => 10, 'async_minutes_per_day' => 60]);
        foreach ([1, 2, 3] as $wd) {
            $sch->days()->create(['weekday' => $wd, 'start_time' => '09:00', 'end_time' => '13:00']);
        }
        $emp = Employee::create(['document_number' => '11112222', 'first_name' => 'C', 'last_name' => 'M', 'schedule_id' => $sch->id, 'hire_date' => '2026-03-01']);

        // One presencial day fully worked (Mon 2026-03-16, 09:00-13:00 = 4h)
        Attendance::create(['employee_id' => $emp->id, 'date' => '2026-03-16', 'check_in' => '09:00', 'check_out' => '13:00', 'status' => 'ON_TIME', 'method' => 'FACIAL', 'expected_minutes' => 240]);

        return [$admin, $emp];
    }

    private function sheetSummary(User $admin, Employee $emp): array
    {
        return $this->actingAs($admin)
            ->get("/reports/sheet/{$emp->getRouteKey()}?from=2026-03-16&to=2026-03-16")
            ->assertOk()->viewData('summary');
    }

    public function test_async_off_by_default_changes_nothing(): void
    {
        [$admin, $emp] = $this->setup4hPlus1hAsync();
        // Flag is off by default → only the 4h presencial count
        $s = $this->sheetSummary($admin, $emp);
        $this->assertSame('4:00', $s['hours']);
        $this->assertSame('4:00', $s['expected_hours']);
        $this->assertFalse($s['async_enabled']);
    }

    public function test_async_on_credits_the_extra_hour(): void
    {
        [$admin, $emp] = $this->setup4hPlus1hAsync();
        Setting::forCompany($admin->company_id)->update(['async_hours_enabled' => true]);

        $s = $this->sheetSummary($admin, $emp);
        // 4h presencial + 1h async = 5h worked AND expected; still no debt
        $this->assertSame('5:00', $s['hours']);
        $this->assertSame('5:00', $s['expected_hours']);
        $this->assertSame(0, $s['debt_minutes']);
        $this->assertTrue($s['async_enabled']);
        $this->assertSame('1:00', $s['async_hours']);
    }
}
