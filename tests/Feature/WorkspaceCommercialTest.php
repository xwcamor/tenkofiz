<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Scopes\CompanyScope;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceCommercialTest extends TestCase
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

    private function demoCompany(): Company
    {
        return Company::orderBy('id')->first();
    }

    private function demoAdmin(): User
    {
        return User::withoutGlobalScopes()->where('email', 'admin@test.com')->first();
    }

    // ---------- Suspension (non-payment) ----------

    public function test_suspended_workspace_users_cannot_sign_in(): void
    {
        $company = $this->demoCompany();
        $this->actingAs($this->super())
            ->post(route('admin.companies.suspend', $company), ['suspended_reason' => 'Pago pendiente'])
            ->assertSessionHas('ok');

        $this->assertFalse($company->fresh()->is_active);

        // Fresh login attempt is rejected with the suspension message
        auth()->logout();
        $this->post('/login', ['email' => 'admin@test.com', 'password' => '123456'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_open_sessions_of_a_suspended_workspace_are_cut(): void
    {
        $admin = $this->demoAdmin();
        $this->actingAs($admin)->get('/')->assertOk();

        $this->demoCompany()->update(['is_active' => false, 'suspended_reason' => 'Pago pendiente']);

        // The very next request logs them out and sends them to login
        $this->actingAs($admin->fresh())->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_suspended_workspace_kiosk_stops_marking(): void
    {
        $site = Site::withoutGlobalScopes()->where('company_id', $this->demoCompany()->id)->first();
        $this->assertNotNull($site);

        $this->get('/kiosk?site='.$site->id)->assertOk();

        $this->demoCompany()->update(['is_active' => false]);

        $this->get('/kiosk?site='.$site->id)->assertForbidden();
    }

    public function test_reactivation_restores_access(): void
    {
        $company = $this->demoCompany();
        $company->update(['is_active' => false, 'suspended_reason' => 'x']);

        $this->actingAs($this->super())->post(route('admin.companies.reactivate', $company));

        $this->post('/logout');
        $this->post('/login', ['email' => 'admin@test.com', 'password' => '123456'])
            ->assertRedirect(route('dashboard'));
    }

    // ---------- Deletion ----------

    public function test_deleted_workspace_blocks_users_and_can_be_restored(): void
    {
        $company = $this->demoCompany();

        $this->actingAs($this->super())
            ->delete(route('admin.companies.destroy', $company), ['delete_reason' => 'Cliente se retiró'])
            ->assertSessionHas('ok');

        $this->assertSoftDeleted('companies', ['id' => $company->id]);

        auth()->logout();
        $this->post('/login', ['email' => 'admin@test.com', 'password' => '123456'])
            ->assertSessionHasErrors('email');

        // Restore brings everything back
        $this->actingAs($this->super())->post(route('admin.companies.restore', $company->id));
        $this->assertNull($company->fresh()->deleted_at);
    }

    // ---------- Plan: modules ----------

    public function test_module_outside_the_plan_is_blocked_even_for_the_company_admin(): void
    {
        $company = $this->demoCompany();

        $this->actingAs($this->super())
            ->put(route('admin.companies.plan', $company), [
                'modules' => ['employees', 'attendances', 'settings', 'users', 'profiles'],
                'max_employees' => null, 'max_sites' => null,
            ])->assertSessionHas('ok');

        $admin = $this->demoAdmin();
        $this->actingAs($admin)->get('/reports')->assertForbidden(); // not contracted
        $this->actingAs($admin)->get('/employees')->assertOk();      // contracted
    }

    public function test_all_modules_plan_restores_full_access(): void
    {
        $company = $this->demoCompany();
        $company->update(['modules' => ['employees']]);

        $this->actingAs($this->super())
            ->put(route('admin.companies.plan', $company), ['all_modules' => 1]);

        $this->assertNull($company->fresh()->modules);
        $this->actingAs($this->demoAdmin())->get('/reports')->assertOk();
    }

    // ---------- Plan: limits ----------

    public function test_employee_limit_blocks_creation_beyond_the_plan(): void
    {
        $company = $this->demoCompany();
        $current = Employee::withoutGlobalScopes()->where('company_id', $company->id)->whereNull('deleted_at')->count();
        $company->update(['max_employees' => $current]); // already full

        $schedule = Schedule::withoutGlobalScopes()->where('company_id', $company->id)->first();

        $this->actingAs($this->demoAdmin())
            ->post('/employees', [
                'document_type' => 'DNI', 'document_number' => '99887766',
                'first_name' => 'Extra', 'last_name' => 'LIMIT',
                'vacation_days_per_year' => 30, 'schedule_id' => $schedule->id,
            ])->assertSessionHas('error');

        $this->assertDatabaseMissing('employees', ['document_number' => '99887766']);
    }

    public function test_site_limit_blocks_creation_beyond_the_plan(): void
    {
        $company = $this->demoCompany();
        $current = Site::withoutGlobalScopes()->where('company_id', $company->id)->count();
        $company->update(['max_sites' => $current]);

        $this->actingAs($this->demoAdmin())
            ->post('/sites', ['name' => 'Sede Extra'])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('sites', ['name' => 'Sede Extra']);
    }

    // ---------- Login audit ----------

    public function test_successful_login_is_audited_with_device_and_gps_when_shared(): void
    {
        $this->post('/login', [
            'email' => 'admin@test.com', 'password' => '123456',
            'geo_lat' => '-12.046374', 'geo_lng' => '-77.042793', 'geo_acc' => '25',
        ])->assertRedirect(route('dashboard'));

        $log = AuditLog::withoutGlobalScopes()->where('action', 'LOGIN')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($this->demoCompany()->id, $log->company_id);
        $this->assertSame(-12.046374, $log->data['lat']);
        $this->assertStringContainsString('google.com/maps', $log->data['maps']);
    }

    public function test_failed_login_is_audited_against_the_targets_workspace(): void
    {
        $this->post('/login', ['email' => 'admin@test.com', 'password' => 'wrong-pass']);

        $log = AuditLog::withoutGlobalScopes()->where('action', 'LOGIN_FAILED')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($this->demoCompany()->id, $log->company_id);
        $this->assertSame($this->demoAdmin()->id, $log->user_id);
    }

    public function test_company_admin_only_sees_their_own_audit_super_sees_all(): void
    {
        // One log in each of two companies
        $other = Company::create(['name' => 'Otra Empresa', 'is_active' => true]);
        AuditLog::withoutGlobalScopes()->create(['company_id' => $other->id, 'action' => 'LOGIN', 'module' => 'Security', 'description' => 'other-ws-login', 'ip' => '1.1.1.1']);

        $this->actingAs($this->demoAdmin());
        $this->assertSame(0, AuditLog::where('description', 'other-ws-login')->count());

        auth()->logout();
        $this->actingAs($this->super()); // no acting company: global view
        $this->assertSame(1, AuditLog::withoutGlobalScopes()->where('description', 'other-ws-login')->count());
        $this->assertSame(1, AuditLog::where('description', 'other-ws-login')->count());
    }
}
