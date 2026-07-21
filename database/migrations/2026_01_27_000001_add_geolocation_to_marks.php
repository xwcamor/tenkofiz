<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kiosk-mark geolocation (per workspace). When enabled, the kiosk asks the
 * browser for the device location at the moment of marking and stores the
 * coordinates with each punch — so a supervisor can see WHERE an employee
 * marked (field workers, third parties at another site). Off by default; the
 * browser GPS needs the user's permission and HTTPS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('kiosk_geolocation')->default(false)->after('kiosk_breaks_enabled');
        });

        Schema::table('attendance_marks', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('method');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->unsignedInteger('accuracy')->nullable()->after('lng'); // metres
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('kiosk_geolocation');
        });
        Schema::table('attendance_marks', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng', 'accuracy']);
        });
    }
};
