<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Site;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KioskFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function makeEmployee(array $extra = []): Employee
    {
        $schedule = Schedule::withoutGlobalScopes()->first();

        return Employee::withoutGlobalScopes()->create(array_merge([
            'company_id' => $schedule->company_id,
            'document_number' => '55667788',
            'first_name' => 'Flujo', 'last_name' => 'KIOSCO',
            'schedule_id' => $schedule->id,
        ], $extra));
    }

    public function test_landing_page_shows_the_keypad_without_camera(): void
    {
        $this->get('/kiosk')
            ->assertOk()
            ->assertSee(__('Type your document number'))
            ->assertDontSee('id="video"', false); // no camera on the landing page
    }

    public function test_lookup_validates_the_document_and_prepares_the_camera_step(): void
    {
        $this->makeEmployee();

        $this->postJson('/kiosk/lookup', ['document_number' => '55667788'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('redirect', route('kiosk.verify'));

        $this->assertSame('55667788', session('kiosk_verify_doc'));
    }

    public function test_lookup_rejects_unknown_documents(): void
    {
        $this->postJson('/kiosk/lookup', ['document_number' => '00000001'])
            ->assertStatus(404)
            ->assertJsonPath('ok', false);

        $this->assertNull(session('kiosk_verify_doc'));
    }

    public function test_lookup_is_scoped_to_the_kiosk_site(): void
    {
        $employee = $this->makeEmployee();
        $siteA = Site::withoutGlobalScopes()->where('company_id', $employee->company_id)->first();
        $siteB = Site::withoutGlobalScopes()->create(['company_id' => $employee->company_id, 'name' => 'Otra Sede']);
        $employee->update(['site_id' => $siteB->id]);

        // Kiosk of site A cannot see a site-B employee
        $this->get('/kiosk?site='.$siteA->id)->assertOk();
        $this->postJson('/kiosk/lookup', ['document_number' => '55667788'])->assertStatus(404);

        // Kiosk of their own site finds them
        $this->get('/kiosk?site='.$siteB->id)->assertOk();
        $this->postJson('/kiosk/lookup', ['document_number' => '55667788'])->assertOk();
    }

    public function test_verify_page_requires_a_validated_document(): void
    {
        // Direct access without going through the keypad: back to the landing
        $this->get('/kiosk/verify')->assertRedirect(route('kiosk'));

        $this->makeEmployee();
        $this->postJson('/kiosk/lookup', ['document_number' => '55667788'])->assertOk();

        $this->get('/kiosk/verify')
            ->assertOk()
            ->assertSee('Flujo')
            ->assertSee('id="video"', false); // the camera lives on THIS page
    }

    public function test_verify_page_expires_after_the_session_window(): void
    {
        $this->makeEmployee();
        $this->postJson('/kiosk/lookup', ['document_number' => '55667788'])->assertOk();

        session(['kiosk_verify_until' => now()->subMinute()->timestamp]);

        $this->get('/kiosk/verify')->assertRedirect(route('kiosk'));
    }

    public function test_enroll_page_renders_with_pin_step(): void
    {
        $this->get('/kiosk/enroll')
            ->assertOk()
            ->assertSee(__('Supervisor PIN'));
    }

    // ---------- Calibration is core: super-only, never in company Settings ----------

    public function test_super_admin_updates_recognition_calibration_from_the_console(): void
    {
        $super = \App\Models\User::withoutGlobalScopes()->where('is_super_admin', true)->first();
        $company = \App\Models\Company::first();

        $this->actingAs($super)->put(route('admin.companies.recognition', $company), [
            'kiosk_face_threshold' => 0.45,
            'kiosk_verify_seconds' => 20,
        ])->assertSessionHas('ok');

        $setting = \App\Models\Setting::withoutGlobalScopes()->where('company_id', $company->id)->first();
        $this->assertSame(0.45, $setting->kiosk_face_threshold);
        $this->assertSame(20, $setting->kiosk_verify_seconds);
    }

    public function test_company_admin_cannot_touch_recognition_calibration(): void
    {
        $admin = \App\Models\User::withoutGlobalScopes()->where('email', 'admin@test.com')->first();
        $company = \App\Models\Company::find($admin->company_id);
        $before = \App\Models\Setting::withoutGlobalScopes()->where('company_id', $company->id)->first();

        // The console route is super-only
        $this->actingAs($admin)->put(route('admin.companies.recognition', $company), [
            'kiosk_face_threshold' => 0.65,
            'kiosk_verify_seconds' => 60,
        ])->assertForbidden();

        // And their own Settings form silently ignores the calibration fields
        $this->actingAs($admin)->put('/settings', [
            'company_name' => 'MI EMPRESA S.A.C.',
            'timezone' => 'America/Lima',
            'country' => 'PE',
            'locale' => 'es',
            'kiosk_face_threshold' => 0.65,
            'kiosk_verify_seconds' => 60,
        ])->assertSessionHas('ok');

        $after = \App\Models\Setting::withoutGlobalScopes()->where('company_id', $company->id)->first();
        $this->assertSame((float) ($before->kiosk_face_threshold ?? 0.5), (float) ($after->kiosk_face_threshold ?? 0.5));
        $this->assertSame($before->kiosk_verify_seconds, $after->kiosk_verify_seconds);
    }

    // ---------- Worked-hours clamping to the schedule ----------

    public function test_worked_minutes_are_clamped_to_the_schedule(): void
    {
        $schedule = Schedule::withoutGlobalScopes()->first(); // General schedule 08:00–17:00
        $shift = $schedule->worksOn(4); // Thursday
        $employee = $this->makeEmployee();

        $attendance = \App\Models\Attendance::withoutGlobalScopes()->create([
            'employee_id' => $employee->id,
            'date' => '2026-07-16', // Thursday
            'check_in' => '06:00:00', // marked 2h early
            'check_out' => '18:00:00', // left 1h late
            'status' => 'ON_TIME',
            'method' => 'FACIAL',
        ]);

        $this->assertSame(720, $attendance->workedMinutes());        // raw = 12h
        $this->assertSame(540, $attendance->workedMinutes($shift));  // clamped 08–17 = 9h
    }

    public function test_every_mark_is_appended_to_the_raw_punch_log(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-16 14:30:00'); // Thursday
        $employee = $this->makeEmployee(['face_descriptor' => json_encode([array_fill(0, 128, 0.1)])]);

        // Check-in
        $this->postJson('/kiosk/mark-dni', ['document_number' => '55667788'])->assertOk();
        // Check-out (past the minimum interval)
        \Carbon\Carbon::setTestNow('2026-07-16 18:00:00');
        $this->postJson('/kiosk/mark-dni', ['document_number' => '55667788'])->assertOk();

        $marks = \App\Models\AttendanceMark::withoutGlobalScopes()->where('employee_id', $employee->id)->orderBy('marked_at')->get();
        $this->assertCount(2, $marks); // both punches logged, additively
        $this->assertSame(['CHECK_IN', 'CHECK_OUT'], $marks->pluck('kind')->all());
        $this->assertNotNull($marks->first()->attendance_id); // linked to the day
    }

    public function test_expected_minutes_are_frozen_on_check_in(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-16 14:30:00'); // Thursday, General schedule 08:00–17:00 (9h)
        $employee = $this->makeEmployee(['face_descriptor' => json_encode([array_fill(0, 128, 0.1)])]);

        $this->postJson('/kiosk/mark-dni', ['document_number' => '55667788'])->assertOk();

        $attendance = \App\Models\Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->first();
        $this->assertSame(540, $attendance->expected_minutes); // 9h frozen at check-in

        // Changing the schedule later must NOT rewrite the frozen expectation
        $employee->schedule->days()->update(['end_time' => '13:00:00']); // now a 5h shift
        $this->assertSame(540, $attendance->fresh()->expected_minutes);
    }

    public function test_expected_minutes_reflect_the_schedule_type(): void
    {
        $fixed = Schedule::withoutGlobalScopes()->first(); // General 08:00–17:00 = 9h
        $this->assertSame(540, $fixed->expectedMinutesFor(4)); // Thursday

        $flexible = Schedule::withoutGlobalScopes()->create([
            'company_id' => $fixed->company_id, 'name' => 'Flex '.uniqid(),
            'type' => Schedule::TYPE_FLEXIBLE, 'tolerance_minutes' => 0, 'target_minutes' => 240,
        ]);
        $flexible->days()->create(['weekday' => 4, 'start_time' => '00:00:00', 'end_time' => '00:00:00']);
        $flexible->load('days');
        $this->assertSame(240, $flexible->expectedMinutesFor(4)); // target, not shift length
    }

    // ---------- Keypad pre-check: fail fast before the camera ----------

    public function test_lookup_warns_when_it_is_too_early_to_check_in(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-16 10:00:00'); // Thursday 05:00 Lima; shift starts 08:00
        $employee = $this->makeEmployee();
        \App\Models\Setting::forCompany($employee->company_id)->update(['early_check_in_minutes' => 120]); // earliest 06:00 Lima

        $this->postJson('/kiosk/lookup', ['document_number' => '55667788'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        // Rejected at the keypad: never sent to the camera
        $this->assertNull(session('kiosk_verify_doc'));
    }

    public function test_lookup_allows_marking_inside_the_window(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-16 12:30:00'); // Thursday 07:30 Lima; inside the 06:00+ window
        $employee = $this->makeEmployee();
        \App\Models\Setting::forCompany($employee->company_id)->update(['early_check_in_minutes' => 120]);

        $this->postJson('/kiosk/lookup', ['document_number' => '55667788'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('55667788', session('kiosk_verify_doc'));
    }

    // ---------- Flexible schedule (by hours, no tardiness) ----------

    private function makeFlexibleEmployee(): Employee
    {
        $schedule = Schedule::withoutGlobalScopes()->create([
            'company_id' => Schedule::withoutGlobalScopes()->first()->company_id,
            'name' => 'Flexible '.uniqid(),
            'type' => Schedule::TYPE_FLEXIBLE,
            'tolerance_minutes' => 0,
            'target_minutes' => 480, // 8h/day
        ]);
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            $schedule->days()->create(['weekday' => $weekday, 'start_time' => '00:00:00', 'end_time' => '00:00:00']);
        }

        return $this->makeEmployee(['schedule_id' => $schedule->id, 'face_descriptor' => json_encode([array_fill(0, 128, 0.1)])]);
    }

    public function test_flexible_schedule_never_marks_tardiness(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-16 20:00:00'); // Thursday 15:00 Lima — very "late" for a fixed shift
        $this->makeFlexibleEmployee();

        $this->postJson('/kiosk/mark-dni', ['document_number' => '55667788'])
            ->assertOk()
            ->assertJsonPath('status', 'ON_TIME'); // flexible: no tardiness ever
    }

    public function test_flexible_schedule_ignores_the_early_check_in_window(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-16 09:00:00'); // Thursday 04:00 Lima — extremely early
        $employee = $this->makeFlexibleEmployee();
        \App\Models\Setting::forCompany($employee->company_id)->update(['early_check_in_minutes' => 120]);

        // A fixed schedule would be rejected this early; flexible has no start to be early against
        $this->postJson('/kiosk/lookup', ['document_number' => '55667788'])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    // ---------- Rule (Carlos): document marking only for enrolled faces ----------

    public function test_document_marking_is_rejected_for_employees_without_an_enrolled_face(): void
    {
        $this->makeEmployee(); // no face_descriptor

        $this->postJson('/kiosk/mark-dni', ['document_number' => '55667788'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertDatabaseMissing('attendances', ['date' => now()->toDateString()]);
    }

    public function test_document_marking_works_as_fallback_for_enrolled_employees(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-16 14:30:00'); // Thursday, working hours
        $this->makeEmployee(['face_descriptor' => json_encode([array_fill(0, 128, 0.1)])]);

        $this->postJson('/kiosk/mark-dni', ['document_number' => '55667788'])->assertOk();
        \Carbon\Carbon::setTestNow();
    }

    public function test_verify_page_hides_the_document_button_for_non_enrolled(): void
    {
        $this->makeEmployee();
        $this->postJson('/kiosk/lookup', ['document_number' => '55667788'])->assertOk();

        $this->get('/kiosk/verify')
            ->assertOk()
            ->assertDontSee('id="markDocBtn"', false)   // no document fallback offered
            ->assertSee('id="enrollCard"', false);      // enrolling is the path forward
    }

    // ---------- Open self-enrollment (no PIN configured) ----------

    public function test_self_enrollment_without_pin_works_for_the_validated_person(): void
    {
        $employee = $this->makeEmployee();
        $this->assertNull(app_setting()->kiosk_enroll_pin);

        // Validated on the keypad first (kiosk_verify_doc in session)
        $this->postJson('/kiosk/lookup', ['document_number' => '55667788'])->assertOk();

        $this->postJson('/kiosk/enroll/descriptor', [
            'employee_id' => $employee->id,
            'consent' => true,
            'descriptors' => [array_fill(0, 128, 0.2)],
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertNotNull($employee->fresh()->face_descriptor);
    }

    public function test_self_enrollment_without_pin_is_rejected_for_a_different_person(): void
    {
        $this->makeEmployee();
        $other = $this->makeEmployee(['document_number' => '99001122', 'first_name' => 'Otro']);

        // The keypad validated 55667788, but the payload targets someone else
        $this->postJson('/kiosk/lookup', ['document_number' => '55667788'])->assertOk();

        $this->postJson('/kiosk/enroll/descriptor', [
            'employee_id' => $other->id,
            'consent' => true,
            'descriptors' => [array_fill(0, 128, 0.2)],
        ])->assertForbidden();
    }
}
