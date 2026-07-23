<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Splits the kiosk verification timer into two independent clocks:
 *   - kiosk_verify_seconds (existing) → "inactivity": how long the camera waits
 *     with NO face before returning to the keypad (frees the kiosk for the next).
 *   - kiosk_match_seconds (new) → "attempt": how long a face that IS present may go
 *     without matching before falling back to document + evidence photo. While a
 *     face is present the inactivity clock is frozen, so a person actively trying
 *     to mark is never timed out for effort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('kiosk_match_seconds')->default(20)->after('kiosk_verify_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('settings', fn (Blueprint $t) => $t->dropColumn('kiosk_match_seconds'));
    }
};
