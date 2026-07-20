<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the shift bounds (start/end) used to clamp worked hours, captured at
 * check-in — the last piece so a LATER schedule change never rewrites a past
 * day's worked hours. Status and expected minutes are already frozen; this makes
 * the whole day's numbers immune. Rows without frozen bounds fall back to the
 * current schedule (legacy behaviour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->time('shift_start')->nullable()->after('expected_minutes');
            $table->time('shift_end')->nullable()->after('shift_start');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['shift_start', 'shift_end']);
        });
    }
};
