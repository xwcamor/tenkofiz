<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitesAndKioskBindingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------- Multi-site ----------

    public function test_kiosk_only_marks_employees_of_the_selected_site(): void
    {
        $this->seed(DatabaseSeeder::class);
        $schedule = Schedule::first();
        $lima = Site::create(['name' => 'Lima']);
        $cusco = Site::create(['name' => 'Cusco']);

        Employee::create(['document_number' => '11112222', 'first_name' => 'A', 'last_name' => 'LIMA', 'schedule_id' => $schedule->id, 'site_id' => $lima->id]);
        Employee::create(['document_number' => '33334444', 'first_name' => 'B', 'last_name' => 'CUSCO', 'schedule_id' => $schedule->id, 'site_id' => $cusco->id]);

        Carbon::setTestNow('2026-07-16 14:30:00'); // Thursday, a working day

        // Enter the kiosk for the Lima site → its session is scoped to Lima
        $this->get('/kiosk?site='.$lima->id)->assertOk();

        // A Lima employee can mark
        $this->postJson('/kiosk/mark-dni', ['document_number' => '11112222'])->assertOk();

        // A Cusco employee is invisible to the Lima kiosk
        $this->postJson('/kiosk/mark-dni', ['document_number' => '33334444'])->assertStatus(422);
    }

    // ---------- Device binding ----------

    public function test_pairing_binds_the_device_and_blocks_others(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@test.com')->first();

        // Admin generates a one-time pairing code
        $this->actingAs($admin)->post('/settings/kiosk-pair-code')->assertSessionHas('pair_code');
        $code = session('pair_code');
        $this->assertNotEmpty($code);

        // A fresh device (no cookie) pairs with the code and receives the cookie
        $pair = $this->post('/kiosk/pair', ['code' => $code]);
        $pair->assertRedirect();
        $pair->assertCookie('kiosk_device');

        $setting = Setting::instance();
        $this->assertNotNull($setting->kiosk_device_hash);
        $this->assertNull($setting->kiosk_pair_code); // one-time: consumed

        // Another device (no valid cookie) is rejected
        $this->get('/kiosk')->assertForbidden();

        // The paired device (correct cookie) is allowed
        $secret = $pair->headers->getCookies()[0]->getValue();
        $this->withUnencryptedCookie('kiosk_device', $secret)->get('/kiosk')->assertOk();
    }

    public function test_expired_or_wrong_code_does_not_pair(): void
    {
        $this->seed(DatabaseSeeder::class);

        Setting::instance()->update(['kiosk_pair_code' => 'ABC123', 'kiosk_pair_expires_at' => now()->subMinute()]);
        $this->post('/kiosk/pair', ['code' => 'ABC123'])->assertSessionHasErrors('code');

        Setting::instance()->update(['kiosk_pair_code' => 'ABC123', 'kiosk_pair_expires_at' => now()->addMinutes(10)]);
        $this->post('/kiosk/pair', ['code' => 'WRONG'])->assertSessionHasErrors('code');

        $this->assertNull(Setting::instance()->kiosk_device_hash);
    }
}
