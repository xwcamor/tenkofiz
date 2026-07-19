<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kiosk recognition hardening (decided with the product owner):
 *  - "require a detected face" stops being optional: without a face on camera
 *    there is NEVER a mark nor a photo, so the toggle column goes away.
 *  - The verification window default drops from 15s to 10s (queues move faster;
 *    the window is a ceiling, not a duration). Rows still on the old default are
 *    moved to the new one; explicitly customized values are untouched.
 *  - Calibration values (threshold + seconds) are now edited ONLY from the
 *    super-admin workspace console, never from the company's own Settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('kiosk_require_face');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('kiosk_verify_seconds')->default(10)->change();
        });

        DB::table('settings')->where('kiosk_verify_seconds', 15)->update(['kiosk_verify_seconds' => 10]);
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('kiosk_require_face')->default(true)->after('kiosk_liveness');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('kiosk_verify_seconds')->default(15)->change();
        });
    }
};
