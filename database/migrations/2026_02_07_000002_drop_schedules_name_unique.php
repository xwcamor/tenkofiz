<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Personalized (per-person) schedules may legitimately repeat a name — two
 * instructors can both have "IA 202 (2026-10)". Drop the DB-level unique
 * (company_id, name); uniqueness for the SHARED catalog is enforced in the app.
 *
 * On MySQL that unique index also covers the company_id foreign key, so a plain
 * index on company_id is added FIRST (otherwise MySQL refuses: "needed in a foreign
 * key constraint"). SQLite has no such requirement but the extra index is harmless.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // Keep the FK covered before removing the composite unique.
            if (!$this->indexExists('schedules', 'schedules_company_id_index')) {
                DB::statement('ALTER TABLE `schedules` ADD INDEX `schedules_company_id_index` (`company_id`)');
            }
            if ($this->indexExists('schedules', 'schedules_company_id_name_unique')) {
                DB::statement('ALTER TABLE `schedules` DROP INDEX `schedules_company_id_name_unique`');
            }

            return;
        }

        // SQLite / others: Laravel rebuilds the table to drop the unique.
        Schema::table('schedules', function ($table) {
            $table->dropUnique('schedules_company_id_name_unique');
        });
    }

    public function down(): void
    {
        // Re-adding the unique may fail if personalized names now collide — best effort.
        Schema::table('schedules', function ($table) {
            $table->unique(['company_id', 'name']);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn ($row) => ($row->Key_name ?? null) === $index);
    }
};
