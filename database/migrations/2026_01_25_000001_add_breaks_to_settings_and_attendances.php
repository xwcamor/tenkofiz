<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Break control (per workspace). When enabled, the kiosk lets a person punch out
 * for a break and back, and the break time is subtracted from worked hours. A
 * break longer than the limit is only FLAGGED ("time exceeded"), never penalized.
 * Off by default → the flow stays exactly as before (one check-in + check-out).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('kiosk_breaks_enabled')->default(false)->after('clamp_worked_hours');
            $table->boolean('break_required')->default(false)->after('kiosk_breaks_enabled');
            $table->unsignedSmallInteger('break_limit_minutes')->default(60)->after('break_required');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->time('break_out')->nullable()->after('check_out'); // left for break
            $table->time('break_in')->nullable()->after('break_out');  // returned from break
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['kiosk_breaks_enabled', 'break_required', 'break_limit_minutes']);
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['break_out', 'break_in']);
        });
    }
};
