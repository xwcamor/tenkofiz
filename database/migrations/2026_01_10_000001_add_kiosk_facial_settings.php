<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kiosk facial-recognition settings (configurable in Settings → Facial):
 *  - kiosk_fast_mode: when ON, the old 1:N auto-scan ("stand and be recognized")
 *    is used. When OFF (default), the kiosk uses DNI + 1:1 face verification,
 *    which is far more reliable (no confusion between similar people).
 *  - kiosk_liveness: require a blink during verification (anti-photo).
 *  - kiosk_face_threshold: match strictness (lower = stricter). 1:1 verification
 *    can afford a stricter default than the old 1:N scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('kiosk_fast_mode')->default(false)->after('kiosk_enroll_pin');
            $table->boolean('kiosk_liveness')->default(false)->after('kiosk_fast_mode');
            $table->decimal('kiosk_face_threshold', 3, 2)->default(0.50)->after('kiosk_liveness');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['kiosk_fast_mode', 'kiosk_liveness', 'kiosk_face_threshold']);
        });
    }
};
