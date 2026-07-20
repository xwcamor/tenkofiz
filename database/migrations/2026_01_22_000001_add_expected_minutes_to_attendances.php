<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of the expected minutes (the "jornada") for the day, frozen when the
 * check-in is recorded — exactly like the PUNTUAL/TARDANZA status is frozen.
 * This makes past reports immune to later schedule changes: if you reassign an
 * employee to a different schedule, their old days keep the hours that were due
 * back then. Rows without a snapshot (older data) fall back to a live compute.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedSmallInteger('expected_minutes')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('expected_minutes');
        });
    }
};
