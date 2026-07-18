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

    public function test_verify_seconds_setting_is_saved_from_settings(): void
    {
        $admin = \App\Models\User::withoutGlobalScopes()->where('email', 'admin@test.com')->first();

        $this->actingAs($admin)->put('/settings', [
            'company_name' => 'MI EMPRESA S.A.C.',
            'timezone' => 'America/Lima',
            'country' => 'PE',
            'kiosk_face_threshold' => 0.5,
            'kiosk_verify_seconds' => 25,
        ])->assertSessionHas('ok');

        $this->assertSame(25, app_setting()->fresh()->kiosk_verify_seconds);
    }
}
