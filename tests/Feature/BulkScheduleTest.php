<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use App\Support\Hashid;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_assigns_a_schedule_to_the_selected_employees(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail();
        $base = Schedule::first();
        $target = Schedule::create(['name' => 'Tarde 2-10', 'tolerance_minutes' => 10]);
        $target->days()->create(['weekday' => 1, 'start_time' => '14:00', 'end_time' => '22:00']);

        $a = Employee::create(['document_number' => '11110001', 'first_name' => 'A', 'last_name' => 'X', 'schedule_id' => $base->id]);
        $b = Employee::create(['document_number' => '11110002', 'first_name' => 'B', 'last_name' => 'Y', 'schedule_id' => $base->id]);
        $c = Employee::create(['document_number' => '11110003', 'first_name' => 'C', 'last_name' => 'Z', 'schedule_id' => $base->id]);

        // Move A and B (Hashid-encoded ids, as the checkboxes send them); leave C
        $this->actingAs($admin)->post(route('employees.bulkSchedule'), [
            'employee_ids' => [$a->getRouteKey(), $b->getRouteKey()],
            'schedule_id' => $target->id,
        ])->assertSessionHas('ok');

        $this->assertSame($target->id, $a->fresh()->schedule_id);
        $this->assertSame($target->id, $b->fresh()->schedule_id);
        $this->assertSame($base->id, $c->fresh()->schedule_id); // untouched
    }

    public function test_cannot_bulk_assign_across_companies(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail();
        $mySchedule = Schedule::first();

        // A foreign employee in another company
        $other = \App\Models\Company::create(['name' => 'Otra', 'is_active' => true]);
        $foreignId = \App\Models\Scopes\CompanyScope::actingAs($other->id, function () use ($other) {
            $sch = Schedule::create(['name' => 'Foreign', 'tolerance_minutes' => 10, 'company_id' => $other->id]);
            $emp = Employee::create(['document_number' => '99990001', 'first_name' => 'F', 'last_name' => 'F', 'schedule_id' => $sch->id, 'company_id' => $other->id]);

            return [$emp->id, $emp->getRouteKey(), $sch->id];
        });

        // My admin tries to reassign the foreign employee → the scoped update skips it
        $this->actingAs($admin)->post(route('employees.bulkSchedule'), [
            'employee_ids' => [$foreignId[1]],
            'schedule_id' => $mySchedule->id,
        ]);

        // The foreign employee keeps its own schedule (never touched)
        \App\Models\Scopes\CompanyScope::actingAs($other->id, function () use ($foreignId) {
            $this->assertSame($foreignId[2], Employee::find($foreignId[0])->schedule_id);
        });
    }
}
