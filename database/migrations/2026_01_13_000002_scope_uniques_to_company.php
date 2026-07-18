<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uniqueness that used to be global becomes per-company: two workspaces may each
 * have their own "Sede Central", "Administración", the same national holiday, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropUnique(['date']);
            $table->unique(['company_id', 'date']);
        });
        Schema::table('sites', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['company_id', 'name']);
        });
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['company_id', 'name']);
        });
        Schema::table('areas', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['company_id', 'name']);
        });
        Schema::table('positions', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        foreach (['holidays' => 'date', 'sites' => 'name', 'schedules' => 'name', 'areas' => 'name', 'positions' => 'name'] as $tableName => $column) {
            Schema::table($tableName, function (Blueprint $table) use ($column) {
                $table->dropUnique(['company_id', $column]);
                $table->unique($column);
            });
        }
    }
};
