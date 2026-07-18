<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kiosk marking-window rules (both default to 0 = disabled, so the current
 * "mark at any time" behaviour is preserved until an administrator sets them):
 *  - early_check_in_minutes: how many minutes before the scheduled start an
 *    employee may check in. Marks earlier than that are rejected.
 *  - early_departure_minutes: if the check-out happens more than this many
 *    minutes before the scheduled end, the mark is kept but flagged with an
 *    automatic note for the supervisor (never blocked).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('early_check_in_minutes')->default(0)->after('cutoff_day');
            $table->unsignedSmallInteger('early_departure_minutes')->default(0)->after('early_check_in_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['early_check_in_minutes', 'early_departure_minutes']);
        });
    }
};
