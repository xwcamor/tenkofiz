<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Education vertical (opt-in): "asynchronous / credited hours". Some schools pay
 * for hours the person does remotely (async) that CANNOT be marked at the kiosk.
 * A workspace flag turns the feature on; per schedule you set how many async
 * minutes each working day is worth. When the flag is off (the default for every
 * normal company) nothing changes — the field stays 0 and the maths are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('async_hours_enabled')->default(false)->after('kiosk_breaks_enabled');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedSmallInteger('async_minutes_per_day')->default(0)->after('target_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('settings', fn (Blueprint $t) => $t->dropColumn('async_hours_enabled'));
        Schema::table('schedules', fn (Blueprint $t) => $t->dropColumn('async_minutes_per_day'));
    }
};
