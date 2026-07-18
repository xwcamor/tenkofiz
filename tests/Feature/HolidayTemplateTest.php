<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\HolidayTemplate;
use App\Models\Profile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::where('email', 'admin@test.com')->first();
    }

    public function test_seeder_creates_templates_for_both_countries_and_a_complete_current_year(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(15, HolidayTemplate::where('country', 'PE')->count());
        $this->assertGreaterThanOrEqual(14, HolidayTemplate::where('country', 'CL')->count());

        // The current year opens already complete (no "half-filled" list)
        $year = now()->year;
        $this->assertDatabaseHas('holidays', ['date' => "$year-07-28"]); // Fiestas Patrias
        $this->assertDatabaseHas('holidays', ['date' => "$year-12-25"]); // Navidad
    }

    public function test_generate_uses_the_chosen_countrys_templates(): void
    {
        $admin = $this->admin();

        // Chile: generate 2027 → Chilean-only holidays appear, Peruvian-only ones do not
        $this->actingAs($admin)->post('/holidays-generate', ['year' => 2027, 'country' => 'CL'])
            ->assertRedirect();

        $this->assertDatabaseHas('holidays', ['date' => '2027-09-18']); // Independencia (CL)
        $this->assertDatabaseHas('holidays', ['date' => '2027-05-21']); // Glorias Navales (CL)
        $this->assertDatabaseMissing('holidays', ['date' => '2027-07-28']); // Fiestas Patrias is Peru-only
    }

    public function test_easter_relative_templates_compute_the_right_date(): void
    {
        $this->admin();

        // Good Friday 2027 is 2027-03-26 (Easter Sunday 2027-03-28, offset -2)
        $goodFriday = HolidayTemplate::where('country', 'PE')->where('easter_offset', -2)->first();
        $this->assertNotNull($goodFriday);
        $this->assertSame('2027-03-26', $goodFriday->dateForYear(2027));
    }

    public function test_restore_defaults_re_adds_missing_templates(): void
    {
        $admin = $this->admin();

        HolidayTemplate::where('country', 'PE')->delete();
        $this->assertSame(0, HolidayTemplate::where('country', 'PE')->count());

        $this->actingAs($admin)->post('/holiday-templates-restore', ['country' => 'PE'])->assertRedirect();

        $this->assertGreaterThanOrEqual(15, HolidayTemplate::where('country', 'PE')->count());
    }

    public function test_a_company_can_add_its_own_recurring_holiday(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/holiday-templates', [
            'country' => 'PE', 'kind' => 'fixed', 'month' => 4, 'day' => 15, 'name' => 'Aniversario de la empresa',
        ])->assertRedirect();

        $this->assertDatabaseHas('holiday_templates', ['country' => 'PE', 'month' => 4, 'day' => 15, 'name' => 'Aniversario de la empresa']);

        // and it flows into a generated year
        $this->actingAs($admin)->post('/holidays-generate', ['year' => 2028, 'country' => 'PE']);
        $this->assertDatabaseHas('holidays', ['date' => '2028-04-15']);
    }
}
