<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Vacation;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulesAndBalanceTest extends TestCase
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

            'face_descriptor' => json_encode([array_fill(0, 128, 0.1)]), // enrolled: required for document fallback
            'first_name' => 'JOHN',
            'last_name' => 'DOE',
            'schedule_id' => Schedule::first()->id, // Morning Shift, Mon-Sat 08:00-17:00
        ]);
    }

    // ---------- Weekly schedules ----------

    public function test_mark_absences_skips_non_working_weekday(): void
    {
        $this->seedBase();

        // 2026-07-19 is a Sunday: the seeded schedule has no Sunday row
        $this->assertSame(0, Attendance::markAbsences('2026-07-19'));
        // 2026-07-16 is a Thursday: one absence expected
        $this->assertSame(1, Attendance::markAbsences('2026-07-16'));
    }

    public function test_kiosk_lateness_uses_the_weekday_hours(): void
    {
        $employee = $this->seedBase();

        // Thursday 09:30 company time (Lima = UTC-5) → 14:30 UTC. Start 08:00 + 10 tol → LATE
        Carbon::setTestNow('2026-07-16 14:30:00');

        $this->postJson('/kiosk/mark-dni', ['document_number' => '11112222'])
            ->assertOk()
            ->assertJsonPath('status', 'LATE');
    }

    public function test_overnight_shift_checkout_closes_previous_day(): void
    {
        $this->seed(DatabaseSeeder::class);

        $night = Schedule::create(['name' => 'Night Shift', 'tolerance_minutes' => 10]);
        foreach (range(1, 6) as $weekday) {
            $night->days()->create(['weekday' => $weekday, 'start_time' => '22:00:00', 'end_time' => '06:00:00']);
        }
        $employee = Employee::create([
            'document_number' => '55556666',

            'face_descriptor' => json_encode([array_fill(0, 128, 0.1)]), // enrolled: required for document fallback
            'first_name' => 'NIGHT',
            'last_name' => 'OWL',
            'schedule_id' => $night->id,
        ]);

        // Thursday 22:05 Lima (Fri 03:05 UTC): check-in
        Carbon::setTestNow('2026-07-17 03:05:00');
        $this->postJson('/kiosk/mark-dni', ['document_number' => '55556666'])
            ->assertOk()->assertJsonPath('type', 'CHECK_IN');

        // Friday 06:02 Lima (11:02 UTC): closes THURSDAY's attendance as check-out
        Carbon::setTestNow('2026-07-17 11:02:00');
        $this->postJson('/kiosk/mark-dni', ['document_number' => '55556666'])
            ->assertOk()->assertJsonPath('type', 'CHECK_OUT');

        $attendance = Attendance::where('employee_id', $employee->id)->first();
        $this->assertSame('2026-07-16', $attendance->date->toDateString());
        $this->assertNotNull($attendance->check_out);
        $this->assertSame(1, Attendance::where('employee_id', $employee->id)->count());
    }

    // ---------- Vacation balance ----------

    public function test_request_over_balance_is_rejected(): void
    {
        $employee = $this->seedBase();
        $employee->update(['vacation_days_per_year' => 10]);
        Carbon::setTestNow('2026-07-17 15:00:00');

        // 11 days > 10 allowance
        $this->actingAs(User::first())
            ->post('/vacations', [
                'employee_id' => $employee->id,
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-11',
                'reason' => 'Too long',
            ])
            ->assertSessionHasErrors('end_date');

        $this->assertSame(0, Vacation::count());
    }

    public function test_approval_rechecks_the_balance(): void
    {
        $employee = $this->seedBase();
        $employee->update(['vacation_days_per_year' => 10]);
        Carbon::setTestNow('2026-07-17 15:00:00');
        $admin = User::first();

        // Two competing 7-day requests: both fit alone, not together
        $first = Vacation::create(['employee_id' => $employee->id, 'start_date' => '2026-08-01', 'end_date' => '2026-08-07', 'days' => 7, 'reason' => 'A']);
        $second = Vacation::create(['employee_id' => $employee->id, 'start_date' => '2026-09-01', 'end_date' => '2026-09-07', 'days' => 7, 'reason' => 'B']);

        $this->actingAs($admin)->patch("/vacations/{$first->id}/status", ['status' => 'APPROVED']);
        $this->assertSame('APPROVED', $first->fresh()->status);

        $this->actingAs($admin)->patch("/vacations/{$second->id}/status", ['status' => 'APPROVED']);
        $this->assertSame('PENDING', $second->fresh()->status); // blocked: only 3 days left

        $this->assertSame(3, $employee->fresh()->remainingVacationDays(2026));
    }

    // ---------- Maintenance commands ----------

    public function test_purge_evidence_removes_old_photos(): void
    {
        $employee = $this->seedBase();

        $dir = public_path('uploads/kiosk_evidence');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents("{$dir}/old_test.jpg", 'x');

        Attendance::create([
            'employee_id' => $employee->id,
            'date' => company_now()->subDays(120)->toDateString(),
            'status' => 'ON_TIME', 'method' => 'DNI',
            'evidence_photo' => 'uploads/kiosk_evidence/old_test.jpg',
        ]);

        $this->artisan('kiosk:purge-evidence --days=90')->assertSuccessful();

        $this->assertFileDoesNotExist("{$dir}/old_test.jpg");
        $this->assertNull(Attendance::first()->evidence_photo);
    }

    public function test_backup_creates_a_zip(): void
    {
        $this->seedBase();

        $this->artisan('system:backup --keep=2')->assertSuccessful();

        $backups = glob(storage_path('app/backups/backup_*.zip'));
        $this->assertNotEmpty($backups);
        array_map('unlink', $backups);
    }
}
