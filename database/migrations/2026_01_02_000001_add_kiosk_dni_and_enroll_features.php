<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // 'method' becomes a plain string so it can also hold 'DNI' (was an enum)
            $table->string('method', 10)->default('FACIAL')->change();
            $table->string('evidence_photo', 255)->nullable()->after('user_agent')
                ->comment('Snapshot taken when marking by DNI (supervisor verification)');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->string('kiosk_enroll_pin', 8)->nullable()->after('kiosk_token')
                ->comment('PIN that unlocks the self-enrollment mode on the kiosk');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('evidence_photo');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('kiosk_enroll_pin');
        });
    }
};
