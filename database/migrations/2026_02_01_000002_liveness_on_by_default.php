<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Liveness (the random gesture challenge) ON by default for everyone. It is the
 * only free, reliable defense against a printed photo AND a pre-recorded video on
 * a plain webcam; the gesture costs the real person ~2 seconds. Still a per-
 * workspace toggle for companies that prefer speed over strictness.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('kiosk_liveness')->default(true)->change();
        });

        DB::table('settings')->update(['kiosk_liveness' => true]);
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('kiosk_liveness')->default(false)->change();
        });
    }
};
