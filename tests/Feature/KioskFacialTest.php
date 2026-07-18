<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\Site;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KioskFacialTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enrolledEmployee(): Employee
    {
        $this->seed(DatabaseSeeder::class);

        return Employee::create([
            'document_number' => '47019237',
            'first_name' => 'NELDA',
            'last_name' => 'ATOCHE',
            'schedule_id' => Schedule::first()->id,
            'face_descriptor' => json_encode([array_fill(0, 128, 0.1)]),
        ]);
    }

    public function test_person_face_returns_descriptors_for_1to1_verification(): void
    {
        $employee = $this->enrolledEmployee();

        $data = $this->getJson('/kiosk/face/47019237')->assertOk()->json();
        $this->assertTrue($data['ok']);
        $this->assertSame($employee->id, $data['id']);
        $this->assertCount(1, $data['descriptors']);
        $this->assertCount(128, $data['descriptors'][0]);
    }

    public function test_person_face_404_when_not_enrolled(): void
    {
        $this->seed(DatabaseSeeder::class);
        Employee::create([
            'document_number' => '11112222',
            'first_name' => 'NO',
            'last_name' => 'FACE',
            'schedule_id' => Schedule::first()->id,
        ]); // no face_descriptor

        $this->getJson('/kiosk/face/11112222')->assertNotFound();
        $this->getJson('/kiosk/face/99999999')->assertNotFound();
    }

    public function test_person_face_is_scoped_to_the_kiosk_site(): void
    {
        $this->seed(DatabaseSeeder::class);
        $lima = Site::create(['name' => 'Lima']);
        $cusco = Site::create(['name' => 'Cusco']);
        Employee::create(['document_number' => '47019237', 'first_name' => 'A', 'last_name' => 'CUSCO', 'schedule_id' => Schedule::first()->id, 'site_id' => $cusco->id, 'face_descriptor' => json_encode([array_fill(0, 128, 0.1)])]);

        // Kiosk scoped to Lima → a Cusco person is not found
        $this->get('/kiosk?site='.$lima->id);
        $this->getJson('/kiosk/face/47019237')->assertNotFound();
    }

    public function test_facial_mark_respects_the_configurable_threshold(): void
    {
        $employee = $this->enrolledEmployee();
        Setting::instance()->update(['kiosk_face_threshold' => 0.45]);
        Carbon::setTestNow('2026-07-16 14:30:00'); // Thursday

        // distance 0.50 > threshold 0.45 → rejected
        $this->postJson('/kiosk/mark', ['employee_id' => $employee->id, 'distance' => 0.50])->assertStatus(422);

        // distance 0.40 <= 0.45 → accepted
        $this->postJson('/kiosk/mark', ['employee_id' => $employee->id, 'distance' => 0.40])
            ->assertOk()->assertJsonPath('type', 'CHECK_IN');
    }
}
