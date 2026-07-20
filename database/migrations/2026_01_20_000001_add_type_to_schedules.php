<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schedule type. 'fixed' (the existing behaviour) judges punctuality against a
 * start time with tolerance. 'flexible' has no fixed start: the person just has
 * to complete a daily target of hours — there is no tardiness, and the early
 * check-in window does not apply. Covers teachers, consultants and part-timers.
 * The type lives on the SCHEDULE (not the company) so one workspace can mix both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('type', 10)->default('fixed')->after('name');
            // Daily target of worked minutes for flexible schedules (null for fixed)
            $table->unsignedSmallInteger('target_minutes')->nullable()->after('tolerance_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['type', 'target_minutes']);
        });
    }
};
