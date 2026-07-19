<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function super(): User
    {
        return User::withoutGlobalScopes()->where('is_super_admin', true)->first();
    }

    public function test_super_outside_a_workspace_is_redirected_from_business_modules(): void
    {
        $super = $this->super();

        foreach (['/employees', '/attendances', '/reports', '/users', '/settings', '/sites'] as $url) {
            $this->actingAs($super)->get($url)
                ->assertRedirect(route('admin.companies.index'));
        }

        // Their home is the workspaces console
        $this->actingAs($super)->get('/')->assertRedirect(route('admin.companies.index'));
    }

    public function test_super_keeps_the_global_security_audit_outside_workspaces(): void
    {
        $this->actingAs($this->super())->get('/audit-logs')->assertOk();
    }

    public function test_super_operates_business_modules_after_entering_a_workspace(): void
    {
        $super = $this->super();
        $company = Company::orderBy('id')->first();

        $this->actingAs($super)->post(route('admin.companies.enter', $company))->assertRedirect();

        $this->actingAs($super)->get('/employees')->assertOk();
        $this->actingAs($super)->get('/settings')->assertOk();

        // Leaving closes the door again
        $this->actingAs($super)->post(route('admin.companies.leave'));
        $this->actingAs($super)->get('/employees')->assertRedirect(route('admin.companies.index'));
    }

    public function test_what_the_super_creates_lands_in_the_entered_workspace(): void
    {
        $super = $this->super();
        $other = Company::create(['name' => 'Empresa Aparte', 'is_active' => true]);

        $this->actingAs($super)->post(route('admin.companies.enter', $other));

        $this->actingAs($super)->post('/sites', ['name' => 'Sede del Super'])->assertRedirect();

        // Unambiguous: the site belongs to the workspace the super ENTERED
        $this->assertDatabaseHas('sites', ['name' => 'Sede del Super', 'company_id' => $other->id]);
    }
}
