<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Profile;
use App\Models\Schedule;
use App\Models\Scopes\CompanyScope;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $acme;
    private Company $globex;
    private Employee $acmeEmp;
    private Employee $globexEmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->acme = Company::create(['name' => 'ACME']);
        $this->globex = Company::create(['name' => 'Globex']);

        CompanyScope::actingAs($this->acme->id, function () {
            $s = Schedule::firstOrCreate(['name' => 'ACME Shift'], ['tolerance_minutes' => 10]);
            $this->acmeEmp = Employee::create(['document_number' => '10000001', 'first_name' => 'A', 'last_name' => 'ACME', 'schedule_id' => $s->id]);
        });
        CompanyScope::actingAs($this->globex->id, function () {
            $s = Schedule::firstOrCreate(['name' => 'Globex Shift'], ['tolerance_minutes' => 10]);
            $this->globexEmp = Employee::create(['document_number' => '10000001', 'first_name' => 'B', 'last_name' => 'GLOBEX', 'schedule_id' => $s->id]);
        });
    }

    private function adminFor(Company $company): User
    {
        return User::create([
            'name' => 'Admin '.$company->name,
            'email' => 'admin_'.$company->id.'@x.com',
            'password' => 'x',
            'company_id' => $company->id,
            'profile_id' => Profile::where('name', 'Administrator')->first()->id,
        ]);
    }

    public function test_same_document_can_exist_in_two_companies(): void
    {
        // Both companies have an employee with document 10000001 — no collision
        $this->assertNotSame($this->acmeEmp->id, $this->globexEmp->id);
    }

    public function test_route_binding_is_tenant_scoped_no_cross_tenant_idor(): void
    {
        // A user + an attendance that belong to Globex
        $globexUser = $this->adminFor($this->globex);
        $globexAttendance = null;
        CompanyScope::actingAs($this->globex->id, function () use (&$globexAttendance) {
            $globexAttendance = \App\Models\Attendance::create([
                'employee_id' => $this->globexEmp->id, 'date' => '2026-07-01', 'status' => 'ON_TIME',
            ]);
        });

        // From ACME's context, binding those ids must resolve to NULL → the route
        // 404s instead of letting ACME mutate Globex's user/attendance (IDOR).
        CompanyScope::actingAs($this->acme->id, function () use ($globexUser, $globexAttendance) {
            $this->assertNull((new User)->resolveRouteBinding($globexUser->id), 'cross-tenant user must not bind');
            $this->assertNull((new \App\Models\Attendance)->resolveRouteBinding($globexAttendance->id), 'cross-tenant attendance must not bind');
        });

        // From Globex's own context, they DO bind (normal operation still works).
        CompanyScope::actingAs($this->globex->id, function () use ($globexUser, $globexAttendance) {
            $this->assertNotNull((new User)->resolveRouteBinding($globexUser->id));
            $this->assertNotNull((new \App\Models\Attendance)->resolveRouteBinding($globexAttendance->id));
        });
    }

    public function test_a_company_user_only_sees_their_own_companys_employees(): void
    {
        $this->actingAs($this->adminFor($this->acme));
        $this->assertEqualsCanonicalizing([$this->acmeEmp->id], Employee::pluck('id')->all());

        $this->actingAs($this->adminFor($this->globex));
        $this->assertEqualsCanonicalizing([$this->globexEmp->id], Employee::pluck('id')->all());
    }

    public function test_a_company_user_cannot_open_another_companys_employee(): void
    {
        $this->actingAs($this->adminFor($this->acme));
        $this->get('/employees/'.$this->globexEmp->id.'/edit')->assertNotFound();
        $this->get('/employees/'.$this->acmeEmp->id.'/edit')->assertOk();
    }

    public function test_super_admin_scopes_to_the_workspace_they_enter(): void
    {
        $super = User::where('is_super_admin', true)->first();
        $this->assertNotNull($super);

        // Before entering any workspace, the dashboard sends them to the console
        $this->actingAs($super)->get('/')->assertRedirect(route('admin.companies.index'));

        // Enter ACME → only ACME data is visible
        $this->actingAs($super)->post('/admin/companies/'.$this->acme->id.'/enter')->assertRedirect();
        $this->actingAs($super)->withSession(['acting_company_id' => $this->acme->id]);
        session(['acting_company_id' => $this->acme->id]);
        $this->assertEqualsCanonicalizing([$this->acmeEmp->id], Employee::pluck('id')->all());

        session(['acting_company_id' => $this->globex->id]);
        $this->assertEqualsCanonicalizing([$this->globexEmp->id], Employee::pluck('id')->all());
    }

    public function test_super_admin_creates_a_workspace_with_its_first_admin(): void
    {
        $super = User::where('is_super_admin', true)->first();

        $this->actingAs($super)->post('/admin/companies', [
            'name' => 'NuevaCorp',
            'timezone' => 'America/Lima',
            'country' => 'PE',
            'locale' => 'es',
            'admin_name' => 'Jefe',
            'admin_email' => 'jefe@nuevacorp.com',
            'admin_password' => 'secret123',
        ])->assertSessionHas('ok');

        $company = Company::where('name', 'NuevaCorp')->first();
        $this->assertNotNull($company);
        $newAdmin = User::withoutGlobalScopes()->where('email', 'jefe@nuevacorp.com')->first();
        $this->assertSame($company->id, $newAdmin->company_id);
        // The workspace gets its own settings row
        $this->assertDatabaseHas('settings', ['company_id' => $company->id, 'company_name' => 'NuevaCorp']);
    }

    public function test_only_super_admin_reaches_the_workspaces_console(): void
    {
        $this->actingAs($this->adminFor($this->acme))->get('/admin/companies')->assertForbidden();
    }
}
