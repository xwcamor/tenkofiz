<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A sensible default early-check-in window: 15 minutes before the shift start.
 * Previously 0 (mark at any time), which let people clock in hours early.
 * New workspaces get 15; existing rows still on the old 0 default are bumped to
 * 15 (0 typed on purpose still means "no restriction" and can be set again).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('early_check_in_minutes')->default(15)->change();
        });

        DB::table('settings')->where('early_check_in_minutes', 0)->update(['early_check_in_minutes' => 15]);
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('early_check_in_minutes')->default(0)->change();
        });
    }
};
