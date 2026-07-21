<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Some companies do operate on public holidays (retail, security, healthcare).
 * When ON, the kiosk stops hard-blocking holidays: the person can mark, and the
 * mark is tagged as made on a holiday. OFF (default) keeps the labor-friendly
 * behavior of "no marking required on a holiday".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('allow_holiday_marking')->default(false)->after('kiosk_geolocation_required');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('allow_holiday_marking');
        });
    }
};
