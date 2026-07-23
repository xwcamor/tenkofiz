<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Setting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KioskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Pin the clock to a known working weekday (Thursday, inside the General
        // 08:00–17:00 shift, not a holiday). Without this the mark tests run on the
        // real date and break whenever "today" is a holiday or non-working day.
        \Carbon\Carbon::setTestNow('2026-07-16 14:30:00');
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedBase(): Employee
    {
        $this->seed(DatabaseSeeder::class);
        \App\Models\Setting::query()->update(['early_check_in_minutes' => 0]); // no time-window interference

        return Employee::create([
            'document_number' => '11112222',

            'face_descriptor' => json_encode([array_fill(0, 128, 0.1)]), // enrolled: required for document fallback
            'first_name' => 'JOHN',
            'last_name' => 'DOE',
            'schedule_id' => Schedule::first()->id,
        ]);
    }

    // ---------- DNI fallback marking ----------

    public function test_mark_by_dni_creates_attendance_with_evidence(): void
    {
        $employee = $this->seedBase();

        $photo = 'data:image/jpeg;base64,'.base64_encode(str_repeat('x', 400));

        $response = $this->postJson('/kiosk/mark-dni', [
            'document_number' => '11112222',

            'face_descriptor' => json_encode([array_fill(0, 128, 0.1)]), // enrolled: required for document fallback
            'photo' => $photo,
        ]);

        $response->assertOk()->assertJsonPath('ok', true)->assertJsonPath('type', 'CHECK_IN');

        $attendance = Attendance::where('employee_id', $employee->id)->first();
        $this->assertSame('DNI', $attendance->method);
        $this->assertNotNull($attendance->evidence_photo);
        $this->assertFileExists(public_path($attendance->evidence_photo));

        @unlink(public_path($attendance->evidence_photo));
    }

    public function test_mark_by_dni_rejects_unknown_document(): void
    {
        $this->seedBase();

        $this->postJson('/kiosk/mark-dni', ['document_number' => '99998888'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    // ---------- Smart descriptor refresh ----------

    public function test_version_changes_when_a_face_is_enrolled(): void
    {
        $employee = $this->seedBase();
        $employee->update(['face_descriptor' => null]); // start NOT enrolled for this test

        $before = $this->getJson('/kiosk/version')->json('version');

        $employee->update(['face_descriptor' => json_encode([array_fill(0, 128, 0.1)])]);

        $after = $this->getJson('/kiosk/version')->json('version');

        $this->assertNotSame($before, $after);

        $payload = $this->getJson('/kiosk/descriptors')->json();
        $this->assertSame($after, $payload['version']);
        $this->assertCount(1, $payload['employees']);
    }

    // ---------- Enrollment mode ----------

    public function test_guided_enrollment_records_consent_for_the_validated_person(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-16 14:30:00'); // Thursday, working hours
        $this->seedBase();
        $employee = Employee::create([ // faceless: eligible for guided enrollment
            'document_number' => '33334444',
            'first_name' => 'NO', 'last_name' => 'FACE',
            'schedule_id' => Schedule::first()->id,
        ]);

        // Validated on the keypad (sets kiosk_verify_doc), then enrolls with consent
        $this->postJson('/kiosk/lookup', ['document_number' => '33334444'])->assertOk();
        $this->postJson('/kiosk/enroll/descriptor', [
            'employee_id' => $employee->id,
            'consent' => true,
            'descriptors' => [array_fill(0, 128, 0.2)],
        ])->assertOk()->assertJsonPath('ok', true);

        $employee->refresh();
        $this->assertTrue($employee->hasFace());
        $this->assertNotNull($employee->biometric_consent_at);

        \Carbon\Carbon::setTestNow();
    }

    public function test_enrollment_requires_the_biometric_consent(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-16 14:30:00');
        $this->seedBase();
        $employee = Employee::create(['document_number' => '33334444', 'first_name' => 'NO', 'last_name' => 'FACE', 'schedule_id' => Schedule::first()->id]);

        $this->postJson('/kiosk/lookup', ['document_number' => '33334444'])->assertOk();
        // No consent → validation error
        $this->postJson('/kiosk/enroll/descriptor', [
            'employee_id' => $employee->id, 'consent' => false, 'descriptors' => [array_fill(0, 128, 0.2)],
        ])->assertStatus(422);

        \Carbon\Carbon::setTestNow();
    }

    // ---------- RENIEC lookup (Decolecta) ----------

    public function test_dni_lookup_returns_names_from_decolecta(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['services.decolecta.token' => 'test-token']);

        Http::fake([
            'api.decolecta.com/*' => Http::response([
                'first_name' => 'MARIA ELENA',
                'first_last_name' => 'GARCIA',
                'second_last_name' => 'TORRES',
                'full_name' => 'GARCIA TORRES MARIA ELENA',
                'document_number' => '40111222',

                'face_descriptor' => json_encode([array_fill(0, 128, 0.1)]), // enrolled: required for document fallback
            ]),
        ]);

        $this->actingAs(\App\Models\User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail())
            ->getJson('/dni-lookup/40111222')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('first_name', 'MARIA ELENA')
            ->assertJsonPath('last_name', 'GARCIA TORRES');
    }

    public function test_dni_lookup_without_token_returns_configuration_error(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['services.decolecta.token' => null]);

        $this->actingAs(\App\Models\User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail())
            ->getJson('/dni-lookup/40111222')
            ->assertStatus(503)
            ->assertJsonPath('ok', false);
    }
}
