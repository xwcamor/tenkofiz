<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Force geolocation" for the kiosk: when enabled (and geolocation is on), a mark
 * is rejected unless the device sends its coordinates, and the camera does not
 * even activate without them. For companies whose workers mark from anywhere via
 * the shared kiosk link and must prove where they were.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('kiosk_geolocation_required')->default(false)->after('kiosk_geolocation');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('kiosk_geolocation_required');
        });
    }
};
