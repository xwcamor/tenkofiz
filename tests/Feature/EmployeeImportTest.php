<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Employee;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmployeeImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail();
    }

    private function csv(array $rows): UploadedFile
    {
        $lines = array_merge(
            ['document,first,last,schedule,site,area,position,hire_date'],
            array_map(fn ($row) => implode(',', $row), $rows)
        );

        return UploadedFile::fake()->createWithContent('employees.csv', implode("\n", $lines));
    }

    public function test_template_downloads_as_xlsx(): void
    {
        $response = $this->actingAs($this->admin())->get('/employees-import/template');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
    }

    public function test_roster_exports_as_xlsx(): void
    {
        $admin = $this->admin();

        // Import a couple of employees, then export the roster back out
        $site = Site::first();
        $this->actingAs($admin)->post('/employees-import', ['file' => $this->csv([
            ['11112222', 'JOHN', 'DOE', 'Horario General', $site->name, 'Quality', 'Tester', '2026-01-15'],
        ])]);

        $response = $this->actingAs($admin)->get('/employees-export');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
    }

    public function test_valid_file_imports_employees_and_creates_catalogs(): void
    {
        $admin = $this->admin();
        $site = Site::first(); // seeded "Sede Principal"

        $file = $this->csv([
            ['11112222', 'JOHN', 'DOE', 'Horario General', $site->name, 'Quality', 'Tester', '2026-01-15'],
            ['33334444', 'JANE', 'ROE', 'Horario General', '', '', '', ''],
        ]);

        $this->actingAs($admin)
            ->post('/employees-import', ['file' => $file])
            ->assertRedirect('/employees');

        $this->assertSame(2, Employee::count());
        $this->assertNotNull(Area::where('name', 'Quality')->first());
        $this->assertSame('2026-01-15', Employee::where('document_number', '11112222')->first()->hire_date->toDateString());
        // Site column is honored (dynamic dropdown from active sites)
        $this->assertSame($site->id, Employee::where('document_number', '11112222')->first()->site_id);
        // Blank site column leaves the employee without a site
        $this->assertNull(Employee::where('document_number', '33334444')->first()->site_id);
    }

    public function test_a_site_that_does_not_exist_is_rejected(): void
    {
        $admin = $this->admin();

        $file = $this->csv([
            ['11112222', 'JOHN', 'DOE', 'Horario General', 'Ghost Site', '', '', ''],
        ]);

        $this->actingAs($admin)->post('/employees-import', ['file' => $file])
            ->assertSessionHas('import_errors');

        $this->assertSame(0, Employee::count());
    }

    public function test_import_is_all_or_nothing_and_reports_row_errors(): void
    {
        $admin = $this->admin();

        $file = $this->csv([
            ['11112222', 'JOHN', 'DOE', 'Horario General', '', '', '', ''],
            ['BAD-DOC', 'JANE', 'ROE', 'Nonexistent Shift', '', '', '', ''],
        ]);

        $response = $this->actingAs($admin)->post('/employees-import', ['file' => $file]);

        $response->assertSessionHas('import_errors');
        $this->assertSame(0, Employee::count());

        $errors = session('import_errors');
        $this->assertSame(3, $errors[0]['row']); // header + 1 valid row before it
        $this->assertCount(2, $errors[0]['messages']); // bad document + bad schedule
    }

    public function test_duplicate_documents_within_file_are_rejected(): void
    {
        $admin = $this->admin();

        $file = $this->csv([
            ['11112222', 'JOHN', 'DOE', 'Horario General', '', '', '', ''],
            ['11112222', 'JANE', 'ROE', 'Horario General', '', '', '', ''],
        ]);

        $this->actingAs($admin)
            ->post('/employees-import', ['file' => $file])
            ->assertSessionHas('import_errors');

        $this->assertSame(0, Employee::count());
    }
}
