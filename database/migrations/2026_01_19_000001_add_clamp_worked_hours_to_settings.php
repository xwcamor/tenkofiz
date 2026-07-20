<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Worked-hours accounting mode. When ON (the sensible default), worked hours are
 * clamped to the employee's schedule: the paid window is max(check-in, shift
 * start) → min(check-out, shift end). This closes the "mark at 6am to rack up
 * hours" loophole — punctuality is still judged on the real mark, but the hours
 * paid never exceed the scheduled shift. OFF keeps the raw check-out − check-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('clamp_worked_hours')->default(true)->after('early_departure_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('clamp_worked_hours');
        });
    }
};
