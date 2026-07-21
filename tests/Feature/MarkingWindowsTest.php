<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Setting;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkingWindowsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedEmployee(): Employee
    {
        $this->seed(DatabaseSeeder::class);

        // Morning Shift: Mon-Sat 08:00-17:00 (Lima = UTC-5)
        return Employee::create([
            'document_number' => '11112222',

            'face_descriptor' => json_encode([array_fill(0, 128, 0.1)]), // enrolled: required for document fallback
            'first_name' => 'JOHN',
            'last_name' => 'DOE',
            'schedule_id' => Schedule::first()->id,
        ]);
    }

    private function mark(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/kiosk/mark-dni', ['document_number' => '11112222'] + $extra);
    }

    public function test_check_in_is_rejected_before_the_early_window(): void
    {
        $this->seedEmployee();
        Setting::instance()->update(['early_check_in_minutes' => 60]); // earliest 07:00

        // Thursday 06:30 Lima (11:30 UTC) → before the 07:00 window
        Carbon::setTestNow('2026-07-16 11:30:00');

        $this->mark()->assertStatus(422);
        $this->assertSame(0, Attendance::count());
    }

    public function test_check_in_is_allowed_inside_the_early_window(): void
    {
        $this->seedEmployee();
        Setting::instance()->update(['early_check_in_minutes' => 60]);

        // Thursday 07:30 Lima (12:30 UTC) → inside 07:00-08:00, on time
        Carbon::setTestNow('2026-07-16 12:30:00');

        $this->mark()->assertOk()->assertJsonPath('type', 'CHECK_IN')->assertJsonPath('status', 'ON_TIME');
    }

    public function test_zero_window_allows_marking_at_any_time(): void
    {
        $this->seedEmployee(); // default early_check_in_minutes = 0

        // Thursday 04:00 Lima (09:00 UTC) → hours before the shift, still allowed
        Carbon::setTestNow('2026-07-16 09:00:00');

        $this->mark()->assertOk()->assertJsonPath('type', 'CHECK_IN');
    }

    public function test_early_departure_is_flagged_but_not_blocked(): void
    {
        $employee = $this->seedEmployee();
        Setting::instance()->update(['early_departure_minutes' => 15]);

        // Check in Thursday 08:00 Lima (13:00 UTC)
        Carbon::setTestNow('2026-07-16 13:00:00');
        $this->mark()->assertOk()->assertJsonPath('type', 'CHECK_IN');

        // Check out 15:00 Lima (20:00 UTC) → 120 min before the 17:00 end. It is
        // early, so the kiosk asks to confirm; confirmed, it records (and flags it).
        Carbon::setTestNow('2026-07-16 20:00:00');
        $this->mark()->assertStatus(422)->assertJsonPath('confirm_out', true);
        $this->mark(['confirm_out' => 1])->assertOk()->assertJsonPath('type', 'CHECK_OUT');

        $note = Attendance::where('employee_id', $employee->id)->value('note');
        $this->assertNotNull($note);
        $this->assertStringContainsString('120', $note);
    }

    public function test_leaving_at_the_scheduled_end_is_not_flagged(): void
    {
        $employee = $this->seedEmployee();
        Setting::instance()->update(['early_departure_minutes' => 15]);

        Carbon::setTestNow('2026-07-16 13:00:00'); // in 08:00
        $this->mark()->assertOk();

        Carbon::setTestNow('2026-07-16 22:00:00'); // out 17:00 exactly
        $this->mark()->assertOk()->assertJsonPath('type', 'CHECK_OUT');

        $this->assertNull(Attendance::where('employee_id', $employee->id)->value('note'));
    }
}
