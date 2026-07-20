<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimum minutes that must pass between check-in and check-out (guards against
 * an accidental double mark). Made configurable per company: the safe default is
 * 30, but a workplace can lower it to be humane about genuine early exits
 * (emergencies, a mistaken early mark) instead of blocking the person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_checkout_minutes')->default(30)->after('early_departure_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('min_checkout_minutes');
        });
    }
};
