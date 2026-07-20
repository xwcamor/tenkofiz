<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR contract type (full-time / part-time). Purely informative for HR and
 * reports — in Peru part-time (avg < 4h/day) carries different benefits, so it
 * is worth recording. It does NOT drive attendance timing: punctuality and
 * hours are handled by the assigned schedule (fixed/flexible).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('contract_type', 12)->default('full_time')->after('hire_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('contract_type');
        });
    }
};
