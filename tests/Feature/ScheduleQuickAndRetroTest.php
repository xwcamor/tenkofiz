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

    public function test_quick_store_creates_a_fixed_schedule_with_per_day_hours(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/schedules-quick', [
            'name' => 'Lu-Mi-Vi horas distintas',
            'days' => [
                ['weekday' => 1, 'start' => '09:00', 'end' => '17:00'],
                ['weekday' => 3, 'start' => '09:00', 'end' => '17:00'],
                ['weekday' => 5, 'start' => '14:00', 'end' => '18:00'], // this day differs
            ],
            'tolerance_minutes' => 15,
        ])->assertOk()->assertJsonStructure(['id', 'name', 'days']);

        $schedule = Schedule::where('name', 'Lu-Mi-Vi horas distintas')->first();
        $this->assertNotNull($schedule);
        $this->assertSame(3, $schedule->days()->count());
        $this->assertNull($schedule->worksOn(2)); // Tuesday not selected
        $this->assertSame(15, $schedule->tolerance_minutes);
        // Each day keeps ITS OWN hours (the whole point of the fix)
        $this->assertSame('09:00', substr($schedule->worksOn(1)->start_time, 0, 5));
        $this->assertSame('14:00', substr($schedule->worksOn(5)->start_time, 0, 5));
        $this->assertSame('18:00', substr($schedule->worksOn(5)->end_time, 0, 5));

        // Same name twice → rejected (stays a clean shared catalog)
        $this->actingAs($admin)->postJson('/schedules-quick', [
            'name' => 'Lu-Mi-Vi horas distintas', 'days' => [['weekday' => 1, 'start' => '09:00', 'end' => '17:00']],
        ])->assertStatus(422);
    }

    public function test_quick_store_requires_days_and_a_valid_range(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/schedules-quick', [
            'name' => 'Bad', 'days' => [],
        ])->assertStatus(422);

        $this->actingAs($admin)->postJson('/schedules-quick', [
            'name' => 'Bad2', 'days' => [['weekday' => 1, 'start' => '09:00', 'end' => '09:00']],
        ])->assertStatus(422);
    }

    public function test_quick_update_edits_a_personalized_schedule_days_and_hours(): void
    {
        $admin = $this->admin();

        $id = $this->actingAs($admin)->postJson('/schedules-quick', [
            'name' => 'Curso Personalizado', 'is_shared' => 0,
            'days' => [['weekday' => 1, 'start' => '09:00', 'end' => '13:00']],
        ])->assertOk()->json('id');

        // Change the day AND the hours — this is what "no se puede modificar" was about
        $this->actingAs($admin)->putJson("/schedules-quick/$id", [
            'name' => 'Curso Personalizado',
            'days' => [
                ['weekday' => 2, 'start' => '14:00', 'end' => '18:00'],
                ['weekday' => 4, 'start' => '08:00', 'end' => '12:00'],
            ],
        ])->assertOk();

        $schedule = Schedule::withoutGlobalScopes()->find($id);
        $this->assertSame(2, $schedule->days()->count());
        $this->assertNull($schedule->worksOn(1));               // old day removed
        $this->assertSame('14:00', substr($schedule->worksOn(2)->start_time, 0, 5));
        $this->assertSame('08:00', substr($schedule->worksOn(4)->start_time, 0, 5));
    }

    public function test_quick_update_refuses_a_shared_catalog_schedule(): void
    {
        $admin = $this->admin();
        $shared = Schedule::shared()->firstOrFail(); // the seeded 'Horario General'

        $this->actingAs($admin)->putJson("/schedules-quick/{$shared->id}", [
            'name' => 'Secuestro', 'days' => [['weekday' => 1, 'start' => '09:00', 'end' => '17:00']],
        ])->assertStatus(403);
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
