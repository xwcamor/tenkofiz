<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * System profiles (Administrator / Supervisor / Employee) are the base roles every
 * workspace gets. Flagging them lets us protect them: they cannot be deleted or
 * renamed, and the Administrator floor keeps the settings module so no admin can
 * lock themselves out. Custom profiles (is_system = false) stay fully editable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('permissions');
        });

        // Backfill: the three base roles seeded by every workspace
        DB::table('profiles')
            ->whereIn('name', ['Administrator', 'Supervisor', 'Employee'])
            ->update(['is_system' => true]);
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
