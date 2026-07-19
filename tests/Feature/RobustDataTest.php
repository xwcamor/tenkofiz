<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobustDataTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail();
    }

    private function makeEmployees(int $count): void
    {
        $schedule = Schedule::first();
        $rows = [];
        foreach (range(1, $count) as $i) {
            $rows[] = [
                'company_id' => \App\Models\Company::orderBy('id')->value('id'),
                'document_number' => str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'document_type' => 'DNI',
                'first_name' => 'NAME'.$i,
                'last_name' => 'SURNAME'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'schedule_id' => $schedule->id,
                'vacation_days_per_year' => 30,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Employee::insert($rows);
    }

    public function test_employees_index_is_paginated(): void
    {
        $admin = $this->admin();
        $this->makeEmployees(60);

        $response = $this->actingAs($admin)->get('/employees');
        $response->assertOk();

        $employees = $response->viewData('employees');
        $this->assertSame(25, $employees->count());   // page size
        $this->assertSame(60, $employees->total());   // total known for the pager

        // Page 2 returns the next slice
        $page2 = $this->actingAs($admin)->get('/employees?page=2')->viewData('employees');
        $this->assertSame(25, $page2->count());
        $this->assertNotEquals($employees->first()->id, $page2->first()->id);
    }

    public function test_employees_search_filters_by_name_and_document(): void
    {
        $admin = $this->admin();
        $this->makeEmployees(30);

        // By document number
        $byDocument = $this->actingAs($admin)->get('/employees?q=00000007')->viewData('employees');
        $this->assertSame(1, $byDocument->total());
        $this->assertSame('00000007', $byDocument->first()->document_number);

        // By last name
        $byName = $this->actingAs($admin)->get('/employees?q=SURNAME0003')->viewData('employees');
        $this->assertSame(1, $byName->total());

        // LIKE wildcards typed by the user are treated literally, not as patterns
        $wildcard = $this->actingAs($admin)->get('/employees?q=%25')->viewData('employees');
        $this->assertSame(0, $wildcard->total());
    }

    public function test_employees_face_and_status_filters(): void
    {
        $admin = $this->admin();
        $this->makeEmployees(3);
        Employee::first()->update(['face_descriptor' => json_encode([array_fill(0, 128, 0.1)])]);
        Employee::orderByDesc('id')->first()->update(['is_active' => false]);

        $this->assertSame(1, $this->actingAs($admin)->get('/employees?face=enrolled')->viewData('employees')->total());
        $this->assertSame(2, $this->actingAs($admin)->get('/employees?face=pending')->viewData('employees')->total());
        $this->assertSame(1, $this->actingAs($admin)->get('/employees?status=inactive')->viewData('employees')->total());
    }

    public function test_users_index_is_paginated_and_searchable(): void
    {
        $admin = $this->admin();

        $profileId = $admin->profile_id;
        $rows = [];
        foreach (range(1, 40) as $i) {
            $rows[] = [
                'company_id' => \App\Models\Company::orderBy('id')->value('id'),
                'name' => 'User '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'email' => 'user'.$i.'@example.com',
                'password' => 'x',
                'profile_id' => $profileId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        User::insert($rows);

        $users = $this->actingAs($admin)->get('/users')->viewData('users');
        $this->assertSame(25, $users->count());
        $this->assertGreaterThanOrEqual(41, $users->total()); // 40 + seeded admin

        $search = $this->actingAs($admin)->get('/users?q=user7@example.com')->viewData('users');
        $this->assertSame(1, $search->total());
    }
}
