<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsAndKioskModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_kiosk_is_a_controllable_profile_module(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Admin and Supervisor may see the kiosk; a plain employee may not
        $this->assertTrue(User::where('email', 'admin@test.com')->first()->hasModule('kiosk'));
        $this->assertTrue(User::where('email', 'aprobador@test.com')->first()->hasModule('kiosk'));
        $this->assertFalse(User::where('email', 'empleado@test.com')->first()->hasModule('kiosk'));
    }

    public function test_detailed_report_exports_an_xlsx(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@test.com')->first();

        $employee = Employee::create([
            'document_number' => '11112222',
            'first_name' => 'JOHN',
            'last_name' => 'DOE',
            'schedule_id' => Schedule::first()->id,
        ]);
        Attendance::create([
            'employee_id' => $employee->id,
            'date' => '2026-07-16',
            'check_in' => '08:00:00',
            'check_out' => '17:00:00',
            'status' => 'ON_TIME',
            'method' => 'FACIAL',
        ]);

        $response = $this->actingAs($admin)->get('/reports/export-detail?from=2026-07-01&to=2026-07-31');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('content-type')
        );
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
    }

    public function test_break_analysis_report_flags_days_over_the_limit(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@test.com')->first();
        \App\Models\Setting::query()->update(['kiosk_breaks_enabled' => true, 'break_limit_minutes' => 60]);

        $employee = Employee::create([
            'document_number' => '22223333',
            'first_name' => 'ANA',
            'last_name' => 'BREAKS',
            'schedule_id' => Schedule::first()->id,
        ]);
        // Within limit (30 min) and over limit (90 min)
        Attendance::create(['employee_id' => $employee->id, 'date' => '2026-06-10', 'check_in' => '08:00:00', 'break_out' => '12:00:00', 'break_in' => '12:30:00', 'check_out' => '17:00:00', 'status' => 'ON_TIME', 'method' => 'FACIAL']);
        Attendance::create(['employee_id' => $employee->id, 'date' => '2026-06-11', 'check_in' => '08:00:00', 'break_out' => '12:00:00', 'break_in' => '13:30:00', 'check_out' => '17:00:00', 'status' => 'ON_TIME', 'method' => 'FACIAL']);

        $response = $this->actingAs($admin)->get('/reports/breaks?from=2026-06-01&to=2026-06-30');

        $response->assertOk();
        $response->assertSee('ANA');
        $response->assertSee(__('Time exceeded'));   // the 90-min day is flagged
        $response->assertSee(__('Within limit'));    // the 30-min day is not
    }

    public function test_break_analysis_exports_an_xlsx(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@test.com')->first();
        \App\Models\Setting::query()->update(['kiosk_breaks_enabled' => true, 'break_limit_minutes' => 60]);

        $response = $this->actingAs($admin)->get('/reports/breaks/export?from=2026-06-01&to=2026-06-30');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
    }
}
