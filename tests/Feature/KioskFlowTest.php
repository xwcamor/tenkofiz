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
