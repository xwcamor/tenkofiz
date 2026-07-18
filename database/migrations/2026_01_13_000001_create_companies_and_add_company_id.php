<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SaaS Phase 1: turn the single-tenant system into a multi-company (workspace)
 * one. A super-admin owns all companies; every business row belongs to a company.
 * Existing data is preserved by moving it all into a default "Empresa Demo".
 */
return new class extends Migration
{
    /** Tables that gain a company_id (business data scoped per workspace) */
    private array $tables = [
        'users', 'sites', 'employees', 'settings',
        'schedules', 'areas', 'positions', 'holidays', 'holiday_templates',
    ];

    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('tax_id', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Default workspace that inherits all existing data. Name it after the
        // current company settings when present, else "Empresa Demo".
        $existingName = DB::table('settings')->value('company_name') ?: 'Empresa Demo';
        $defaultId = DB::table('companies')->insertGetId([
            'name' => $existingName,
            'tax_id' => DB::table('settings')->value('tax_id'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($this->tables as $tableName) {
            if (!Schema::hasColumn($tableName, 'company_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
                });
                // Move all existing rows into the default company
                DB::table($tableName)->update(['company_id' => $defaultId]);
            }
        }

        // Super-admin flag: a user with is_super_admin owns every workspace.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('company_id');
        });

        // Bootstrap a dedicated super-admin for existing installs (idempotent).
        if (!DB::table('users')->where('is_super_admin', true)->exists()) {
            $existing = DB::table('users')->where('email', 'super@test.com')->first();
            if ($existing) {
                DB::table('users')->where('id', $existing->id)->update(['is_super_admin' => true, 'company_id' => null]);
            } else {
                DB::table('users')->insert([
                    'name' => 'Super Admin',
                    'email' => 'super@test.com',
                    'password' => bcrypt('123456'),
                    'is_super_admin' => true,
                    'company_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });

        foreach ($this->tables as $tableName) {
            if (Schema::hasColumn($tableName, 'company_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('company_id');
                });
            }
        }

        Schema::dropIfExists('companies');
    }
};
