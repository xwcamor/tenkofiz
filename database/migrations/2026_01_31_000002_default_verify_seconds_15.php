<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recognition window back to 15 seconds for everyone (was 10). A longer window
 * gives the face more time to be confirmed before any fallback, reducing false
 * "couldn't recognize you" cases. Kept configurable per workspace by the super.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('kiosk_verify_seconds')->default(15)->change();
        });

        DB::table('settings')->update(['kiosk_verify_seconds' => 15]);
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('kiosk_verify_seconds')->default(10)->change();
        });
    }
};
