<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'document_type' => 'DNI',
            'document_number' => '47019237',
            'first_name' => 'JOHN',
            'last_name' => 'DOE',
            'schedule_id' => Schedule::first()->id,
            'vacation_days_per_year' => 30,
            'is_active' => 1,
        ], $overrides);
    }

    private function admin(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::withoutGlobalScopes()->where('email', 'admin@test.com')->firstOrFail();
    }

    public function test_dni_must_have_exactly_8_digits(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/employees', $this->payload(['document_number' => '123456789']))
            ->assertSessionHasErrors('document_number');

        $this->actingAs($admin)->post('/employees', $this->payload())
            ->assertRedirect('/employees');

        $this->assertSame('DNI', Employee::first()->document_type);
    }

    public function test_foreigner_card_accepts_alphanumeric_9_to_12(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/employees', $this->payload([
            'document_type' => 'CE',
            'document_number' => 'ce00123456',
        ]))->assertRedirect('/employees');

        // Stored uppercase
        $this->assertSame('CE00123456', Employee::first()->document_number);

        // Too short for a CE
        $this->actingAs($admin)->post('/employees', $this->payload([
            'document_type' => 'CE',
            'document_number' => '12345678',
        ]))->assertSessionHasErrors('document_number');
    }

    public function test_passport_accepts_6_to_12_alphanumeric(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/employees', $this->payload([
            'document_type' => 'PASSPORT',
            'document_number' => 'AB1234',
        ]))->assertRedirect('/employees');

        $this->assertSame('PASSPORT', Employee::first()->document_type);
    }

    public function test_unknown_document_type_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/employees', $this->payload(['document_type' => 'OTHER']))
            ->assertSessionHasErrors('document_type');
    }
}
