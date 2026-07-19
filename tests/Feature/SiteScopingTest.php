<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Profile;
use App\Models\Schedule;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteScopingTest extends TestCase
{
    use RefreshDatabase;

    private Site $lima;
    private Site $cusco;
    private Employee $limaEmployee;
    private Employee $cuscoEmployee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $schedule = Schedule::first();
        $this->lima = Site::create(['name' => 'Lima', 'address' => 'Av. Lima 1']);
        $this->cusco = Site::create(['name' => 'Cusco', 'address' => 'Av. Cusco 2']);

        $this->limaEmployee = Employee::create(['document_number' => '11112222', 'first_name' => 'Ana', 'last_name' => 'LIMA', 'schedule_id' => $schedule->id, 'site_id' => $this->lima->id]);
        $this->cuscoEmployee = Employee::create(['document_number' => '33334444', 'first_name' => 'Beto', 'last_name' => 'CUSCO', 'schedule_id' => $schedule->id, 'site_id' => $this->cusco->id]);
    }

    private function siteManager(Site $site): User
    {
        $adminProfile = Profile::where('name', 'Administrator')->first();

        return User::create([
            'name' => 'Site Manager '.$site->name,
            'email' => 'manager_'.$site->id.'@test.com',
            'password' => 'x',
            'profile_id' => $adminProfile->id,
            'company_id' => $site->company_id, // console context: stamp explicitly
            'site_id' => $site->id,
        ]);
    }

    public function test_site_bound_user_only_sees_their_own_sites_employees(): void
    {
        $this->actingAs($this->siteManager($this->lima));

        $this->assertEqualsCanonicalizing(
            [$this->limaEmployee->id],
            Employee::pluck('id')->all()
        );
    }

    public function test_company_admin_without_a_site_sees_every_employee(): void
    {
        $companyAdmin = User::where('email', 'admin@test.com')->first();
        $this->assertNull($companyAdmin->site_id);
        $this->actingAs($companyAdmin);

        $this->assertEqualsCanonicalizing(
            [$this->limaEmployee->id, $this->cuscoEmployee->id],
            Employee::pluck('id')->all()
        );
    }

    public function test_site_bound_user_cannot_open_another_sites_employee(): void
    {
        $this->actingAs($this->siteManager($this->lima));

        // Route-model binding respects the global scope: the Cusco employee is a 404
        $this->get('/employees/'.$this->cuscoEmployee->id.'/edit')->assertNotFound();
        $this->get('/employees/'.$this->limaEmployee->id.'/edit')->assertOk();
    }

    public function test_attendances_are_scoped_to_the_users_site(): void
    {
        Attendance::create(['employee_id' => $this->limaEmployee->id, 'date' => '2026-07-15', 'status' => 'ON_TIME', 'method' => 'MANUAL']);
        Attendance::create(['employee_id' => $this->cuscoEmployee->id, 'date' => '2026-07-15', 'status' => 'ON_TIME', 'method' => 'MANUAL']);

        // Site-bound manager only counts their own site's marks
        $this->actingAs($this->siteManager($this->lima));
        $this->assertSame(1, Attendance::inCurrentSite()->count());

        // Company admin counts everything
        $this->actingAs(User::where('email', 'admin@test.com')->first());
        $this->assertSame(2, Attendance::inCurrentSite()->count());
    }

    public function test_a_new_employee_defaults_to_the_creators_site(): void
    {
        $this->actingAs($this->siteManager($this->cusco));

        $schedule = Schedule::first();
        $created = Employee::create(['document_number' => '55556666', 'first_name' => 'Cid', 'last_name' => 'NUEVO', 'schedule_id' => $schedule->id]);

        $this->assertSame($this->cusco->id, $created->site_id);
    }

    public function test_site_bound_user_only_sees_and_uses_their_own_site_in_the_form(): void
    {
        $manager = $this->siteManager($this->lima);

        // The form only offers THEIR site
        $sites = $this->actingAs($manager)->get('/employees/create')->viewData('sites');
        $this->assertSame([$this->lima->id], $sites->pluck('id')->all());

        // Even submitting another site's id, the employee lands in THEIR site
        $schedule = \App\Models\Schedule::withoutGlobalScopes()->first();
        $response = $this->actingAs($manager)->post('/employees', [
            'document_type' => 'DNI', 'document_number' => '77665544',
            'first_name' => 'Fija', 'last_name' => 'SEDE',
            'schedule_id' => $schedule->id,
            'site_id' => $this->cusco->id, // tampered
            'vacation_days_per_year' => 30,
        ]);
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employees', ['document_number' => '77665544', 'site_id' => $this->lima->id]);
    }
}
