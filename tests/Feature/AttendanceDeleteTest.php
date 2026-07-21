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

class AttendanceDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function setUpData(): array
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@test.com')->first();
        $employee = Employee::create([
            'document_number' => '11112222',

            'face_descriptor' => json_encode([array_fill(0, 128, 0.1)]), // enrolled: required for document fallback
            'first_name' => 'JOHN',
            'last_name' => 'DOE',
            'schedule_id' => Schedule::first()->id,
        ]);
        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'date' => '2026-07-16',
            'check_in' => '08:00:00',
            'check_out' => '17:00:00',
            'status' => 'ON_TIME',
            'method' => 'FACIAL',
        ]);

        return [$admin, $employee, $attendance];
    }

    public function test_delete_requires_a_reason_and_soft_deletes(): void
    {
        [$admin, , $attendance] = $this->setUpData();

        $this->actingAs($admin)->delete("/attendances/{$attendance->getRouteKey()}")
            ->assertSessionHasErrors('delete_reason');
        $this->assertNull($attendance->fresh()->deleted_at);

        $this->actingAs($admin)->delete("/attendances/{$attendance->getRouteKey()}", ['delete_reason' => 'Duplicated mark'])
            ->assertSessionHas('ok');
        $this->assertSoftDeleted('attendances', ['id' => $attendance->id]);
        $this->assertSame(0, Attendance::count());
    }

    public function test_admin_sees_deleted_and_can_restore(): void
    {
        [$admin, , $attendance] = $this->setUpData();
        $this->actingAs($admin)->delete("/attendances/{$attendance->getRouteKey()}", ['delete_reason' => 'Mistake']);

        $deleted = $this->actingAs($admin)
            ->get('/attendances?deleted=1&from=2026-07-01&to=2026-07-31')
            ->viewData('attendances');
        $this->assertSame(1, $deleted->total());
        $this->assertSame('Mistake', $deleted->first()->delete_reason);

        $this->actingAs($admin)->post("/attendances/{$attendance->getRouteKey()}/restore")->assertSessionHas('ok');
        $this->assertNull($attendance->fresh()->deleted_at);
    }

    public function test_attendance_list_shows_the_document_and_flags_a_deleted_employee(): void
    {
        [$admin, $employee, ] = $this->setUpData();

        // While active: the document is shown, no deleted badge
        $this->actingAs($admin)->get('/attendances?from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->assertSee('11112222')
            ->assertDontSee(__('Deleted'));

        // Deleting the employee keeps the attendance and its name; the list flags it
        $employee->update(['delete_reason' => 'left the company']);
        $employee->delete();

        $response = $this->actingAs($admin)->get('/attendances?from=2026-07-01&to=2026-07-31');
        $response->assertOk()
            ->assertSee('DOE, JOHN')       // name still resolves (withTrashed relation)
            ->assertSee('11112222')        // document still shown
            ->assertSee(__('Deleted'));    // and the row is flagged
        $this->assertSame(1, Attendance::count()); // the attendance was NOT deleted
    }

    public function test_same_day_can_be_marked_again_after_deletion(): void
    {
        [, $employee, $attendance] = $this->setUpData();
        $admin = User::where('email', 'admin@test.com')->first();

        // Delete the mark, then the kiosk records a fresh one for the same date
        $this->actingAs($admin)->delete("/attendances/{$attendance->getRouteKey()}", ['delete_reason' => 'Wrong person']);

        Carbon::setTestNow('2026-07-16 14:30:00'); // Thursday 09:30 Lima
        $this->postJson('/kiosk/mark-dni', ['document_number' => '11112222'])
            ->assertOk()
            ->assertJsonPath('type', 'CHECK_IN');

        // One live record + one trashed, no unique-constraint crash
        $this->assertSame(1, Attendance::count());
        $this->assertSame(2, Attendance::withTrashed()->count());
    }
}
