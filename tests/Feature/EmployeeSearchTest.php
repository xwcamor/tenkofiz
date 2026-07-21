<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Profile;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Vacation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSearchTest extends TestCase
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

    public function test_search_returns_paginated_select2_results(): void
    {
        $admin = $this->admin();
        $this->makeEmployees(25);

        $page1 = $this->actingAs($admin)->getJson('/lookup/employees')->assertOk()->json();
        $this->assertCount(20, $page1['results']);
        $this->assertTrue($page1['pagination']['more']);

        $page2 = $this->actingAs($admin)->getJson('/lookup/employees?page=2')->json();
        $this->assertCount(5, $page2['results']);
        $this->assertFalse($page2['pagination']['more']);
    }

    public function test_search_filters_by_term_and_includes_the_balance(): void
    {
        $admin = $this->admin();
        $this->makeEmployees(10);

        $employee = Employee::where('document_number', '00000004')->first();
        Vacation::create([
            'employee_id' => $employee->id,
            'start_date' => company_now()->addMonth()->toDateString(),
            'end_date' => company_now()->addMonth()->addDays(6)->toDateString(),
            'days' => 7,
            'reason' => 'Trip',
            'status' => 'APPROVED',
        ]);

        $data = $this->actingAs($admin)->getJson('/lookup/employees?q=00000004')->json();

        $this->assertCount(1, $data['results']);
        // The autocomplete returns the obfuscated (Hashid) id, never the raw key
        $this->assertSame($employee->getRouteKey(), $data['results'][0]['id']);
        $this->assertSame($employee->id, \App\Support\Hashid::decode($data['results'][0]['id']));
        $this->assertStringContainsString('00000004', $data['results'][0]['text']);
        $this->assertSame(23, $data['results'][0]['balance']); // 30 allowance - 7 approved
    }

    public function test_search_is_forbidden_for_non_managers(): void
    {
        $this->admin();

        $profile = Profile::create(['name' => 'No modules', 'permissions' => []]);
        $user = User::create([
            'name' => 'Plain User',
            'email' => 'plain@example.com',
            'password' => 'x',
            'profile_id' => $profile->id,
        ]);

        $this->actingAs($user)->getJson('/lookup/employees')->assertForbidden();
    }
}
