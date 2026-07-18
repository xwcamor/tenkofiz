<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['terms.enforced' => true]); // the suite disables it globally; this test covers the gate
    }

    private function admin(): User
    {
        return User::where('email', 'admin@test.com')->first();
    }

    public function test_user_without_acceptance_is_redirected_to_terms_everywhere(): void
    {
        $admin = $this->admin();
        $this->assertFalse($admin->hasAcceptedTerms());

        foreach (['/', '/employees', '/settings', '/users'] as $url) {
            $this->actingAs($admin)->get($url)->assertRedirect(route('terms.show'));
        }

        // The terms screen itself and logout stay reachable
        $this->actingAs($admin)->get('/terms')->assertOk()->assertSee('1.');
    }

    public function test_accepting_records_date_ip_and_version_and_unblocks(): void
    {
        $admin = $this->admin();

        // Without the checkbox: rejected
        $this->actingAs($admin)->post('/terms', [])->assertSessionHasErrors('accept');

        // With the checkbox: recorded and redirected to the dashboard
        $this->actingAs($admin)->post('/terms', ['accept' => '1'])->assertRedirect(route('dashboard'));

        $admin->refresh();
        $this->assertTrue($admin->hasAcceptedTerms());
        $this->assertNotNull($admin->terms_accepted_at);
        $this->assertSame(User::TERMS_VERSION, $admin->terms_version);
        $this->assertNotNull($admin->terms_ip);

        // Now the system opens normally
        $this->actingAs($admin)->get('/employees')->assertOk();
    }

    public function test_a_new_terms_version_forces_re_acceptance(): void
    {
        $admin = $this->admin();
        $admin->update(['terms_accepted_at' => now(), 'terms_version' => '0.9', 'terms_ip' => '1.2.3.4']);

        // Accepted an OLD version -> gated again
        $this->assertFalse($admin->fresh()->hasAcceptedTerms());
        $this->actingAs($admin)->get('/')->assertRedirect(route('terms.show'));
    }

    public function test_accepted_user_visiting_terms_goes_to_dashboard(): void
    {
        $admin = $this->admin();
        $admin->update(['terms_accepted_at' => now(), 'terms_version' => User::TERMS_VERSION, 'terms_ip' => '1.2.3.4']);

        $this->actingAs($admin)->get('/terms')->assertRedirect(route('dashboard'));
    }
}
