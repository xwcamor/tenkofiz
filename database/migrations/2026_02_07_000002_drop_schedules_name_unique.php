<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personalized (per-person) schedules may legitimately repeat a name — two
 * instructors can both have "IA 202 (2026-10)". Drop the DB-level unique
 * (company_id, name); uniqueness for the SHARED catalog is enforced in the app
 * (ScheduleController), which is the only place that needs it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropUnique('schedules_company_id_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->unique(['company_id', 'name']);
        });
    }
};
