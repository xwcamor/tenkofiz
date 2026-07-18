<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // When on, the kiosk refuses to mark unless a real face is detected in
            // front of the camera. No face seen -> no mark and no photo (the person
            // is asked to show their face). Defaults on: no more silent marks.
            $table->boolean('kiosk_require_face')->default(true)->after('kiosk_liveness');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('kiosk_require_face');
        });
    }
};
