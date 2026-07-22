<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceDefaultSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_workspace_is_born_with_a_general_site(): void
    {
        $this->seed(DatabaseSeeder::class);
        $super = User::where('is_super_admin', true)->firstOrFail();

        $this->actingAs($super)->post('/admin/companies', [
            'name' => 'NuevaCorp', 'timezone' => 'America/Lima', 'country' => 'PE', 'locale' => 'es',
            'admin_name' => 'Jefe', 'admin_email' => 'jefe@nuevacorp.com', 'admin_password' => 'secret123',
        ])->assertSessionHas('ok');

        $company = Company::where('name', 'NuevaCorp')->firstOrFail();

        CompanyScope::actingAs($company->id, function () {
            $sites = Site::all();
            $this->assertCount(1, $sites);
            $this->assertSame('General', $sites->first()->name);
            $this->assertSame('America/Lima', $sites->first()->timezone); // uses the workspace timezone
        });
    }
}
