<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Justification;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two employee-form conveniences:
 *  - "+ New schedule": quick-create a fixed schedule without leaving the page.
 *  - Justifications list flags days that fall in an already-closed payroll period
 *    (retroactive), so HR handles them as an adjustment, not a change to what was paid.
 */
class ScheduleQuickAndRetroTest extends TestCase
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

    public function test_quick_store_creates_a_reusable_fixed_schedule(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/schedules-quick', [
            'name' => 'Lu-Mi-Vi 9 a 5',
            'weekdays' => [1, 3, 5], // Mon, Wed, Fri
            'start' => '09:00',
            'end' => '17:00',
            'tolerance_minutes' => 15,
        ])->assertOk()->assertJsonStructure(['id', 'name']);

        $schedule = Schedule::where('name', 'Lu-Mi-Vi 9 a 5')->first();
        $this->assertNotNull($schedule);
        $this->assertSame(3, $schedule->days()->count());
        $this->assertTrue($schedule->worksOn(1) && $schedule->worksOn(3) && $schedule->worksOn(5));
        $this->assertNull($schedule->worksOn(2)); // Tuesday not selected
        $this->assertSame(15, $schedule->tolerance_minutes);

        // Same name twice → rejected (stays a clean shared catalog)
        $this->actingAs($admin)->postJson('/schedules-quick', [
            'name' => 'Lu-Mi-Vi 9 a 5', 'weekdays' => [1], 'start' => '09:00', 'end' => '17:00',
        ])->assertStatus(422);
    }

    public function test_quick_store_requires_days_and_a_valid_range(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/schedules-quick', [
            'name' => 'Bad', 'weekdays' => [], 'start' => '09:00', 'end' => '17:00',
        ])->assertStatus(422);

        $this->actingAs($admin)->postJson('/schedules-quick', [
            'name' => 'Bad2', 'weekdays' => [1], 'start' => '09:00', 'end' => '09:00',
        ])->assertStatus(422);
    }

    public function test_justifications_flag_a_closed_period_as_retroactive(): void
    {
        // Cut-off 19; "today" is 25 Aug → current open period is 20 Aug – 19 Sep.
        Carbon::setTestNow('2026-08-25 12:00:00');
        $admin = $this->admin();
        \App\Models\Setting::forCompany(current_company_id())->update(['cutoff_day' => 19]);

        $employee = Employee::create([
            'document_number' => '11112222', 'first_name' => 'J', 'last_name' => 'D',
            'schedule_id' => Schedule::first()->id,
        ]);

        // A justification for 30 June (a closed, already-paid period) and one for today
        Justification::create(['employee_id' => $employee->id, 'date' => '2026-06-30', 'reason' => 'old']);
        Justification::create(['employee_id' => $employee->id, 'date' => '2026-08-25', 'reason' => 'today']);

        $html = $this->actingAs($admin)->get('/justifications')->assertOk()->getContent();

        // The badge appears (for the June one); assert the label is present
        $this->assertStringContainsString(__('Retroactive · closed period'), $html);
    }
}
