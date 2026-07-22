<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared (catalog) vs personalized (per-person) schedules: personalized ones are
 * created inline for one employee and never clutter the shared catalog.
 */
class PersonalizedScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail();
    }

    public function test_quick_store_can_create_a_personalized_schedule_hidden_from_the_catalog(): void
    {
        $admin = $this->admin();

        // Personalized: is_shared = 0
        $this->actingAs($admin)->postJson('/schedules-quick', [
            'name' => 'IA 202 (2026-10)', 'weekdays' => [1, 2, 3], 'start' => '09:00', 'end' => '13:00',
            'is_shared' => 0, 'async_minutes_per_day' => 60,
        ])->assertOk();

        $personal = Schedule::firstWhere('name', 'IA 202 (2026-10)');
        $this->assertFalse($personal->is_shared);
        $this->assertSame(60, $personal->async_minutes_per_day);

        // It does NOT appear in the shared catalog (Schedules admin page)
        $names = $this->actingAs($admin)->get('/schedules')->assertOk()->viewData('schedules')->pluck('name');
        $this->assertFalse($names->contains('IA 202 (2026-10)'));

        // A shared one from the same shortcut DOES appear
        $this->actingAs($admin)->postJson('/schedules-quick', [
            'name' => 'Turno Mañana', 'weekdays' => [1, 2, 3, 4, 5], 'start' => '08:00', 'end' => '17:00', 'is_shared' => 1,
        ])->assertOk();
        $names2 = $this->actingAs($admin)->get('/schedules')->viewData('schedules')->pluck('name');
        $this->assertTrue($names2->contains('Turno Mañana'));
        $this->assertFalse($names2->contains('IA 202 (2026-10)'));
    }

    public function test_two_personalized_schedules_can_share_a_name(): void
    {
        $admin = $this->admin();

        foreach (['A', 'B'] as $who) {
            $this->actingAs($admin)->postJson('/schedules-quick', [
                'name' => 'Curso X', 'weekdays' => [1], 'start' => '09:00', 'end' => '13:00', 'is_shared' => 0,
            ])->assertOk();
        }
        // Personalized names are not forced unique (each person's is separate)
        $this->assertSame(2, Schedule::where('name', 'Curso X')->count());
    }
}
