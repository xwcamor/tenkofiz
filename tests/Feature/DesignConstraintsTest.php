<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design rules: a workspace must always keep at least one schedule and at least
 * one site, so employees can always be registered and scoped. The last of each
 * is locked against deletion.
 */
class DesignConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail();
    }

    public function test_the_last_schedule_cannot_be_deleted(): void
    {
        $admin = $this->admin();

        // Seed leaves exactly one shared schedule ("Horario General").
        $this->assertSame(1, Schedule::shared()->count());
        $last = Schedule::shared()->firstOrFail();

        $this->actingAs($admin)->delete(route('schedules.destroy', $last))->assertSessionHas('error');
        $this->assertDatabaseHas('schedules', ['id' => $last->id]);
    }

    public function test_a_schedule_can_be_deleted_once_a_second_one_exists(): void
    {
        $admin = $this->admin();

        // Add a second shared schedule, then the first becomes deletable.
        $this->actingAs($admin)->postJson('/schedules-quick', [
            'name' => 'Turno Tarde', 'weekdays' => [1, 2, 3, 4, 5], 'start' => '14:00', 'end' => '22:00', 'is_shared' => 1,
        ])->assertOk();
        $this->assertSame(2, Schedule::shared()->count());

        $first = Schedule::shared()->where('name', 'Horario General')->firstOrFail();
        $this->actingAs($admin)->delete(route('schedules.destroy', $first))->assertSessionHas('ok');
        $this->assertDatabaseMissing('schedules', ['id' => $first->id]);
    }

    public function test_the_last_site_cannot_be_deleted(): void
    {
        $admin = $this->admin();

        // Delete every site but one (none has employees under tests).
        $sites = Site::orderBy('id')->get();
        foreach ($sites->slice(0, $sites->count() - 1) as $site) {
            $this->actingAs($admin)->delete(route('sites.destroy', $site));
        }

        $this->assertSame(1, Site::count());
        $last = Site::firstOrFail();
        $this->actingAs($admin)->delete(route('sites.destroy', $last))->assertSessionHas('error');
        $this->assertDatabaseHas('sites', ['id' => $last->id]);
    }
}
