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

        $face = json_encode([array_fill(0, 128, 0.1)]); // enrolled: required for document fallback
        Employee::create(['document_number' => '11112222', 'first_name' => 'A', 'last_name' => 'LIMA', 'schedule_id' => $schedule->id, 'site_id' => $lima->id, 'face_descriptor' => $face]);
        Employee::create(['document_number' => '33334444', 'first_name' => 'B', 'last_name' => 'CUSCO', 'schedule_id' => $schedule->id, 'site_id' => $cusco->id, 'face_descriptor' => $face]);

        Carbon::setTestNow('2026-07-16 14:30:00'); // Thursday, a working day

        // Enter the kiosk for the Lima site → its session is scoped to Lima
        $this->get('/kiosk?site='.$lima->id)->assertOk();

        // A Lima employee can mark
        $this->postJson('/kiosk/mark-dni', ['document_number' => '11112222'])->assertOk();

        // A Cusco employee is invisible to the Lima kiosk
        $this->postJson('/kiosk/mark-dni', ['document_number' => '33334444'])->assertStatus(422);
    }

    // ---------- Device binding ----------

    public function test_pairing_binds_the_device_to_a_site_and_blocks_others(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@test.com')->first();
        $site = Site::first(); // the seeded "Sede Principal"

        // Admin generates a one-time pairing code FOR THIS SITE
        $this->actingAs($admin)->post('/sites/'.$site->id.'/kiosk-pair-code')
            ->assertSessionHas('pair_code')
            ->assertSessionHas('pair_site', $site->id);
        $code = session('pair_code');
        $this->assertNotEmpty($code);

        // A fresh device (no cookie) pairs with the code and receives the cookie
        $pair = $this->post('/kiosk/pair', ['code' => $code]);
        $pair->assertRedirect();
        $pair->assertCookie('kiosk_device');

        $site->refresh();
        $this->assertTrue($site->hasPairedDevices());
        $this->assertCount(1, $site->kioskDevices);
        $this->assertNull($site->kiosk_pair_code); // one-time: consumed

        // Another device (no valid cookie) is rejected for this site
        $this->get('/kiosk?site='.$site->id)->assertForbidden();

        // The paired device (correct cookie) is allowed
        $secret = $pair->headers->getCookies()[0]->getValue();
        $this->withUnencryptedCookie('kiosk_device', $secret)->get('/kiosk?site='.$site->id)->assertOk();
    }

    // ---------- Multi-device (several tablets per site, one per area) ----------

    public function test_a_site_can_pair_several_tablets_and_any_of_them_opens_the_kiosk(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@test.com')->first();
        $site = Site::first();

        // Pair TWO tablets, each with its own code and its own name
        $secrets = [];
        foreach (['Reception', 'Warehouse'] as $name) {
            $this->actingAs($admin)->post('/sites/'.$site->id.'/kiosk-pair-code');
            $code = session('pair_code');
            $pair = $this->post('/kiosk/pair', ['code' => $code, 'device_name' => $name]);
            $pair->assertRedirect();
            $secrets[$name] = $pair->headers->getCookies()[0]->getValue();
        }

        $site->refresh();
        $this->assertCount(2, $site->kioskDevices);
        $this->assertEqualsCanonicalizing(['Reception', 'Warehouse'], $site->kioskDevices->pluck('name')->all());

        // A stranger device (no valid cookie) is rejected. Checked first: the
        // withUnencryptedCookie() helper below persists its cookie for the rest of
        // the test, so a cookie-less assertion must run before it.
        $this->get('/kiosk?site='.$site->id)->assertForbidden();

        // EITHER tablet's cookie opens the kiosk
        $this->withUnencryptedCookie('kiosk_device', $secrets['Reception'])->get('/kiosk?site='.$site->id)->assertOk();
        $this->withUnencryptedCookie('kiosk_device', $secrets['Warehouse'])->get('/kiosk?site='.$site->id)->assertOk();
    }

    public function test_revoking_one_tablet_keeps_the_others_working(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@test.com')->first();
        $site = Site::first();

        $secrets = [];
        foreach (['A', 'B'] as $name) {
            $this->actingAs($admin)->post('/sites/'.$site->id.'/kiosk-pair-code');
            $pair = $this->post('/kiosk/pair', ['code' => session('pair_code'), 'device_name' => $name]);
            $secrets[$name] = $pair->headers->getCookies()[0]->getValue();
        }
        $site->refresh();
        $tabletA = $site->kioskDevices->firstWhere('name', 'A');

        // Revoke tablet A only
        $this->actingAs($admin)->delete('/sites/'.$site->id.'/kiosk-device/'.$tabletA->id)->assertRedirect();
        $this->assertDatabaseMissing('kiosk_devices', ['id' => $tabletA->id]);

        // A can no longer open it; B still can
        $this->withUnencryptedCookie('kiosk_device', $secrets['A'])->get('/kiosk?site='.$site->id)->assertForbidden();
        $this->withUnencryptedCookie('kiosk_device', $secrets['B'])->get('/kiosk?site='.$site->id)->assertOk();
    }

    public function test_a_device_cannot_be_revoked_from_a_site_it_does_not_belong_to(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@test.com')->first();
        $siteA = Site::first();
        $siteB = Site::create(['name' => 'Other']);

        $this->actingAs($admin)->post('/sites/'.$siteA->id.'/kiosk-pair-code');
        $this->post('/kiosk/pair', ['code' => session('pair_code'), 'device_name' => 'A-tablet']);
        $device = $siteA->kioskDevices()->first();

        // Trying to revoke A's device through site B's route is a 404
        $this->actingAs($admin)->delete('/sites/'.$siteB->id.'/kiosk-device/'.$device->id)->assertNotFound();
        $this->assertDatabaseHas('kiosk_devices', ['id' => $device->id]);
    }

    public function test_expired_or_wrong_code_does_not_pair(): void
    {
        $this->seed(DatabaseSeeder::class);
        $site = Site::first();

        $site->update(['kiosk_pair_code' => 'ABC123', 'kiosk_pair_expires_at' => now()->subMinute()]);
        $this->post('/kiosk/pair', ['code' => 'ABC123'])->assertSessionHasErrors('code');

        $site->update(['kiosk_pair_code' => 'ABC123', 'kiosk_pair_expires_at' => now()->addMinutes(10)]);
        $this->post('/kiosk/pair', ['code' => 'WRONG'])->assertSessionHasErrors('code');

        $this->assertFalse($site->fresh()->hasPairedDevices());
    }

    // ---------- Forced geolocation (no GPS, no mark) ----------

    public function test_forced_geolocation_rejects_a_mark_without_coordinates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $schedule = Schedule::first();
        $site = Site::first();
        Setting::query()->update(['kiosk_geolocation' => true, 'kiosk_geolocation_required' => true]);

        $face = json_encode([array_fill(0, 128, 0.1)]);
        Employee::create(['document_number' => '55556666', 'first_name' => 'Geo', 'last_name' => 'USER', 'schedule_id' => $schedule->id, 'site_id' => $site->id, 'face_descriptor' => $face]);

        Carbon::setTestNow('2026-07-16 14:30:00'); // Thursday, a working day
        $this->get('/kiosk?site='.$site->id)->assertOk();

        // No coordinates → rejected
        $this->postJson('/kiosk/mark-dni', ['document_number' => '55556666'])->assertStatus(422);

        // With coordinates → allowed
        $this->postJson('/kiosk/mark-dni', ['document_number' => '55556666', 'lat' => -12.05, 'lng' => -77.04])->assertOk();
    }

    public function test_geolocation_not_required_allows_a_mark_without_coordinates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $schedule = Schedule::first();
        $site = Site::first();
        Setting::query()->update(['kiosk_geolocation' => true, 'kiosk_geolocation_required' => false]);

        $face = json_encode([array_fill(0, 128, 0.1)]);
        Employee::create(['document_number' => '77778888', 'first_name' => 'Soft', 'last_name' => 'GEO', 'schedule_id' => $schedule->id, 'site_id' => $site->id, 'face_descriptor' => $face]);

        Carbon::setTestNow('2026-07-16 14:30:00');
        $this->get('/kiosk?site='.$site->id)->assertOk();

        // Location recorded when present, but never blocks a mark
        $this->postJson('/kiosk/mark-dni', ['document_number' => '77778888'])->assertOk();
    }

    public function test_a_sites_token_link_opens_only_that_sites_kiosk(): void
    {
        $this->seed(DatabaseSeeder::class);
        $site = Site::first();
        $site->regenerateKioskToken();
        $token = $site->fresh()->kiosk_token;

        // Wrong/absent token is rejected; the correct token opens it
        $this->get('/kiosk?site='.$site->id)->assertForbidden();
        $this->get('/kiosk?site='.$site->id.'&token=WRONG')->assertForbidden();
        $this->get('/kiosk?site='.$site->id.'&token='.$token)->assertOk();
    }
}
